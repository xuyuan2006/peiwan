<?php
/**
 * 演示数据（浏览器访问 /demo.php，需先用管理员登录后台）
 *
 * 一键生成一套可直接体验/演示的数据：
 *   · 3 位已通过审核的陪玩（不同游戏、不同价位、不同服务类型、已上架技能）
 *   · 1 位待审核陪玩（用于演示后台入驻审核）
 *   · 2 位买家（钱包有余额，可直接下单体验）
 *   · 4 笔不同状态的订单：待支付 / 待接单 / 服务中 / 已完成（含评价）
 *   · 1 笔待确认充值单、1 笔待审核提现单（用于演示后台审核）
 *
 * 所有账号用户名都以 demo_ 开头，可随时在本页「清除演示数据」一键删除。
 * 正式上线前请清除演示数据并删除本文件。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Auth;
use App\CompanionService;
use App\Db;
use App\FinanceService;
use App\OrderService;
use App\Setting;
use App\Wallet;

if (!Db::tableExists('users')) {
    header('Location: ' . base_url() . '/install.php');
    exit;
}
if (!Auth::isAdmin()) {
    flash('warning', '请先用管理员账号登录后台');
    redirect(admin_url('auth/login'));
}

if (!function_exists('h')) {
    function h($v): string
    {
        return e($v);
    }
}

/** 演示账号用户名白名单（不按前缀匹配，避免误删真实注册用户） */
function demo_usernames(): array
{
    return ['demo_comp1', 'demo_comp2', 'demo_comp3', 'demo_comp_pending', 'demo_buyer1', 'demo_buyer2'];
}

/** 现有演示账号 ID */
function demo_user_ids(): array
{
    $names = demo_usernames();
    $holders = implode(',', array_fill(0, count($names), '?'));
    $rows = Db::all("SELECT id FROM users WHERE username IN ($holders)", $names);
    return array_map('intval', array_column($rows, 'id'));
}

