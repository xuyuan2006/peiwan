<?php
/**
 * 自检工具（浏览器访问 /check.php）
 *
 * 作用（全部为只读操作）：
 *  1) 语法检查：用 PHP 内置词法分析器 TOKEN_PARSE 解析项目内所有 .php 文件（等价于 php -l，无需命令行）
 *  2) SQL 静态核对：逐个提取 Db::one/all/value/query 的 SQL 与绑定参数，核对 `?` 占位符个数是否与参数个数一致
 *  3) 路由完整性：核对全项目 url('控制器/方法')、admin_url(...)、redirect_route(...) 是否都有对应的控制器方法
 *  4) 运行环境：PHP 版本、扩展、目录可写
 *  5) 数据库：连接、18 张表、关键字段
 *  6) 上线安全项
 *
 * **上线后请删除本文件。**
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // 权限判定通过后再打开，避免向匿名访客暴露告警细节

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

function rel_path(string $path): string
{
    return str_replace('\\', '/', substr($path, strlen(ROOT_DIR) + 1));
}

/** 后台入口目录名（安装时可自定义；这里直接读配置，不依赖 app 里的 helpers） */
function admin_dir_name(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $dir  = 'admin';
    $file = ROOT_DIR . '/config/config.php';
    if (is_file($file)) {
        $cfg = include $file;
        if (is_array($cfg) && !empty($cfg['security']['admin_path'])) {
            $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $cfg['security']['admin_path']);
            if (is_string($clean) && $clean !== '') {
                $dir = $clean;
            }
        }
    }
    return $dir;
}

/** 收集项目内所有 PHP 文件 */
function collect_php_files(): array
{
    static $files = null;
    if ($files !== null) {
        return $files;
    }
    $dirs = [ROOT_DIR . '/app', ROOT_DIR . '/config', ROOT_DIR . '/cron', ROOT_DIR . '/public'];
    $out  = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $out[] = $file->getPathname();
                }
            }
        } catch (Throwable $e) {
            // 目录不可读（如共享主机上的 uploads）时跳过，不能让自检页自己白屏
            continue;
        }
    }
    sort($out);
    return $files = $out;
}

// ================================================================ 1. 语法检查
/** @return array{files:int,errors:array<int,array{file:string,line:int,msg:string}>,ms:int} */
function lint_php_files(): array
{
    $start  = microtime(true);
    $errors = [];
    $files  = collect_php_files();

    foreach ($files as $path) {
        $code = (string) file_get_contents($path);
        if (strpos($code, '<?php') === false && strpos($code, '<?=') === false) {
            continue;
        }
        try {
            // TOKEN_PARSE 会让词法器做完整语法解析，语法错误抛 ParseError
            token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            $errors[] = ['file' => rel_path($path), 'line' => (int) $e->getLine(), 'msg' => $e->getMessage()];
        } catch (Throwable $e) {
            $errors[] = ['file' => rel_path($path), 'line' => 0, 'msg' => '解析异常：' . $e->getMessage()];
        }
    }

    return [
        'files'  => count($files),
        'errors' => $errors,
        'ms'     => (int) round((microtime(true) - $start) * 1000),
    ];
}

// ================================================================ 2. SQL 静态核对

/**
 * 从 '(' 的位置提取一次函数调用的完整参数，按顶层逗号切分。
 * 会正确跳过字符串字面量、嵌套括号/数组。
 * @return array<int,string>|null null 表示括号未闭合
 */
