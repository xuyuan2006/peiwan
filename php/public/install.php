<?php
/**
 * 安装向导（浏览器访问 /install.php）
 *  - 环境自检
 *  - 建库建表（执行 sql/schema.sql）
 *  - 创建管理员账号
 *  - 写入 config/config.php
 *
 * 安装完成后建议删除或重命名本文件。
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_FILE', ROOT_DIR . '/config/config.php');
define('SCHEMA_FILE', ROOT_DIR . '/sql/schema.sql');

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

function p(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

$TABLE_LIST = [
    'admin_logs', 'settings', 'withdraw_orders', 'recharge_orders', 'wallet_transactions',
    'user_wallets', 'order_reviews', 'order_logs', 'orders', 'companion_skills',
    'games', 'companion_profiles', 'users',
];

// ---------------------------------------------------------------- 现有配置
$config = is_file(CONFIG_FILE) ? (include CONFIG_FILE) : [];
if (!is_array($config)) {
    $config = [];
}
$db = array_merge(
    ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'peiwang', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'],
    (array) ($config['db'] ?? [])
);
$appCfg = array_merge(
    ['name' => '陪玩点单平台', 'url' => '', 'debug' => true, 'timezone' => 'Asia/Shanghai'],
    (array) ($config['app'] ?? [])
);
$payCfg = array_merge(
    ['channel' => 'manual', 'epay' => ['api_url' => '', 'pid' => '', 'key' => '']],
    (array) ($config['pay'] ?? [])
);

// ---------------------------------------------------------------- 是否已安装
$installed   = false;
$checkError  = '';
try {
    $probe = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], (int) $db['port'], $db['name'], $db['charset']),
        (string) $db['user'],
        (string) $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $installed = (bool) $probe->query("SHOW TABLES LIKE 'users'")->fetchAll();
} catch (Throwable $e) {
    $checkError = $e->getMessage();
}

// ---------------------------------------------------------------- 环境检测
$uploadDir = ROOT_DIR . '/public/uploads';
$checks = [
    ['PHP 版本 ≥ 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION],
    ['PDO MySQL 扩展', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? '已开启' : '未开启'],
    ['mbstring 扩展', extension_loaded('mbstring'), extension_loaded('mbstring') ? '已开启' : '未开启'],
    ['config 目录可写', is_writable(ROOT_DIR . '/config'), is_writable(ROOT_DIR . '/config') ? '可写' : '不可写'],
    ['public/uploads 可写', is_dir($uploadDir) ? is_writable($uploadDir) : is_writable(ROOT_DIR . '/public'), ''],
    ['sql/schema.sql 存在', is_file(SCHEMA_FILE), ''],
    ['数据库连接', $installed, $checkError !== '' ? $checkError : ($installed ? '已连接并检测到数据表' : '尚未安装')],
];
$envOk = true;
foreach (array_slice($checks, 0, 6) as $c) {
    if (!$c[1]) {
        $envOk = false;
    }
}

// ---------------------------------------------------------------- 执行安装
$done        = false;
$errors      = [];
$configText  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && p('action') === 'install') {
    $dbHost = p('db_host', '127.0.0.1');
    $dbPort = (int) p('db_port', '3306');
    $dbName = p('db_name', 'peiwang');
    $dbUser = p('db_user', 'root');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $siteName = p('site_name', '陪玩点单平台');
    $adminUser = p('admin_user', 'admin');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $adminNick = p('admin_nick', '超级管理员');
    $reinstall = p('reinstall') === '1';
    $siteUrl   = rtrim(p('site_url'), '/');
    // 后台入口目录名（安装时可自定义，避免被人直接扫 /admin/）
    $adminPath = strtolower(trim(p('admin_path', 'admin'), "/ \t"));
    $warnings  = [];

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $errors[] = '请填写完整的数据库信息';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $adminUser)) {
        $errors[] = '管理员账号需为 3~20 位字母、数字或下划线';
    }

    // 强密码校验：≥8 位、必须同时含字母与数字、且不是常见弱口令
    $weakPasswords = [
        '123456', '12345678', '123456789', '1234567890', 'password', 'passw0rd',
        'admin', 'admin123', 'admin888', 'adminadmin', '888888', '666666', '111111',
        'qwerty', 'qwerty123', 'abc123', 'a123456', 'peiwang', 'peiwang123', 'test1234',
    ];
    if (strlen($adminPass) < 8) {
        $errors[] = '管理员密码至少 8 位（建议字母 + 数字 + 符号混用）';
    } elseif (!preg_match('/[A-Za-z]/', $adminPass) || !preg_match('/\d/', $adminPass)) {
        $errors[] = '管理员密码必须同时包含字母和数字';
    } elseif (in_array(strtolower($adminPass), $weakPasswords, true) || strtolower($adminPass) === strtolower($adminUser)) {
        $errors[] = '这个密码太常见（或与账号相同），换一个更安全的吧';
    }

    if (!preg_match('/^[a-z0-9_\-]{3,30}$/', $adminPath)) {
        $errors[] = '后台入口只能是 3~30 位小写字母 / 数字 / 下划线 / 短横线，例如 manage8x';
    }
    if (!is_file(SCHEMA_FILE)) {
        $errors[] = 'sql/schema.sql 不存在，请重新上传完整程序';
    }

    if (!$errors) {
        try {
            // 1) 连接 MySQL 服务并确保数据库存在
            $server = new PDO(
                sprintf('mysql:host=%s;port=%d;charset=%s', $dbHost, $dbPort, $dbCharset = 'utf8mb4'),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbName) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

            // 2) 连接目标库
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            if ($reinstall) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($TABLE_LIST as $table) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }

            // 3) 建表 + 初始数据
            $sql   = ltrim((string) file_get_contents(SCHEMA_FILE), "\xEF\xBB\xBF"); // 去掉可能的 BOM
            $lines = [];
            foreach (preg_split('/\r?\n/', $sql) as $line) {
                if (strpos(ltrim($line), '--') === 0) {
                    continue; // 去掉注释行
                }
                $lines[] = $line;
            }
            $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))), function ($s) {
                return $s !== '';
            });

            $syncActions = [];
            foreach ($statements as $statement) {
                if ($installed) {
                    // 升级（保留数据）模式：只补「缺失的设置项」（INSERT IGNORE），
                    // 跳过游戏等初始数据，避免每次升级都重复插入一批游戏
                    $isInsert       = stripos(ltrim($statement), 'INSERT') === 0;
                    $isSettingsSeed = strpos($statement, 'INSERT IGNORE INTO `settings`') !== false;
                    if ($isInsert && !$isSettingsSeed) {
                        continue;
                    }
                }
                $pdo->exec($statement);
            }

            // 3.1) 结构同步：补齐旧库缺失的字段与索引（幂等）
            require_once ROOT_DIR . '/app/SchemaSync.php';
            try {
                $syncActions = \App\SchemaSync::sync($pdo, SCHEMA_FILE);
            } catch (Throwable $e) {
                $errors[] = '结构同步失败（可手动对照 sql/schema.sql 补字段）：' . $e->getMessage();
            }

            // 4) 创建/更新管理员
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$adminUser]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);

            if ($exists) {
                $upd = $pdo->prepare("UPDATE users SET password = ?, role = 'admin', status = 1, nickname = ?, updated_at = ? WHERE id = ?");
                $upd->execute([$hash, $adminNick !== '' ? $adminNick : '超级管理员', date('Y-m-d H:i:s'), $exists['id']]);
                $adminId = (int) $exists['id'];
            } else {
                $ins = $pdo->prepare("INSERT INTO users (username, password, nickname, role, status, created_at, updated_at) VALUES (?, ?, ?, 'admin', 1, ?, ?)");
                $now = date('Y-m-d H:i:s');
                $ins->execute([$adminUser, $hash, $adminNick !== '' ? $adminNick : '超级管理员', $now, $now]);
                $adminId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at) VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)')
                ->execute([$adminId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

            // 5) 站点名称写入设置表
            if ($siteName !== '') {
                $pdo->prepare("INSERT INTO settings (`k`, `v`, `updated_at`) VALUES ('site_name', ?, ?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)")
                    ->execute([$siteName, date('Y-m-d H:i:s')]);
            }

            // 6) 后台入口改名（可选，防止被扫描默认后台）
            // 只有确认配置文件能写，才动目录：避免"目录改了、配置没改"导致后台进不去
            $oldPath        = (string) ($config['security']['admin_path'] ?? 'admin');
            $finalPath      = $oldPath;
            $configWritable = is_file(CONFIG_FILE) ? is_writable(CONFIG_FILE) : is_writable(ROOT_DIR . '/config');

            if ($adminPath !== $oldPath) {
                $src = ROOT_DIR . '/public/' . $oldPath;
                $dst = ROOT_DIR . '/public/' . $adminPath;

                if (!$configWritable) {
                    $warnings[] = '目录 config 不可写，无法保存新的后台入口，入口保持 /' . $oldPath . '/';
                } elseif (is_dir($dst)) {
                    $warnings[] = '目录 public/' . $adminPath . ' 已存在，后台入口仍保持 /' . $oldPath . '/';
                } elseif (!is_dir($src)) {
                    $warnings[] = '没找到 public/' . $oldPath . ' 目录，后台入口仍保持 /' . $oldPath . '/';
                } elseif (@rename($src, $dst)) {
                    $finalPath = $adminPath;
                } else {
                    $warnings[] = '后台目录改名失败（public 目录可能不可写）：可手动把 public/' . $oldPath . ' 改名为 public/' . $adminPath
                        . '，并在 config/config.php 里把 security.admin_path 改成 ' . $adminPath;
                }
            }

            // 7) 生成配置文件
            $newConfig = [
                'app' => [
                    'name'     => $siteName !== '' ? $siteName : '陪玩点单平台',
                    'url'      => $siteUrl,
                    'debug'    => false,
                    'timezone' => 'Asia/Shanghai',
                ],
                'db' => [
                    'host'    => $dbHost,
                    'port'    => $dbPort,
                    'name'    => $dbName,
                    'user'    => $dbUser,
                    'pass'    => $dbPass,
                    'charset' => 'utf8mb4',
                ],
                'pay' => $payCfg,
                'security' => [
                    // 后台入口目录名：/admin/ 换成别的名字，减少被扫的风险
                    'admin_path' => $finalPath,
                ],
            ];
            $configText = "<?php\n/**\n * 由 install.php 于 " . date('Y-m-d H:i:s') . " 生成\n */\nreturn " . var_export($newConfig, true) . ";\n";

            if (@file_put_contents(CONFIG_FILE, $configText) === false) {
                // 配置没写成功：把后台目录改回去，并让展示的配置也用旧入口，保持两边一致
                if ($finalPath !== $oldPath && is_dir(ROOT_DIR . '/public/' . $finalPath)) {
                    @rename(ROOT_DIR . '/public/' . $finalPath, ROOT_DIR . '/public/' . $oldPath);
                }
                $newConfig['security']['admin_path'] = $oldPath;
                $configText = "<?php\n/**\n * 由 install.php 于 " . date('Y-m-d H:i:s') . " 生成\n */\nreturn " . var_export($newConfig, true) . ";\n";
                $errors[] = '配置文件写入失败，请手动把下面的内容保存为 config/config.php：';
            } else {
                $done = true;
                $_SESSION['install_admin'] = $adminUser;
                $_SESSION['install_admin_path'] = $finalPath;
            }
        } catch (Throwable $e) {
            $errors[] = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装向导 - 陪玩点单系统</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #f4f5f9; color: #23222b; font-family: -apple-system, "Segoe UI", "Microsoft YaHei", sans-serif; font-size: 14px; line-height: 1.7; }
  .wrap { max-width: 860px; margin: 0 auto; padding: 32px 18px 70px; }
  h1 { font-size: 22px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; }
  .card { background: #fff; border: 1px solid #eceaf3; border-radius: 14px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(29,22,71,.05); }
  .muted { color: #8b8a97; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #f0eef7; }
  .ok { color: #16a34a; font-weight: 600; }
  .bad { color: #e11d48; font-weight: 600; }
  .row { display: flex; gap: 14px; flex-wrap: wrap; }
  .row > div { flex: 1; min-width: 220px; }
  label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; }
  input[type=text], input[type=password], input[type=number] { width: 100%; padding: 9px 12px; border: 1px solid #e2e0ee; border-radius: 10px; background: #fbfbfe; font-size: 14px; }
  .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; border: none; background: #6c5ce7; color: #fff; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; }
  .btn:hover { background: #5a4bd1; color: #fff; }
  .btn-ghost { background: #fff; color: #55535f; border: 1px solid #eceaf3; }
  .alert { padding: 11px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 13.5px; }
  .alert-error { background: #fdecef; color: #b3123a; }
  .alert-ok { background: #e9f9ef; color: #14804a; }
  .alert-warn { background: #fff6e6; color: #96590a; }
  textarea { width: 100%; height: 220px; font-family: Consolas, Monaco, monospace; font-size: 12.5px; border: 1px solid #e2e0ee; border-radius: 10px; padding: 10px; }
  code { background: #f4f3fa; padding: 1px 5px; border-radius: 5px; }
  .form-row { margin-bottom: 14px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>陪玩点单系统 · 安装向导</h1>
  <p class="muted">按顺序完成：环境自检 → 填写数据库与管理员信息 → 安装完成</p>

<?php if ($done): ?>
  <?php $__ap = (string) ($_SESSION['install_admin_path'] ?? 'admin'); ?>
  <div class="card">
    <div class="alert alert-ok">
      🎉 安装成功！管理员账号：<code><?= h((string) ($_SESSION['install_admin'] ?? 'admin')) ?></code>
      —— 密码就是你刚才设置的那个（系统只保存加密串，无法显示，请自己记牢）
    </div>
    <?php if (!empty($warnings)): ?>
      <div class="alert alert-warn">
        <?php foreach ($warnings as $__w): ?><div><?= h($__w) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($syncActions)): ?>
      <h2>结构同步</h2>
      <ul>
        <?php foreach ($syncActions as $action): ?>
          <li class="muted"><?= h($action) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p>
      <strong>后台入口</strong>：<code>/<?= h($__ap) ?>/index.php</code>
      <?php if ($__ap !== 'admin'): ?>
        —— 默认的 <code>/admin/</code> 已经不可用（会返回 404），请把新地址收藏起来。
      <?php else: ?>
        —— 你保留了默认入口，建议尽快改成别的名字（见下方说明）。
      <?php endif; ?>
    </p>
    <p>为了安全，请立即删除或重命名 <code>install.php</code>，然后进入后台修改平台设置。</p>
    <p>
      <a class="btn" href="index.php?r=home/index">进入前台</a>
      <a class="btn btn-ghost" href="<?= h($__ap) ?>/index.php?r=dashboard/index">进入后台</a>
      <a class="btn btn-ghost" href="check.php">运行自检</a>
    </p>
    <p class="muted">
      完整后台地址：<code><?= h((isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/')) ?>/<?= h($__ap) ?>/index.php</code>
    </p>
    <p class="muted">
      想事后改后台入口：把 <code>public/<?= h($__ap) ?></code> 改名为新名字，并把 <code>config/config.php</code> 里的
      <code>'admin_path' =&gt; '<?= h($__ap) ?>'</code> 改成同一个新名字即可（两处必须一致）。
    </p>
  </div>
<?php elseif ($installed && p('action') !== 'install'): ?>
  <?php $__ap0 = (string) ($config['security']['admin_path'] ?? 'admin'); ?>
  <div class="card">
    <div class="alert alert-warn">检测到系统已经安装过了。</div>
    <p class="muted">当前数据库：<code><?= h($db['name']) ?>@<?= h($db['host']) ?></code></p>
    <p>
      <a class="btn" href="index.php?r=home/index">进入前台</a>
      <a class="btn btn-ghost" href="<?= h($__ap0) ?>/index.php?r=dashboard/index">进入后台</a>
    </p>
    <hr style="border:none;border-top:1px solid #f0eef7;margin:18px 0">
    <h2>重新安装</h2>
    <p class="muted">重新安装可以选择保留数据（仅重置管理员密码）或清空全部数据重建表结构。<strong>清空不可恢复，请先备份。</strong></p>
    <form method="post">
      <input type="hidden" name="action" value="install">
      <input type="hidden" name="db_host" value="<?= h($db['host']) ?>">
      <input type="hidden" name="db_port" value="<?= (int) $db['port'] ?>">
      <input type="hidden" name="db_name" value="<?= h($db['name']) ?>">
      <input type="hidden" name="db_user" value="<?= h($db['user']) ?>">
      <div class="form-row">
        <label>数据库密码</label>
        <input type="password" name="db_pass" value="<?= h($db['pass']) ?>">
      </div>
      <div class="row">
        <div class="form-row">
          <label>管理员账号</label>
          <input type="text" name="admin_user" value="admin" required>
        </div>
        <div class="form-row">
          <label>管理员新密码</label>
          <input type="password" name="admin_pass" id="admin-pass" placeholder="至少 8 位，含字母和数字" required>
        </div>
      </div>
      <div class="form-row">
        <label>站点名称</label>
        <input type="text" name="site_name" value="<?= h($appCfg['name']) ?>">
      </div>
      <div class="form-row">
        <label>后台入口目录名（建议改掉默认的 admin，防止被扫描）</label>
        <input type="text" name="admin_path" value="<?= h((string) ($config['security']['admin_path'] ?? 'admin')) ?>" placeholder="例如 manage8x">
        <p class="muted">只允许小写字母 / 数字 / 下划线 / 短横线；填别的名字会自动重命名 public 下的后台目录，之后 <code>/admin/</code> 会返回 404。</p>
      </div>
      <div class="form-row">
        <label><input type="checkbox" name="reinstall" value="1"> 清空全部数据并重建表结构（危险）</label>
      </div>
      <p><button class="btn btn-ghost" type="button" id="gen-pass">随机生成强密码</button></p>
      <button class="btn" type="submit">执行</button>
    </form>
    <script>
      (function () {
        var btn = document.getElementById('gen-pass');
        var input = document.getElementById('admin-pass');
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
          var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
          var pass = '';
          var i;
          if (window.crypto && window.crypto.getRandomValues) {
            var buf = new Uint32Array(14);
            window.crypto.getRandomValues(buf);
            for (i = 0; i < 14; i++) { pass += chars.charAt(buf[i] % chars.length); }
          } else {
            for (i = 0; i < 14; i++) { pass += chars.charAt(Math.floor(Math.random() * chars.length)); }
          }
          pass = pass.slice(0, 10) + '8' + pass.slice(10); // 保证含数字
          input.type = 'text';
          input.value = pass;
          if (window.navigator.clipboard) { window.navigator.clipboard.writeText(pass); }
          window.alert('已生成并尝试复制到剪贴板：\n' + pass + '\n\n请保存好，安装后系统不会显示它。');
        });
      })();
    </script>
  </div>
<?php else: ?>
  <div class="card">
    <h2>一、环境自检</h2>
    <table>
      <?php foreach ($checks as $c): ?>
        <tr>
          <td><?= h($c[0]) ?></td>
          <td class="<?= $c[1] ? 'ok' : 'bad' ?>"><?= $c[1] ? '通过' : '不通过' ?></td>
          <td class="muted"><?= h($c[2]) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$envOk): ?>
      <div class="alert alert-error" style="margin-top:12px">环境检测未全部通过，请先解决上面标记为“不通过”的项目。</div>
    <?php else: ?>
      <p class="muted" style="margin-top:12px">环境检测通过，可以开始安装。</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>二、数据库与管理员</h2>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= h($err) ?></div>
    <?php endforeach; ?>

    <?php if ($configText !== ''): ?>
      <div class="form-row">
        <label>请把下面内容保存为 config/config.php</label>
        <textarea readonly><?= h($configText) ?></textarea>
      </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="action" value="install">

      <div class="row">
        <div class="form-row">
          <label>数据库地址</label>
          <input type="text" name="db_host" value="<?= h(p('db_host', (string) $db['host'])) ?>" required>
        </div>
        <div class="form-row">
          <label>端口</label>
          <input type="number" name="db_port" value="<?= h(p('db_port', (string) $db['port'])) ?>" required>
        </div>
      </div>

      <div class="row">
        <div class="form-row">
          <label>数据库名（不存在会自动创建）</label>
          <input type="text" name="db_name" value="<?= h(p('db_name', (string) $db['name'])) ?>" required>
        </div>
        <div class="form-row">
          <label>数据库用户名</label>
          <input type="text" name="db_user" value="<?= h(p('db_user', (string) $db['user'])) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <label>数据库密码</label>
        <input type="password" name="db_pass" value="<?= h((string) $db['pass']) ?>">
      </div>

      <div class="form-row">
        <label>站点名称</label>
        <input type="text" name="site_name" value="<?= h(p('site_name', (string) $appCfg['name'])) ?>">
      </div>

      <div class="form-row">
        <label>站点根网址（选填，例如 https://peiwang.com，留空自动识别）</label>
        <input type="text" name="site_url" value="<?= h(p('site_url', (string) $appCfg['url'])) ?>">
      </div>

      <div class="row">
        <div class="form-row">
          <label>管理员账号</label>
          <input type="text" name="admin_user" value="<?= h(p('admin_user', 'admin')) ?>" required>
        </div>
        <div class="form-row">
          <label>管理员密码（至少 8 位，必须含字母和数字）</label>
          <input type="password" name="admin_pass" id="admin-pass" placeholder="例如 Wd8k2mQz" required>
        </div>
      </div>

      <div class="form-row">
        <label>管理员昵称</label>
        <input type="text" name="admin_nick" value="<?= h(p('admin_nick', '超级管理员')) ?>">
      </div>

      <div class="form-row">
        <label>后台入口目录名（<strong>建议改掉默认的 admin</strong>，防止被人扫描后台）</label>
        <input type="text" name="admin_path" id="admin-path" value="<?= h(p('admin_path', 'admin')) ?>" placeholder="例如 manage8x">
        <p class="muted">
          只用小写字母 / 数字 / 下划线 / 短横线。填了别的名字会自动把 <code>public/admin</code> 改名为
          <code>public/该名字</code>，之后 <code>/admin/</code> 会返回 404，只有新地址能进后台。
        </p>
      </div>

      <p>
        <button class="btn btn-ghost" type="button" id="gen-pass">随机生成强密码</button>
        <button class="btn btn-ghost" type="button" id="gen-path">随机生成后台入口</button>
      </p>
      <button class="btn" type="submit" <?= $envOk ? '' : 'disabled style="opacity:.5"' ?>>开始安装</button>
      <p class="muted" style="margin-top:10px">安装会创建 20 张数据表并写入初始游戏与平台设置。</p>
    </form>
    <script>
      (function () {
        function random(n, chars) {
          var out = '';
          var i;
          if (window.crypto && window.crypto.getRandomValues) {
            var buf = new Uint32Array(n);
            window.crypto.getRandomValues(buf);
            for (i = 0; i < n; i++) { out += chars.charAt(buf[i] % chars.length); }
          } else {
            for (i = 0; i < n; i++) { out += chars.charAt(Math.floor(Math.random() * chars.length)); }
          }
          return out;
        }

        var passBtn = document.getElementById('gen-pass');
        var passInput = document.getElementById('admin-pass');
        if (passBtn && passInput) {
          passBtn.addEventListener('click', function () {
            var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            var pass = random(14, chars);
            pass = pass.slice(0, 9) + '7' + pass.slice(9); // 保证含数字
            passInput.type = 'text';
            passInput.value = pass;
            if (window.navigator.clipboard) { window.navigator.clipboard.writeText(pass); }
            window.alert('已生成并尝试复制到剪贴板：\n' + pass + '\n\n请保存好，安装后系统不会显示它。');
          });
        }

        var pathBtn = document.getElementById('gen-path');
        var pathInput = document.getElementById('admin-path');
        if (pathBtn && pathInput) {
          pathBtn.addEventListener('click', function () {
            pathInput.value = 'manage' + random(6, 'abcdefghijkmnpqrstuvwxyz23456789');
          });
        }
      })();
    </script>
  </div>
<?php endif; ?>
</div>
</body>
</html>