function demo_create_user(string $username, string $nickname, int $gender, string $phone, string $role = 'user'): int
{
    $userId = Db::insert('users', [
        'username'   => $username,
        'password'   => password_hash('demo123456', PASSWORD_DEFAULT),
        'nickname'   => $nickname,
        'phone'      => $phone,
        'gender'     => $gender,
        'role'       => $role,
        'status'     => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Db::query(
        'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
         VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
        [$userId, now(), now()]
    );
    return $userId;
}

/** 生成演示数据，返回说明数组 */
function demo_seed(): array
{
    $games = Db::all('SELECT id, name FROM games WHERE status = 1 ORDER BY sort DESC, id ASC LIMIT 4');
    if (!$games) {
        throw new \RuntimeException('还没有可用的游戏，请先到「游戏管理」添加');
    }
    $gameId = function (int $index) use ($games): int {
        return (int) $games[min($index, count($games) - 1)]['id'];
    };

    $created = [];
    $adminId = Auth::id();

    // ---------------- 陪玩 ----------------
    $companions = [
        [
            'username' => 'demo_comp1', 'nickname' => '小鹿', 'gender' => 2, 'phone' => '13700000001',
            'city' => '上海', 'tags' => '声音甜,技术好,带飞',
            'intro' => '王者荣耀 50 星，主玩打野，晚上 8 点后在线，可以带新人上分，也可以单纯开黑聊天。',
            'skills' => [
                ['game' => 0, 'title' => '排位上分陪练', 'type' => 'hour', 'rank' => '王者 50 星', 'price' => 66, 'desc' => '一小时起，语音开麦，边打边讲思路。'],
                ['game' => 1, 'title' => '双排上分（按局）', 'type' => 'round', 'rank' => '无敌战神', 'price' => 25, 'desc' => '一局一结，稳上分。'],
            ],
        ],
        [
            'username' => 'demo_comp2', 'nickname' => '阿凯', 'gender' => 1, 'phone' => '13700000002',
            'city' => '广州', 'tags' => '上分稳,幽默,指挥',
            'intro' => '前职业青训队选手，擅长指挥和复盘，专治各种卡分，游戏里话不多但很稳。',
            'skills' => [
                ['game' => 0, 'title' => '大师段位冲分陪练', 'type' => 'hour', 'rank' => '巅峰 2000+', 'price' => 88, 'desc' => '高段位冲分，支持双排/三排。'],
                ['game' => 2, 'title' => '定级赛包赢套餐', 'type' => 'custom', 'rank' => '钻石以上', 'price' => 120, 'desc' => '一口价，打完为止，未达段位可退款。'],
            ],
        ],
        [
            'username' => 'demo_comp3', 'nickname' => '小满', 'gender' => 2, 'phone' => '13700000003',
            'city' => '成都', 'tags' => '有耐心,小姐姐,和平精英',
            'intro' => '和平精英四排固定位，擅长报点和架枪，脾气好，新人也可以放心约。',
            'skills' => [
                ['game' => 1, 'title' => '四排上分陪玩', 'type' => 'hour', 'rank' => '皇冠', 'price' => 45, 'desc' => '一小时起，可带三人。'],
            ],
        ],
    ];

    $companionIds = [];
    $skillIds     = [];
    foreach ($companions as $c) {
        $uid = demo_create_user($c['username'], $c['nickname'], $c['gender'], $c['phone']);
        CompanionService::apply($uid, [
            'real_name' => $c['nickname'],
            'phone'     => $c['phone'],
            'gender'    => $c['gender'],
            'city'      => $c['city'],
            'intro'     => $c['intro'],
            'tags'      => $c['tags'],
        ]);
        CompanionService::audit($uid, 1, '演示数据自动通过');
        foreach ($c['skills'] as $s) {
            $skillIds[] = CompanionService::saveSkill($uid, [
                'game_id'      => $gameId((int) $s['game']),
                'title'        => $s['title'],
                'service_type' => $s['type'],
                'rank_level'   => $s['rank'],
                'price'        => $s['price'],
                'description'  => $s['desc'],
            ]);
        }
        $companionIds[] = $uid;
    }
    $created[] = '已创建 ' . count($companions) . ' 位陪玩（含 ' . count($skillIds) . ' 个服务项目）';

    // ---------------- 待审核陪玩 ----------------
    $pendingId = demo_create_user('demo_comp_pending', '待审核的小新', 1, '13700000004');
    CompanionService::apply($pendingId, [
        'real_name' => '小新', 'phone' => '13700000004', 'gender' => 1, 'city' => '杭州',
        'intro' => '刚注册想接单，熟悉英雄联盟和云顶之弈，时间充裕。', 'tags' => '新人,时间多',
    ]);
    $created[] = '已创建 1 位待审核陪玩（demo_comp_pending，可到「陪玩管理」审核）';

    // ---------------- 买家 ----------------
    $buyer1 = demo_create_user('demo_buyer1', '爱打游戏的小王', 1, '13600000001');
    $buyer2 = demo_create_user('demo_buyer2', '夜猫子小李', 2, '13600000002');
    FinanceService::adjustBalance($buyer1, 500, 'add', $adminId, '演示数据：初始余额');
    FinanceService::adjustBalance($buyer2, 300, 'add', $adminId, '演示数据：初始余额');
    $created[] = '已创建 2 位买家（demo_buyer1 余额 500 / demo_buyer2 余额 300，密码均为 demo123456）';

    // ---------------- 订单：已完成（含评价） ----------------
    $orderA = OrderService::create($buyer1, $skillIds[0], 2, '演示：晚上 9 点开始');
    OrderService::pay((int) $orderA['id'], $buyer1);
    OrderService::accept((int) $orderA['id'], $companionIds[0]);
    OrderService::finish((int) $orderA['id'], $companionIds[0]);
    OrderService::confirm((int) $orderA['id'], $buyer1, 'user');
    Db::insert('order_reviews', [
        'order_id' => (int) $orderA['id'], 'user_id' => $buyer1, 'companion_id' => $companionIds[0],
        'score' => 5, 'content' => '声音很好听，打野思路清晰，带飞两把！', 'tags' => '技术好,声音甜',
        'is_anonymous' => 0, 'reply' => '谢谢老板，下次再来～', 'reply_at' => now(), 'created_at' => now(),
    ]);
    Db::update('orders', ['is_reviewed' => 1, 'updated_at' => now()], 'id = ?', [(int) $orderA['id']]);
    CompanionService::recalcScore($companionIds[0]);

    // ---------------- 订单：服务中 ----------------
    $orderD = OrderService::create($buyer1, $skillIds[1] ?? $skillIds[0], 2, '演示：正在服务中');
    OrderService::pay((int) $orderD['id'], $buyer1);
    OrderService::accept((int) $orderD['id'], $companionIds[1] ?? $companionIds[0]);

    // ---------------- 订单：待接单 ----------------
    $orderB = OrderService::create($buyer2, $skillIds[count($skillIds) - 1], 1, '演示：等待接单');
    OrderService::pay((int) $orderB['id'], $buyer2);

    // ---------------- 订单：待支付 ----------------
    OrderService::create($buyer2, $skillIds[0], 1, '演示：还没支付');

    $created[] = '已创建 4 笔订单：已完成(含评价) / 服务中 / 待接单 / 待支付';

    // ---------------- 待确认充值单 ----------------
    FinanceService::createRecharge($buyer1, 100, 'manual', '', '演示：已微信转账，昵称 小王');
    $created[] = '已创建 1 笔待确认充值单（可到「充值审核」体验确认到账/驳回）';

    // ---------------- 待审核提现单（金额受最低提现额与余额限制，避免设置不同导致失败） ----------------
    $wdAmount = max(Setting::float('min_withdraw', 50.0), 50.0);
    $compBal  = Wallet::balance($companionIds[0]);
    if ($compBal >= $wdAmount && $wdAmount > 0) {
        FinanceService::applyWithdraw($companionIds[0], $wdAmount, 'alipay', '小鹿', 'demo@example.com');
        $created[] = '已创建 1 笔待审核提现单（' . money($wdAmount) . ' 元，可到「提现审核」体验打款/驳回）';
    } else {
        $created[] = '已跳过提现单示例（陪玩余额 ' . money($compBal) . ' 元 < 最低提现 ' . money($wdAmount) . ' 元，与平台设置有关）';
    }

    return $created;
}

/** 清除演示数据 */
function demo_clear(): array
{
    $userIds = demo_user_ids();
    if (!$userIds) {
        return ['没有找到演示数据'];
    }
    $in   = implode(',', $userIds);
    $log  = [];
    // 只清理「双方都是演示账号」的订单与评价，避免误删真实用户与演示陪玩之间的订单
    $sqls = [
        '资金流水'   => "DELETE FROM wallet_transactions WHERE user_id IN ($in)",
        '钱包'       => "DELETE FROM user_wallets WHERE user_id IN ($in)",
        '提现单'     => "DELETE FROM withdraw_orders WHERE user_id IN ($in)",
        '充值单'     => "DELETE FROM recharge_orders WHERE user_id IN ($in)",
        '评价'       => "DELETE FROM order_reviews WHERE user_id IN ($in) AND companion_id IN ($in)",
        '订单日志'   => "DELETE FROM order_logs WHERE order_id IN (SELECT id FROM orders WHERE user_id IN ($in) AND companion_id IN ($in))",
        '订单'       => "DELETE FROM orders WHERE user_id IN ($in) AND companion_id IN ($in)",
        '技能'       => "DELETE FROM companion_skills WHERE companion_id IN ($in)",
        '陪玩资料'   => "DELETE FROM companion_profiles WHERE user_id IN ($in)",
        '账号'       => "DELETE FROM users WHERE id IN ($in)",
    ];
    foreach ($sqls as $name => $sql) {
        try {
            $log[] = $name . ' 清理 ' . Db::query($sql)->rowCount() . ' 行';
        } catch (\Throwable $e) {
            $log[] = $name . ' 清理失败：' . $e->getMessage();
        }
    }
    return $log;
}

$message = '';
$error   = '';
$details = [];
$action  = (string) ($_POST['action'] ?? '');

if (is_post()) {
    verify_csrf();
    try {
        if ($action === 'create') {
            // 已有演示账号时先清理，避免用户名唯一键冲突留下半套数据
            if (demo_user_ids()) {
                demo_clear();
            }
            // 整个生成过程放在一个事务里：任何一步失败都不会留下半套演示数据
            $details = Db::tx(function () {
                return demo_seed();
            });
            $message = '演示数据生成完成';
        } elseif ($action === 'clear') {
            $details = demo_clear();
            $message = '演示数据已清除';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage() . '（生成过程已整体回滚，不会留下半套数据）';
    }
}

$existingIds = demo_user_ids();
$hasDemo     = count($existingIds) > 0;
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>演示数据 - 陪玩点单系统</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #f4f5f9; color: #23222b; font-family: -apple-system, "Segoe UI", "Microsoft YaHei", sans-serif; font-size: 14px; line-height: 1.7; }
  .wrap { max-width: 820px; margin: 0 auto; padding: 30px 18px 70px; }
  h1 { font-size: 21px; margin: 0 0 4px; }
  h2 { font-size: 16px; margin: 0 0 10px; }
  .card { background: #fff; border: 1px solid #eceaf3; border-radius: 14px; padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(29,22,71,.05); }
  .muted { color: #8b8a97; }
  .alert { padding: 11px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 13.5px; }
  .alert-ok { background: #e9f9ef; color: #14804a; }
  .alert-error { background: #fdecef; color: #b3123a; }
  .alert-warn { background: #fff6e6; color: #96590a; }
  .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; border: none; background: #6c5ce7; color: #fff; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; }
  .btn:hover { background: #5a4bd1; color: #fff; }
  .btn-danger { background: #e11d48; }
  .btn-danger:hover { background: #c11340; }
  ul { margin: 6px 0 0; padding-left: 20px; }
  code { background: #f4f3fa; padding: 1px 5px; border-radius: 5px; font-size: 12.5px; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  th, td { text-align: left; padding: 7px 6px; border-bottom: 1px solid #f0eef7; }
</style>
</head>
<body>
<div class="wrap">
  <h1>演示数据</h1>
  <p class="muted">所有演示账号用户名以 <code>demo_</code> 开头，统一密码 <code>demo123456</code>，可一键清除。</p>

  <?php if ($message !== ''): ?>
    <div class="alert alert-ok"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert-error">操作失败：<?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($details): ?>
    <div class="card">
      <h2>执行明细</h2>
      <ul>
        <?php foreach ($details as $line): ?>
          <li class="muted"><?= h($line) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>当前状态</h2>
    <p><?= $hasDemo ? '<span style="color:#16a34a;font-weight:600">已存在演示数据</span>（共 ' . count($existingIds) . ' 个账号）' : '暂无演示数据' ?></p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <button class="btn" type="submit"><?= $hasDemo ? '重新生成演示数据' : '一键生成演示数据' ?></button>
      </form>
      <?php if ($hasDemo): ?>
        <form method="post" data-confirm="确定删除全部演示账号及其订单、流水吗？">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear">
          <button class="btn btn-danger" type="submit">清除演示数据</button>
        </form>
      <?php endif; ?>
    </div>
    <p class="muted" style="margin-top:10px">重复生成前建议先清除，避免出现重名账号。</p>
  </div>

  <div class="card">
    <h2>生成后的演示账号</h2>
    <table>
      <thead><tr><th>用户名</th><th>密码</th><th>身份 / 用途</th></tr></thead>
      <tbody>
        <tr><td>demo_comp1 / 2 / 3</td><td>demo123456</td><td>已通过审核的陪玩（可直接接单）</td></tr>
        <tr><td>demo_comp_pending</td><td>demo123456</td><td>待审核陪玩（后台「陪玩管理」可见）</td></tr>
        <tr><td>demo_buyer1 / 2</td><td>demo123456</td><td>买家，钱包有余额，可直接下单</td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>下一步</h2>
    <ul>
      <li><a href="index.php?r=home/index">前台首页</a> · <a href="<?= e(admin_path()) ?>/index.php?r=dashboard/index">管理后台</a></li>
      <li><a href="selftest.php">运行资金链路自检</a> · <a href="check.php">运行环境与语法自检</a></li>
      <li>正式上线前：清除演示数据并删除 <code>install.php</code>、<code>check.php</code>、<code>selftest.php</code>、<code>demo.php</code></li>
    </ul>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