function extract_args(string $code, int $openPos): ?array
{
    $len   = strlen($code);
    $depth = 0;
    $quote = null;
    $buf   = '';
    $args  = [];

    for ($i = $openPos; $i < $len; $i++) {
        $ch = $code[$i];

        if ($quote !== null) {
            $buf .= $ch;
            if ($ch === '\\') {
                if ($i + 1 < $len) {
                    $buf .= $code[$i + 1];
                    $i++;
                }
                continue;
            }
            if ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === '(' || $ch === '[' || $ch === '{') {
            $depth++;
            if ($depth === 1 && $ch === '(') {
                continue; // 跳过调用自身的左括号
            }
            $buf .= $ch;
            continue;
        }
        if ($ch === ')' || $ch === ']' || $ch === '}') {
            $depth--;
            if ($depth === 0) {
                $args[] = $buf;
                break;
            }
            $buf .= $ch;
            continue;
        }
        if ($ch === ',' && $depth === 1) {
            $args[] = $buf;
            $buf    = '';
            continue;
        }
        $buf .= $ch;
    }

    if ($i >= $len) {
        return null; // 未闭合
    }
    $args = array_map('trim', $args);
    if (count($args) === 1 && $args[0] === '') {
        return [];
    }
    return $args;
}

/** 按顶层逗号切分数组内部内容（已去掉外层 []） */
function split_top_level(string $inner): array
{
    $len   = strlen($inner);
    $depth = 0;
    $quote = null;
    $buf   = '';
    $out   = [];

    for ($i = 0; $i < $len; $i++) {
        $ch = $inner[$i];

        if ($quote !== null) {
            $buf .= $ch;
            if ($ch === '\\') {
                if ($i + 1 < $len) {
                    $buf .= $inner[$i + 1];
                    $i++;
                }
                continue;
            }
            if ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === '(' || $ch === '[' || $ch === '{') {
            $depth++;
            $buf .= $ch;
            continue;
        }
        if ($ch === ')' || $ch === ']' || $ch === '}') {
            $depth--;
            $buf .= $ch;
            continue;
        }
        if ($ch === ',' && $depth === 0) {
            $out[] = trim($buf);
            $buf   = '';
            continue;
        }
        $buf .= $ch;
    }
    $out[] = trim($buf);

    return array_values(array_filter($out, function ($v) {
        return $v !== '';
    }));
}

/**
 * 静态核对 SQL 占位符与参数数量
 * @return array{checked:int,skipped:int,bad:array<int,array<string,mixed>>,skipped_list:array<int,string>}
 */
function sql_placeholder_check(): array
{
    $checked = 0;
    $skipped = 0;
    $bad     = [];
    $skippedList = [];

    foreach (collect_php_files() as $path) {
        $code   = (string) file_get_contents($path);
        $rel    = rel_path($path);
        $offset = 0;

        while (($pos = strpos($code, 'Db::', $offset)) !== false) {
            $offset = $pos + 4;
            $head   = substr($code, $pos, 60);
            if (!preg_match('/^Db::(one|all|value|query|insert|update)\s*\(/', $head, $m)) {
                continue;
            }
            $method  = $m[1];
            $openPos = strpos($code, '(', $pos);
            if ($openPos === false) {
                continue;
            }
            $line = substr_count(substr($code, 0, $pos), "\n") + 1;
            $args = extract_args($code, $openPos);
            if ($args === null || !$args) {
                $skipped++;
                continue;
            }

            // insert/update 的占位符由 Db 内部生成或来自 $where 变量，无法静态核对
            if (!in_array($method, ['one', 'all', 'value', 'query'], true)) {
                $skipped++;
                continue;
            }

            $sqlArg = $args[0];
            // 只核对"纯单引号字符串"的 SQL；拼接或双引号（含变量）一律跳过
            if (!preg_match("/^'(?:[^'\\\\]|\\\\.)*'$/s", $sqlArg)) {
                $skipped++;
                $skippedList[] = $rel . ':' . $line . '（SQL 为拼接或含变量，无法静态判定）';
                continue;
            }
            $sql   = substr($sqlArg, 1, -1);
            $marks = substr_count($sql, '?');

            if (count($args) < 2) {
                $checked++;
                if ($marks > 0) {
                    $bad[] = [
                        'file' => $rel, 'line' => $line, 'marks' => $marks, 'params' => 0,
                        'sql' => $sql, 'note' => 'SQL 有 ' . $marks . ' 个占位符，但没有传参数',
                    ];
                }
                continue;
            }

            $params = $args[1];
            if ($params === '') {
                $checked++;
                if ($marks > 0) {
                    $bad[] = [
                        'file' => $rel, 'line' => $line, 'marks' => $marks, 'params' => 0,
                        'sql' => $sql, 'note' => '参数为空但 SQL 有占位符',
                    ];
                }
                continue;
            }
            if ($params[0] !== '[' || substr($params, -1) !== ']') {
                $skipped++;
                $skippedList[] = $rel . ':' . $line . '（参数不是内联数组，无法静态判定）';
                continue;
            }

            $elements = split_top_level(substr($params, 1, -1));
            $count    = count($elements);
            $checked++;

            if ($marks !== $count) {
                $bad[] = [
                    'file' => $rel, 'line' => $line, 'marks' => $marks, 'params' => $count,
                    'sql' => $sql, 'note' => '占位符 ' . $marks . ' 个，参数 ' . $count . ' 个，数量不一致',
                ];
            }
        }
    }

    return ['checked' => $checked, 'skipped' => $skipped, 'bad' => $bad, 'skipped_list' => $skippedList];
}

// ================================================================ 3. 路由完整性

function studly(string $value): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
}

/**
 * 核对视图/入口里的路由字符串是否有对应的控制器方法
 * @return array{found:int,missing:array<int,array{file:string,line:int,route:string,reason:string}>}
 */
function route_check(): array
{
    $found   = 0;
    $missing = [];
    $pattern = '/(admin_url|url|redirect_route|redirect_back)\(\s*\'([A-Za-z0-9_]+)\/([A-Za-z0-9_]+)\'/';

    foreach (collect_php_files() as $path) {
        $rel  = rel_path($path);
        $code = (string) file_get_contents($path);
        if (!preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches as $hit) {
            $func    = $hit[1][0];
            $ctrl    = $hit[2][0];
            $action  = $hit[3][0];
            $pos     = (int) $hit[0][1];
            $line    = substr_count(substr($code, 0, $pos), "\n") + 1;
            $route   = $ctrl . '/' . $action;
            $isAdmin = ($func === 'admin_url');

            $class  = studly($ctrl) . 'Controller';
            $file   = $isAdmin
                ? ROOT_DIR . '/app/Controllers/Admin/' . $class . '.php'
                : ROOT_DIR . '/app/Controllers/' . $class . '.php';
            $method = lcfirst(studly($action));

            if (!is_file($file)) {
                $missing[] = ['file' => $rel, 'line' => $line, 'route' => $route, 'reason' => '控制器文件 ' . $class . '.php 不存在'];
                continue;
            }
            $classCode = (string) file_get_contents($file);
            if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $classCode)) {
                $missing[] = ['file' => $rel, 'line' => $line, 'route' => $route, 'reason' => $class . '::' . $method . '() 方法不存在'];
                continue;
            }
            $found++;
        }
    }

    return ['found' => $found, 'missing' => $missing];
}

// ================================================================ 4. 环境检查
function env_checks(): array
{
    $uploadDir = ROOT_DIR . '/public/uploads';
    return [
        ['PHP 版本 ≥ 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION],
        ['PDO 扩展', extension_loaded('pdo'), extension_loaded('pdo') ? '已开启' : '未开启'],
        ['PDO MySQL 驱动', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? '已开启' : '未开启'],
        ['mbstring 扩展', extension_loaded('mbstring'), extension_loaded('mbstring') ? '已开启' : '未开启'],
        ['json 扩展', extension_loaded('json'), extension_loaded('json') ? '已开启' : '未开启'],
        ['config 目录可写', is_writable(ROOT_DIR . '/config'), ROOT_DIR . '/config'],
        ['public/uploads 可写', is_dir($uploadDir) ? is_writable($uploadDir) : is_writable(ROOT_DIR . '/public'), $uploadDir],
        ['sql/schema.sql 存在', is_file(ROOT_DIR . '/sql/schema.sql'), ''],
    ];
}

// ================================================================ 5. 安全提示
function security_notes(): array
{
    $notes = [];
    if (is_file(ROOT_DIR . '/public/install.php')) {
        $notes[] = ['warn', 'public/install.php 仍然存在，安装完成后请删除或改名（否则别人可以重置管理员密码）'];
    }
    if (is_file(ROOT_DIR . '/public/check.php')) {
        $notes[] = ['warn', 'public/check.php（本文件）上线后请删除'];
    }
    if (is_file(ROOT_DIR . '/public/selftest.php')) {
        $notes[] = ['warn', 'public/selftest.php 上线后请删除'];
    }
    if (is_file(ROOT_DIR . '/public/demo.php')) {
        $notes[] = ['warn', 'public/demo.php 上线后请删除（避免被用来生成/清除数据）'];
    }

    $configFile = ROOT_DIR . '/config/config.php';
    if (is_file($configFile)) {
        $cfg = include $configFile;
        if (is_array($cfg) && !empty($cfg['app']['debug'])) {
            $notes[] = ['warn', 'config/config.php 中 app.debug 仍为 true，上线请改为 false（否则报错会暴露文件路径）'];
        }
        $docRoot   = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $publicDir = str_replace('\\', '/', ROOT_DIR . '/public');
        if ($docRoot !== '' && $docRoot === str_replace('\\', '/', ROOT_DIR)) {
            $notes[] = ['danger', '网站根目录指向了项目根目录，app/ 与 config/ 存在被下载的风险。请把运行目录改为 public/'];
        } elseif ($docRoot !== '' && $docRoot !== $publicDir) {
            $notes[] = ['info', '当前网站根目录：' . $docRoot . '（建议指向 ' . $publicDir . '）'];
        }
    } else {
        $notes[] = ['info', '还没有 config/config.php，请先访问 install.php 安装'];
    }
    return $notes;
}

// ================================================================ 6. 数据库检查
function db_check(): array
{
    $configFile = ROOT_DIR . '/config/config.php';
    if (!is_file($configFile)) {
        return ['ok' => false, 'msg' => '缺少 config/config.php', 'tables' => [], 'missing' => [], 'columns' => [], 'schema_actions' => []];
    }
    $cfg = include $configFile;
    if (!is_array($cfg) || empty($cfg['db'])) {
        return ['ok' => false, 'msg' => 'config/config.php 内容不完整', 'tables' => [], 'missing' => [], 'columns' => [], 'schema_actions' => []];
    }
    $db       = $cfg['db'];
    $expected = [
        'users', 'companion_profiles', 'games', 'companion_skills', 'orders', 'order_logs',
        'order_reviews', 'user_wallets', 'wallet_transactions', 'recharge_orders',
        'withdraw_orders', 'settings', 'admin_logs', 'verify_codes', 'tickets', 'ticket_replies',
        'announcements', 'banners', 'order_packages', 'order_messages',
    ];
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']),
            (string) $db['user'],
            (string) $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $rows    = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($expected, $rows));

        // 用 SchemaSync 做完整的"字段/索引缺失"比对（以 sql/schema.sql 为准）
        $schemaSyncActions = [];
        $syncFile = ROOT_DIR . '/app/SchemaSync.php';
        if (is_file($syncFile) && is_file(ROOT_DIR . '/sql/schema.sql')) {
            require_once $syncFile;
            try {
                $diff = \App\SchemaSync::diff($pdo, ROOT_DIR . '/sql/schema.sql');
                foreach ($diff['columns'] as $item) {
                    $schemaSyncActions[] = '缺少字段 ' . $item['table'] . '.' . $item['column'];
                }
                foreach ($diff['keys'] as $item) {
                    $schemaSyncActions[] = '缺少索引 ' . $item['table'] . '.' . $item['key'];
                }
            } catch (Throwable $e) {
                $schemaSyncActions[] = '结构比对失败：' . $e->getMessage();
            }
        }

        $columnIssues = [];
        $need = [
            'users'               => ['email', 'credit_score', 'last_login_ip'],
            'orders'              => ['platform_fee', 'companion_income', 'is_reviewed', 'expire_at', 'refund_operator', 'refund_from_status', 'status'],
            'companion_profiles'  => ['review_count', 'total_income', 'is_recommend', 'sort', 'audit_status'],
            'user_wallets'        => ['balance', 'frozen', 'total_recharge', 'total_income', 'total_withdraw', 'total_expense'],
            'wallet_transactions' => ['direction', 'balance_before', 'balance_after', 'frozen_before', 'frozen_after'],
            'verify_codes'        => ['target', 'channel', 'scene', 'status', 'attempts', 'expire_at'],
            'recharge_orders'     => ['order_no', 'pay_url', 'trade_no', 'status', 'pay_voucher'],
            'tickets'             => ['ticket_no', 'user_id', 'category', 'order_id', 'title', 'content', 'status', 'admin_id', 'updated_at'],
            'ticket_replies'      => ['ticket_id', 'sender_type', 'sender_id', 'content'],
            'announcements'       => ['content', 'link_url', 'sort', 'status'],
            'banners'             => ['title', 'image', 'link_url', 'sort', 'status'],
            'order_packages'      => ['title', 'game_id', 'price', 'cover', 'description', 'sort', 'status'],
            'order_messages'      => ['order_id', 'sender_id', 'sender_role', 'content', 'is_read'],
            'settings'            => ['k', 'v'],
        ];
        foreach ($need as $table => $cols) {
            if (in_array($table, $rows, true)) {
                $have = array_map('strval', $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN));
                foreach ($cols as $col) {
                    if (!in_array($col, $have, true)) {
                        $columnIssues[] = $table . '.' . $col . ' 缺失';
                    }
                }
            }
        }

        return [
            'ok'      => empty($missing) && empty($columnIssues) && empty($schemaSyncActions),
            'msg'     => '连接成功：' . $db['name'] . '@' . $db['host'],
            'tables'  => $rows,
            'missing' => $missing,
            'columns' => $columnIssues,
            'schema_actions' => $schemaSyncActions,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => '连接失败：' . $e->getMessage(), 'tables' => [], 'missing' => [], 'columns' => [], 'schema_actions' => []];
    }
}

$lint    = lint_php_files();
$sqlScan = sql_placeholder_check();
$routes  = route_check();
$envList = env_checks();

// ---------------------------------------------------------------- 权限判定
// 未安装时允许匿名查看语法/环境自检（装机前需要）；已安装则只有管理员能看到完整报告，
// 避免匿名访客拿到数据库主机/库名、表结构、服务器路径等信息。
$installed = false;
$isAdmin   = false;
$bootstrap = ROOT_DIR . '/app/bootstrap.php';
if (is_file($bootstrap) && is_file(ROOT_DIR . '/config/config.php')) {
    try {
        require_once $bootstrap;
        $installed = \App\Db::tableExists('users');
        $isAdmin   = \App\Auth::isAdmin();
    } catch (Throwable $e) {
        $installed = false;
    }
}
$fullAccess = $isAdmin || !$installed;

if (!$fullAccess) {
    $hasLintError = !empty($lint['errors']);
    ?>
    <!doctype html>
    <html lang="zh-CN">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>系统自检 - 陪玩点单系统</title>
    <style>
      body { margin:0; background:#f4f5f9; color:#23222b; font-family:-apple-system,"Segoe UI","Microsoft YaHei",sans-serif; font-size:14px; line-height:1.7; }
      .wrap { max-width:860px; margin:0 auto; padding:30px 18px 70px; }
      .card { background:#fff; border:1px solid #eceaf3; border-radius:14px; padding:18px; margin-bottom:16px; box-shadow:0 2px 10px rgba(29,22,71,.05); }
      table { width:100%; border-collapse:collapse; font-size:13.5px; }
      th,td { text-align:left; padding:8px 6px; border-bottom:1px solid #f0eef7; }
      .ok { color:#16a34a; font-weight:600; } .bad { color:#e11d48; font-weight:600; } .muted { color:#8b8a97; }
      .alert { padding:11px 14px; border-radius:10px; margin-bottom:12px; font-size:13.5px; }
      .alert-ok { background:#e9f9ef; color:#14804a; } .alert-error { background:#fdecef; color:#b3123a; }
      .alert-info { background:#eaf1fe; color:#1c4ed8; }
      code { background:#f4f3fa; padding:1px 5px; border-radius:5px; }
    </style>
    </head>
    <body>
    <div class="wrap">
      <h1>系统自检（受限视图）</h1>
      <p class="muted">匿名访问只显示语法与环境检查。登录管理员后重新访问可查看数据库结构、SQL 占位符核对、路由核对与安全项。</p>

      <div class="card">
        <h2>PHP 语法检查（等价 php -l）</h2>
        <p>已解析 <strong><?= (int) $lint['files'] ?></strong> 个文件，耗时 <?= (int) $lint['ms'] ?> ms。</p>
        <?php if (!$hasLintError): ?>
          <div class="alert alert-ok">全部文件语法正确。</div>
        <?php else: ?>
          <div class="alert alert-error">发现 <?= count($lint['errors']) ?> 处语法错误，请登录管理员查看明细。</div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>运行环境</h2>
        <table>
          <?php foreach ($envList as $c): ?>
            <tr>
              <td><?= h($c[0]) ?></td>
              <td class="<?= $c[1] ? 'ok' : 'bad' ?>" style="width:80px"><?= $c[1] ? '通过' : '不通过' ?></td>
              <td class="muted"><?= h((string) $c[2]) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="card">
        <div class="alert alert-info">系统已安装：请用管理员账号登录后台，再访问本页查看完整自检报告。</div>
        <p><a href="<?= h(admin_dir_name()) ?>/index.php?r=auth/login">前往后台登录</a> · <a href="index.php?r=home/index">返回前台</a></p>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

ini_set('display_errors', '1');

$notes  = security_notes();
$dbInfo = db_check();

$allGreen = !$lint['errors'] && !$sqlScan['bad'] && !$routes['missing'];
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>系统自检 - 陪玩点单系统</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #f4f5f9; color: #23222b; font-family: -apple-system, "Segoe UI", "Microsoft YaHei", sans-serif; font-size: 14px; line-height: 1.65; }
  .wrap { max-width: 980px; margin: 0 auto; padding: 30px 18px 70px; }
  h1 { font-size: 21px; margin: 0 0 4px; }
  h2 { font-size: 16px; margin: 0 0 12px; }
  .card { background: #fff; border: 1px solid #eceaf3; border-radius: 14px; padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(29,22,71,.05); }
  .muted { color: #8b8a97; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #f0eef7; vertical-align: top; }
  .ok { color: #16a34a; font-weight: 600; }
  .bad { color: #e11d48; font-weight: 600; }
  .warn { color: #d97706; font-weight: 600; }
  .alert { padding: 11px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 13.5px; }
  .alert-ok { background: #e9f9ef; color: #14804a; }
  .alert-error { background: #fdecef; color: #b3123a; }
  .alert-warn { background: #fff6e6; color: #96590a; }
  .alert-info { background: #eaf1fe; color: #1c4ed8; }
  code { background: #f4f3fa; padding: 1px 5px; border-radius: 5px; font-size: 12.5px; word-break: break-all; }
  ul { margin: 6px 0 0; padding-left: 20px; }
  pre { background: #f7f6fc; border-radius: 8px; padding: 8px 10px; font-size: 12px; white-space: pre-wrap; margin: 4px 0 0; }
  .big { font-size: 26px; font-weight: 700; }
</style>
</head>
<body>
<div class="wrap">
  <h1>系统自检</h1>
  <p class="muted">只读检查：PHP 语法、SQL 占位符、路由完整性、运行环境、数据库结构、上线安全项。时间 <?= h(date('Y-m-d H:i:s')) ?></p>

  <div class="card">
    <?php if ($allGreen): ?>
      <div class="alert alert-ok"><strong>代码静态检查全部通过</strong>：语法、SQL 占位符、路由三项均未发现问题。</div>
    <?php else: ?>
      <div class="alert alert-error"><strong>发现问题</strong>：请查看下面标记为红色的条目。</div>
    <?php endif; ?>
    <p class="big <?= $allGreen ? 'ok' : 'bad' ?>"><?= $allGreen ? 'PASS' : 'FAIL' ?></p>
    <p class="muted">
      语法：<?= count($lint['errors']) ?> 处错误 ·
      SQL 占位符：核对 <?= (int) $sqlScan['checked'] ?> 条，问题 <?= count($sqlScan['bad']) ?> 条 ·
      路由：核对 <?= (int) $routes['found'] ?> 处，缺失 <?= count($routes['missing']) ?> 处
    </p>
  </div>

  <div class="card">
    <h2>一、PHP 语法检查（等价 php -l）</h2>
    <p>已解析 <strong><?= (int) $lint['files'] ?></strong> 个 PHP 文件，耗时 <?= (int) $lint['ms'] ?> ms。</p>
    <?php if (!$lint['errors']): ?>
      <div class="alert alert-ok">全部文件语法正确，没有解析错误。</div>
    <?php else: ?>
      <table>
        <thead><tr><th>文件</th><th style="width:60px">行号</th><th>错误信息</th></tr></thead>
        <tbody>
        <?php foreach ($lint['errors'] as $err): ?>
          <tr>
            <td><code><?= h($err['file']) ?></code></td>
            <td class="bad"><?= (int) $err['line'] ?></td>
            <td><?= h($err['msg']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>二、SQL 占位符静态核对</h2>
    <p class="muted">逐个提取 <code>Db::one/all/value/query</code> 的 SQL 与绑定参数，比对 <code>?</code> 个数与参数个数。SQL 用拼接或参数用变量的情况会跳过（见下方清单）。</p>
    <?php if (!$sqlScan['bad']): ?>
      <div class="alert alert-ok">已核对的 <?= (int) $sqlScan['checked'] ?> 条 SQL 占位符与参数数量全部匹配。</div>
    <?php else: ?>
      <table>
        <thead><tr><th>位置</th><th style="width:70px">占位符</th><th style="width:70px">参数</th><th>问题</th></tr></thead>
        <tbody>
        <?php foreach ($sqlScan['bad'] as $item): ?>
          <tr>
            <td><code><?= h($item['file']) ?>:<?= (int) $item['line'] ?></code></td>
            <td class="bad"><?= (int) $item['marks'] ?></td>
            <td class="bad"><?= (int) $item['params'] ?></td>
            <td>
              <?= h($item['note']) ?>
              <pre><?= h(substr($item['sql'], 0, 300)) ?></pre>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <?php if (!empty($sqlScan['skipped_list'])): ?>
      <p class="muted" style="margin-top:12px">跳过 <?= count($sqlScan['skipped_list']) ?> 处（无法静态判定，需人工或运行验证）：</p>
      <ul>
        <?php foreach (array_slice($sqlScan['skipped_list'], 0, 20) as $line): ?>
          <li class="muted"><code><?= h($line) ?></code></li>
        <?php endforeach; ?>
        <?php if (count($sqlScan['skipped_list']) > 20): ?>
          <li class="muted">……另有 <?= count($sqlScan['skipped_list']) - 20 ?> 处</li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>三、路由完整性核对</h2>
    <p class="muted">扫描 <code>url()</code> / <code>admin_url()</code> / <code>redirect_route()</code> / <code>redirect_back()</code> 里的路由字符串，检查对应控制器方法是否存在。</p>
    <?php if (!$routes['missing']): ?>
      <div class="alert alert-ok">共核对 <?= (int) $routes['found'] ?> 处路由链接，全部能对应到已存在的控制器方法。</div>
    <?php else: ?>
      <table>
        <thead><tr><th>位置</th><th>路由</th><th>原因</th></tr></thead>
        <tbody>
        <?php foreach ($routes['missing'] as $item): ?>
          <tr>
            <td><code><?= h($item['file']) ?>:<?= (int) $item['line'] ?></code></td>
            <td class="bad"><?= h($item['route']) ?></td>
            <td><?= h($item['reason']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>四、运行环境</h2>
    <table>
      <?php foreach ($envList as $c): ?>
        <tr>
          <td><?= h($c[0]) ?></td>
          <td class="<?= $c[1] ? 'ok' : 'bad' ?>" style="width:80px"><?= $c[1] ? '通过' : '不通过' ?></td>
          <td class="muted"><?= h((string) $c[2]) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2>五、数据库与数据表</h2>
    <p><?= $dbInfo['ok'] ? '<span class="ok">✓</span>' : '<span class="bad">✗</span>' ?> <?= h($dbInfo['msg']) ?></p>
    <?php if (!empty($dbInfo['missing'])): ?>
      <div class="alert alert-error">缺少数据表：<?= h(implode('、', $dbInfo['missing'])) ?>，请重新执行 install.php。</div>
    <?php endif; ?>
    <?php if (!empty($dbInfo['columns'])): ?>
      <div class="alert alert-error">字段缺失：<?= h(implode('、', $dbInfo['columns'])) ?>，请对照 sql/schema.sql 补表。</div>
    <?php endif; ?>
    <?php if (!empty($dbInfo['schema_actions'])): ?>
      <div class="alert alert-warn">
        与 sql/schema.sql 对比后发现结构差异（<a href="install.php">重新访问 install.php</a> 会自动补齐，保留数据）：
        <ul>
          <?php foreach ($dbInfo['schema_actions'] as $line): ?>
            <li><?= h($line) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if (!empty($dbInfo['tables'])): ?>
      <p class="muted">已存在 <?= count($dbInfo['tables']) ?> 张表：<?= h(implode('、', $dbInfo['tables'])) ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>六、上线前安全项</h2>
    <?php if (!$notes): ?>
      <div class="alert alert-ok">没有发现需要处理的安全项。</div>
    <?php else: ?>
      <?php foreach ($notes as $n): ?>
        <div class="alert alert-<?= $n[0] === 'warn' ? 'warn' : ($n[0] === 'danger' ? 'error' : 'info') ?>"><?= h($n[1]) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>七、下一步</h2>
    <ul>
      <li><a href="install.php">install.php</a> 安装（已安装则忽略）</li>
      <li><a href="selftest.php">selftest.php</a> 跑资金链路冒烟测试（需先登录后台）</li>
      <li><a href="demo.php">demo.php</a> 生成演示数据（需先登录后台）</li>
      <li><a href="index.php?r=home/index">前台首页</a> · <a href="<?= h(admin_dir_name()) ?>/index.php?r=dashboard/index">管理后台</a></li>
      <li>确认无误后删除 <code>install.php</code>、<code>check.php</code>、<code>selftest.php</code>、<code>demo.php</code></li>
    </ul>
  </div>
</div>
</body>
</html>
