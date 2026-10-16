<?php
/**
 * 业务自检（浏览器访问 /selftest.php，需先用管理员登录后台）
 *
 * 它会自动创建两个临时账号（买家 + 陪玩），把核心资金链路完整跑一遍并逐条断言：
 *   下单 → 余额支付 → 陪玩接单 → 提交完成 → 用户确认 → 平台抽成分账 → 评价与评分重算
 *   → 第二笔订单取消退款 → 退款驳回回到原状态 → 后台强制完成「服务中」订单
 *   → 后台强制退款关闭 → 后台代客下单（自定义金额 / 立即余额支付 / 余额不足整体回滚）
 *   → 提现申请(冻结) → 后台打款 → 再次申请 → 后台驳回(解冻) → 人工调账
 *   → 防护类断言：已完成订单不可重复退款、已驳回充值单不可改判到账、冻结不足拒绝解冻、
 *     非法状态流转与超额扣款一律拦截
 * 运行结束后会删掉自己创建的全部数据（订单、流水、钱包、提现单、充值单、技能、账号），
 * 不会影响真实业务数据。**确认无误后请删除本文件。**
 *
 * 注意：必须用页面上的按钮（POST + CSRF）触发，直接 GET 访问只会显示说明页。
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

// 仅管理员可执行
if (!Auth::isAdmin()) {
    flash('warning', '请先用管理员账号登录后台，再访问自检脚本');
    redirect(admin_url('auth/login'));
}

// 会建数据+删数据，必须 POST + CSRF 触发，避免被爬虫/预取（<img src>）误跑
$started = is_post();
if ($started) {
    verify_csrf();
}

$RESULTS = [];
$ERRORS  = [];
$CTX     = [
    'users'    => [],
    'orders'   => [],
    'skills'   => [],
    'games'    => [],
];

function check(string $name, bool $pass, string $detail = '', bool $skipped = false): void
{
    global $RESULTS;
    $RESULTS[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail, 'skipped' => $skipped];
}

function near(float $a, float $b, float $epsilon = 0.005): bool
{
    return abs($a - $b) < $epsilon;
}

/** 输出转义（本文件是独立入口，bootstrap 只提供 e()） */
if (!function_exists('h')) {
    function h($v): string
    {
        return e($v);
    }
}

function fmt($v): string
{
    return is_float($v) ? number_format($v, 2, '.', '') : (string) $v;
}

/** 清理自检产生的数据（只删自己造的） */
function cleanup(array $ctx): array
{
    $log = [];
    $userIds = $ctx['users'];
    $orderIds = $ctx['orders'];

    $steps = [];
    if ($userIds) {
        $in = implode(',', array_map('intval', $userIds));
        $steps[] = ['wallet_transactions', "DELETE FROM wallet_transactions WHERE user_id IN ($in)"];
        $steps[] = ['user_wallets', "DELETE FROM user_wallets WHERE user_id IN ($in)"];
        $steps[] = ['withdraw_orders', "DELETE FROM withdraw_orders WHERE user_id IN ($in)"];
        $steps[] = ['recharge_orders', "DELETE FROM recharge_orders WHERE user_id IN ($in)"];
    }
    if ($orderIds) {
        $in = implode(',', array_map('intval', $orderIds));
        $steps[] = ['order_reviews', "DELETE FROM order_reviews WHERE order_id IN ($in)"];
        $steps[] = ['order_logs', "DELETE FROM order_logs WHERE order_id IN ($in)"];
        $steps[] = ['orders', "DELETE FROM orders WHERE id IN ($in)"];
    }
    if ($userIds) {
        $in = implode(',', array_map('intval', $userIds));
        // 只删「双方都是自检账号」的订单，避免误删真实用户的订单
        $steps[] = ['orders(其他)', "DELETE FROM orders WHERE user_id IN ($in) AND companion_id IN ($in)"];
        $steps[] = ['companion_skills', "DELETE FROM companion_skills WHERE companion_id IN ($in)"];
        $steps[] = ['companion_profiles', "DELETE FROM companion_profiles WHERE user_id IN ($in)"];
        $steps[] = ['users', "DELETE FROM users WHERE id IN ($in)"];
    }
    foreach ($ctx['games'] as $gameId) {
        $steps[] = ['games', 'DELETE FROM games WHERE id = ' . (int) $gameId];
    }

    foreach ($steps as $step) {
        try {
            $n = Db::query($step[1])->rowCount();
            $log[] = $step[0] . ' 清理 ' . $n . ' 行';
        } catch (Throwable $e) {
            $log[] = $step[0] . ' 清理失败：' . $e->getMessage();
        }
    }
    return $log;
}

$suffix   = substr(bin2hex(random_bytes(4)), 0, 6);
$gameId   = 0;
$cleanLog = [];

if ($started) {
try {
    // ------------------------------------------------------------ 准备数据
    $game = Db::one('SELECT id FROM games WHERE status = 1 ORDER BY id ASC LIMIT 1');
    if ($game) {
        $gameId = (int) $game['id'];
    } else {
        $gameId = Db::insert('games', [
            'name' => '自检游戏' . $suffix, 'icon' => '', 'sort' => 0, 'is_hot' => 0,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $CTX['games'][] = $gameId;
    }

    $buyerId = Db::insert('users', [
        'username' => 'st_buyer_' . $suffix, 'password' => password_hash('test123456', PASSWORD_DEFAULT),
        'nickname' => '自检买家', 'phone' => '1380000' . rand(1000, 9999), 'role' => 'user',
        'status' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $CTX['users'][] = $buyerId;

    $compId = Db::insert('users', [
        'username' => 'st_comp_' . $suffix, 'password' => password_hash('test123456', PASSWORD_DEFAULT),
        'nickname' => '自检陪玩', 'phone' => '1390000' . rand(1000, 9999), 'role' => 'user',
        'status' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $CTX['users'][] = $compId;

    Db::query(
        'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
         VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
        [$buyerId, now(), now()]
    );
    Db::query(
        'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
         VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
        [$compId, now(), now()]
    );
    check('准备测试账号', true, '买家 #' . $buyerId . '、陪玩 #' . $compId . '，游戏 #' . $gameId);

    // 陪玩入驻 + 审核 + 上架技能
    CompanionService::apply($compId, [
        'real_name' => '自检陪玩', 'phone' => '13900000000', 'gender' => 2,
        'city' => '测试城市', 'intro' => '自检用账号', 'tags' => '自检',
    ]);
    CompanionService::audit($compId, 1, '自检自动通过');
    $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$compId]);
    check('陪玩入驻并审核通过', $profile !== null && (int) $profile['audit_status'] === 1);

    $compUser = Db::one('SELECT role FROM users WHERE id = ? LIMIT 1', [$compId]);
    check('审核通过后角色变为 companion', ($compUser['role'] ?? '') === 'companion', '当前角色：' . ($compUser['role'] ?? '无'));

    $skillId = CompanionService::saveSkill($compId, [
        'game_id' => $gameId, 'title' => '自检服务 陪练', 'service_type' => 'hour',
        'rank_level' => '王者', 'price' => 100, 'description' => '自检专用',
    ]);
    $CTX['skills'][] = $skillId;
    check('上架技能成功', $skillId > 0, '技能 #' . $skillId . '，单价 100/小时');

    // 给买家加 500 元（模拟后台加款）
    FinanceService::adjustBalance($buyerId, 500, 'add', Auth::id(), '自检加款');
    $w = Wallet::get($buyerId);
    check('后台加款 500 元', near((float) $w['balance'], 500), '当前余额 ' . fmt((float) $w['balance']));

    // ------------------------------------------------------------ 主流程：下单→支付→接单→完成→确认→分账
    $order = OrderService::create($buyerId, $skillId, 2, '自检主流程');
    $CTX['orders'][] = (int) $order['id'];
    check('下单成功且金额计算正确', (int) $order['status'] === 1 && near((float) $order['amount'], 200),
        '订单 #' . $order['id'] . '，状态 ' . $order['status'] . '，金额 ' . fmt((float) $order['amount']));

    $before = Wallet::balance($buyerId);
    OrderService::pay((int) $order['id'], $buyerId);
    $after  = Wallet::balance($buyerId);
    $paid   = OrderService::find((int) $order['id']);
    check('余额支付扣款正确', near($before - $after, 200) && (int) $paid['status'] === 2,
        '余额 ' . fmt($before) . ' → ' . fmt($after) . '，订单状态 ' . $paid['status']);

    OrderService::accept((int) $order['id'], $compId);
    $accepted = OrderService::find((int) $order['id']);
    check('陪玩接单', (int) $accepted['status'] === 3, '订单状态 ' . $accepted['status']);

    OrderService::finish((int) $order['id'], $compId);
    $finished = OrderService::find((int) $order['id']);
    check('陪玩提交完成', (int) $finished['status'] === 4, '订单状态 ' . $finished['status']);

    $rate = Setting::float('platform_fee_rate', 20.0);
    $expectFee    = round(200 * $rate / 100, 2);
    $expectIncome = round(200 - $expectFee, 2);

    OrderService::confirm((int) $order['id'], $buyerId, 'user');
    $done = OrderService::find((int) $order['id']);
    check('用户确认后订单已完成', (int) $done['status'] === 5, '订单状态 ' . $done['status']);
    check('平台抽成金额正确（比例 ' . fmt($rate) . '%）', near((float) $done['platform_fee'], $expectFee),
        '实际 ' . fmt((float) $done['platform_fee']) . '，预期 ' . fmt($expectFee));
    check('陪玩实收金额正确', near((float) $done['companion_income'], $expectIncome),
        '实际 ' . fmt((float) $done['companion_income']) . '，预期 ' . fmt($expectIncome));
    check('抽成 + 实收 = 订单金额', near((float) $done['platform_fee'] + (float) $done['companion_income'], 200),
        fmt((float) $done['platform_fee']) . ' + ' . fmt((float) $done['companion_income']) . ' = '
        . fmt((float) $done['platform_fee'] + (float) $done['companion_income']));

    $compWallet = Wallet::get($compId);
    check('陪玩余额入账正确', near((float) $compWallet['balance'], $expectIncome),
        '陪玩余额 ' . fmt((float) $compWallet['balance']) . '，预期 ' . fmt($expectIncome));

    $compProfile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$compId]);
    check('陪玩统计更新（完成单数 +1）', (int) $compProfile['order_count'] === 1,
        '完成单数 ' . $compProfile['order_count'] . '，累计收入 ' . fmt((float) $compProfile['total_income']));

    $flowCount = (int) Db::value('SELECT COUNT(*) FROM wallet_transactions WHERE user_id IN (?, ?)', [$buyerId, $compId]);
    check('资金流水已记录（含前后余额）', $flowCount >= 3, '共 ' . $flowCount . ' 条流水（加款 / 支付 / 陪玩入账）');

    // ------------------------------------------------------------ 评价与评分重算
    Db::insert('order_reviews', [
        'order_id' => (int) $order['id'], 'user_id' => $buyerId, 'companion_id' => $compId,
        'score' => 5, 'content' => '自检评价', 'tags' => '技术好', 'is_anonymous' => 0, 'created_at' => now(),
    ]);
    Db::update('orders', ['is_reviewed' => 1, 'updated_at' => now()], 'id = ?', [(int) $order['id']]);
    CompanionService::recalcScore($compId);
    $scored = Db::one('SELECT score, review_count, good_rate FROM companion_profiles WHERE user_id = ? LIMIT 1', [$compId]);
    check('评价后评分/好评率重算', near((float) $scored['score'], 5) && (int) $scored['review_count'] === 1 && near((float) $scored['good_rate'], 100),
        '评分 ' . fmt((float) $scored['score']) . '，评价数 ' . (int) $scored['review_count'] . '，好评率 ' . fmt((float) $scored['good_rate']) . '%');

    // ------------------------------------------------------------ 取消退款路径
    $order2 = OrderService::create($buyerId, $skillId, 1, '自检退款流程');
    $CTX['orders'][] = (int) $order2['id'];
    check('第二笔订单金额正确（1 小时 × 100）', near((float) $order2['amount'], 100), '金额 ' . fmt((float) $order2['amount']));

    $b1 = Wallet::balance($buyerId);
    OrderService::pay((int) $order2['id'], $buyerId);
    $b2 = Wallet::balance($buyerId);
    OrderService::cancel((int) $order2['id'], $buyerId, 'user', '自检取消退款');
    $refunded = OrderService::find((int) $order2['id']);
    $b3 = Wallet::balance($buyerId);

    check('支付后取消，订单进入已退款', (int) $refunded['status'] === 8, '订单状态 ' . $refunded['status']);
    check('退款金额原路退回余额', near($b1 - $b2, 100) && near($b3, $b1),
        '余额 ' . fmt($b1) . ' → 支付后 ' . fmt($b2) . ' → 退款后 ' . fmt($b3));

    // ------------------------------------------------------------ 提现：打款
    $minWithdraw = Setting::float('min_withdraw', 50.0);
    $feeRate     = Setting::float('withdraw_fee_rate', 1.0);
    $compBal     = Wallet::balance($compId);

    if ($compBal + 0.005 < $minWithdraw) {
        check('提现流程（陪玩余额低于最低提现额，已跳过）', true,
            '陪玩余额 ' . fmt($compBal) . ' < 最低提现 ' . fmt($minWithdraw) . '，属于设置导致，不算失败', true);
    } else {
        $wdAmount    = min($compBal, max($minWithdraw, 50.0));
        $expectWdFee = safe_float($wdAmount * $feeRate / 100);
        $expectWdNet = safe_float($wdAmount - $expectWdFee);

        $w0     = Wallet::get($compId);
        $apply1 = FinanceService::applyWithdraw($compId, $wdAmount, 'alipay', '自检陪玩', 'selftest@example.com');
        $w1     = Wallet::get($compId);
        check('提现申请后金额被冻结',
            near((float) $w1['frozen'], $wdAmount) && near((float) $w0['balance'] - (float) $w1['balance'], $wdAmount),
            '申请 ' . fmt($wdAmount) . ' 元：余额 ' . fmt((float) $w0['balance']) . ' → ' . fmt((float) $w1['balance'])
            . '，冻结 ' . fmt((float) $w1['frozen']));
        check('提现手续费计算正确（费率 ' . fmt($feeRate) . '%）',
            near($apply1['fee'], $expectWdFee) && near($apply1['real_amount'], $expectWdNet),
            '手续费 ' . fmt($apply1['fee']) . '（预期 ' . fmt($expectWdFee) . '），实际到账 '
            . fmt($apply1['real_amount']) . '（预期 ' . fmt($expectWdNet) . '）');

        FinanceService::auditWithdraw($apply1['withdraw_id'], Auth::id(), 1, '自检打款');
        $w2 = Wallet::get($compId);
        check('提现打款后冻结清零、累计提现增加',
            near((float) $w2['frozen'], 0) && near((float) $w2['balance'], (float) $w1['balance']) && near((float) $w2['total_withdraw'], $wdAmount),
            '冻结 ' . fmt((float) $w2['frozen']) . '，余额 ' . fmt((float) $w2['balance'])
            . '，累计提现 ' . fmt((float) $w2['total_withdraw']) . '（预期 ' . fmt($wdAmount) . '）');

        // -------------------------------------------------------- 提现：驳回
        // 第二次提现金额受剩余余额限制（打款后余额已减少），避免因设置差异导致测试中断
        $wdAmount2 = min($wdAmount, Wallet::balance($compId));
        if ($wdAmount2 + 0.005 < $minWithdraw) {
            check('提现驳回流程（余额低于最低提现额，已跳过）', true,
                '当前余额 ' . fmt(Wallet::balance($compId)) . ' < 最低提现 ' . fmt($minWithdraw) . '，属于设置导致，不算失败', true);
        } else {
            $apply2 = FinanceService::applyWithdraw($compId, $wdAmount2, 'wechat', '自检陪玩', 'selftest_wx');
            $w3     = Wallet::get($compId);
            FinanceService::auditWithdraw($apply2['withdraw_id'], Auth::id(), 2, '自检驳回');
            $w4 = Wallet::get($compId);
            check('提现驳回后金额解冻回余额',
                near((float) $w4['frozen'], 0) && near((float) $w4['balance'], (float) $w3['balance'] + $wdAmount2),
                '申请 ' . fmt($wdAmount2) . ' 元：申请后余额 ' . fmt((float) $w3['balance']) . '（冻结 ' . fmt((float) $w3['frozen']) . '）→ 驳回后余额 '
                . fmt((float) $w4['balance']) . '（冻结 ' . fmt((float) $w4['frozen']) . '）');
        }
    }

    // ------------------------------------------------------------ 人工调账
    $b4 = Wallet::balance($buyerId);
    FinanceService::adjustBalance($buyerId, 10, 'add', Auth::id(), '自检加款');
    $b5 = Wallet::balance($buyerId);
    FinanceService::adjustBalance($buyerId, 10, 'sub', Auth::id(), '自检扣款');
    $b6 = Wallet::balance($buyerId);
    check('后台人工加款/扣款正确', near($b5 - $b4, 10) && near($b6, $b4),
        '余额 ' . fmt($b4) . ' → +10 后 ' . fmt($b5) . ' → -10 后 ' . fmt($b6));

    // ------------------------------------------------------------ 退款驳回必须回到申请前的状态
    $order3 = OrderService::create($buyerId, $skillId, 1, '自检：退款驳回回到原状态');
    $CTX['orders'][] = (int) $order3['id'];
    OrderService::pay((int) $order3['id'], $buyerId);
    OrderService::accept((int) $order3['id'], $compId);

    OrderService::applyRefund((int) $order3['id'], $buyerId, '自检：服务中申请退款');
    $refunding = OrderService::find((int) $order3['id']);
    check('服务中订单申请退款 → 退款中(6)', (int) $refunding['status'] === 6, '订单状态 ' . $refunding['status']);

    OrderService::auditRefund((int) $order3['id'], Auth::id(), false, '自检：驳回退款');
    $afterReject = OrderService::find((int) $order3['id']);
    check('退款驳回 → 回到原状态(3)，而不是待确认(4)', (int) $afterReject['status'] === 3,
        '订单状态 ' . $afterReject['status'] . '（预期 3），finish_time ' . ($afterReject['finish_time'] ?: '空'));

    // ------------------------------------------------------------ 后台可把"服务中"直接推进到完成
    OrderService::confirm((int) $order3['id'], Auth::id(), 'admin');
    $forced = OrderService::find((int) $order3['id']);
    check('后台可强制完成「服务中」订单', (int) $forced['status'] === 5, '订单状态 ' . $forced['status']);
    check('强制完成时正常分账（抽成 + 实收 = 金额）',
        near((float) $forced['platform_fee'] + (float) $forced['companion_income'], (float) $forced['amount'])
        && (float) $forced['companion_income'] > 0,
        '抽成 ' . fmt((float) $forced['platform_fee']) . ' + 实收 ' . fmt((float) $forced['companion_income'])
        . ' = ' . fmt((float) $forced['platform_fee'] + (float) $forced['companion_income'])
        . '，订单金额 ' . fmt((float) $forced['amount']));

    // ------------------------------------------------------------ 已完成订单不允许再退款（防双倍损失）
    $refundGuard = 0;
    try {
        OrderService::adminRefund((int) $order3['id'], Auth::id(), '自检：试图重复退款');
    } catch (Throwable $e) {
        $refundGuard++;
    }
    try {
        OrderService::refund(OrderService::find((int) $order3['id']), Auth::id(), 'admin', '自检：直接退已完成订单');
    } catch (Throwable $e) {
        $refundGuard++;
    }
    check('已完成订单无法重复退款', $refundGuard === 2, '成功拦截 ' . $refundGuard . '/2 项');

    // ------------------------------------------------------------ 后台强制退款关闭（服务中订单）
    $order4 = OrderService::create($buyerId, $skillId, 1, '自检：后台强制关闭');
    $CTX['orders'][] = (int) $order4['id'];
    $c0 = Wallet::balance($buyerId);
    OrderService::pay((int) $order4['id'], $buyerId);
    OrderService::accept((int) $order4['id'], $compId);
    OrderService::adminRefund((int) $order4['id'], Auth::id(), '自检：平台强制关闭');
    $closed4 = OrderService::find((int) $order4['id']);
    $c1      = Wallet::balance($buyerId);
    check('后台可强制退款关闭「服务中」订单',
        (int) $closed4['status'] === 8 && near($c1, $c0),
        '订单状态 ' . $closed4['status'] . '，买家余额 ' . fmt($c0) . ' → ' . fmt($c1));

    // ------------------------------------------------------------ 充值单不能重复入账
    $rcId = FinanceService::createRecharge($buyerId, 30, 'manual', '', '自检充值');
    FinanceService::auditRecharge($rcId, Auth::id(), 3, '自检：驳回');
    $rcGuard = 0;
    try {
        FinanceService::auditRecharge($rcId, Auth::id(), 1, '自检：试图改判为到账');
    } catch (Throwable $e) {
        $rcGuard++;
    }
    check('已驳回的充值单不能改判为到账', $rcGuard === 1, '成功拦截 ' . $rcGuard . '/1');

    // ------------------------------------------------------------ 冻结不足时必须报错（防造币）
    $frozenGuard = 0;
    try {
        Wallet::unfreeze($buyerId, 999999, 'withdraw_reject', 'withdraw', 0, '自检：超额解冻');
    } catch (Throwable $e) {
        $frozenGuard++;
    }
    try {
        Wallet::deductFrozen($buyerId, 999999, 'withdraw_paid', 'withdraw', 0, '自检：超额扣冻结');
    } catch (Throwable $e) {
        $frozenGuard++;
    }
    check('冻结金额不足时拒绝解冻/扣减', $frozenGuard === 2, '成功拦截 ' . $frozenGuard . '/2 项');

    // ------------------------------------------------------------ 后台代客下单（可自定义金额）
    $order5 = OrderService::adminCreate(Auth::id(), $buyerId, $compId, $skillId, 1, 88.88, '自检代客下单', false);
    $CTX['orders'][] = (int) $order5['id'];
    check('后台代客下单支持自定义金额',
        near((float) $order5['amount'], 88.88) && (int) $order5['status'] === 1,
        '金额 ' . fmt((float) $order5['amount']) . '（预期 88.88），状态 ' . (int) $order5['status']);
    check('代客下单快照了游戏与服务项目',
        (string) $order5['game_name'] !== '' && (string) $order5['skill_title'] !== '',
        $order5['game_name'] . ' / ' . $order5['skill_title']);

    $d0 = Wallet::balance($buyerId);
    $order6 = OrderService::adminCreate(Auth::id(), $buyerId, $compId, $skillId, 2, 10, '自检代客下单并支付', true);
    $CTX['orders'][] = (int) $order6['id'];
    $d1 = Wallet::balance($buyerId);
    check('代客下单可直接用余额支付',
        (int) $order6['status'] === 2 && near($d0 - $d1, 20),
        '状态 ' . (int) $order6['status'] . '，余额 ' . fmt($d0) . ' → ' . fmt($d1) . '（应扣 20）');

    $beforeOrders   = (int) Db::value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$buyerId]);
    $rollbackGuard  = 0;
    try {
        OrderService::adminCreate(Auth::id(), $buyerId, $compId, $skillId, 1, 99999, '自检：余额不足应回滚', true);
    } catch (Throwable $e) {
        $rollbackGuard++;
    }
    $afterOrders = (int) Db::value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$buyerId]);
    check('代客下单余额不足时整体回滚（不留半张订单）',
        $rollbackGuard === 1 && $afterOrders === $beforeOrders,
        '拦截 ' . $rollbackGuard . '/1，订单数 ' . $beforeOrders . ' → ' . $afterOrders);

    // ------------------------------------------------------------ 越权与非法流转防护
    $blocked = 0;
    try {
        OrderService::cancel((int) $order['id'], $buyerId, 'user', '试图取消已完成订单');
    } catch (Throwable $e) {
        $blocked++;
    }
    try {
        OrderService::accept((int) $order['id'], $compId);
    } catch (Throwable $e) {
        $blocked++;
    }
    try {
        Wallet::expense($buyerId, 999999, 'order_pay', 'order', 0, '超额扣款测试');
    } catch (Throwable $e) {
        $blocked++;
    }
    check('非法状态流转与超额扣款被正确拦截', $blocked === 3, '成功拦截 ' . $blocked . '/3 项');
} catch (Throwable $e) {
    $ERRORS[] = $e->getMessage() . ' @ ' . str_replace('\\', '/', $e->getFile()) . ':' . $e->getLine();
} finally {
    $cleanLog = cleanup($CTX);
}
} // end if ($started)

$passCount    = 0;
$skippedCount = 0;
foreach ($RESULTS as $r) {
    if (!empty($r['skipped'])) {
        $skippedCount++;
        continue;
    }
    if ($r['pass']) {
        $passCount++;
    }
}
$total     = count($RESULTS);
$realTotal = $total - $skippedCount;
$allPass   = ($ERRORS === [] && $realTotal > 0 && $passCount === $realTotal);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>业务自检 - 陪玩点单系统</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #f4f5f9; color: #23222b; font-family: -apple-system, "Segoe UI", "Microsoft YaHei", sans-serif; font-size: 14px; line-height: 1.65; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 30px 18px 70px; }
  h1 { font-size: 21px; margin: 0 0 4px; }
  h2 { font-size: 16px; margin: 0 0 12px; }
  .card { background: #fff; border: 1px solid #eceaf3; border-radius: 14px; padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(29,22,71,.05); }
  .muted { color: #8b8a97; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #f0eef7; vertical-align: top; }
  .ok { color: #16a34a; font-weight: 600; }
  .bad { color: #e11d48; font-weight: 600; }
  .warn { color: #d97706; font-weight: 600; }
  .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 13.5px; }
  .alert-ok { background: #e9f9ef; color: #14804a; }
  .alert-error { background: #fdecef; color: #b3123a; }
  .big { font-size: 30px; font-weight: 700; }
  code { background: #f4f3fa; padding: 1px 5px; border-radius: 5px; font-size: 12.5px; }
  ul { margin: 6px 0 0; padding-left: 20px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>业务自检（流程冒烟测试）</h1>
  <p class="muted">只使用自建的临时账号，跑完自动清理，不影响真实数据。</p>

  <?php if (!$started): ?>
    <div class="card">
      <div class="alert alert-info">点击下面的按钮开始自检：会在库里创建两个临时账号，跑完整条资金链路并逐条断言，结束后自动删除这些数据。</div>
      <form method="post">
        <?= csrf_field() ?>
        <button type="submit" style="padding:10px 20px;border:none;border-radius:10px;background:#6c5ce7;color:#fff;font-weight:600;font-size:14px;cursor:pointer">开始自检</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="card">
    <?php if ($ERRORS): ?>
      <div class="alert alert-error">
        <strong>测试中断</strong>：<?= h(implode('；', $ERRORS)) ?>
        <div class="muted" style="margin-top:6px">中断前已完成的断言见下方列表。</div>
      </div>
    <?php elseif ($allPass): ?>
      <div class="alert alert-ok">
        <strong>全部通过</strong>：<?= $passCount ?> / <?= $realTotal ?> 项断言成立，核心资金链路无误。
        <?php if ($skippedCount > 0): ?>
          <div class="muted" style="margin-top:6px">另有 <?= $skippedCount ?> 项因平台设置（如最低提现额高于余额）被跳过，不算通过也不算失败。</div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-error"><strong>存在失败项</strong>：<?= $passCount ?> / <?= $realTotal ?> 项通过，请查看下方标记为「失败」的条目。</div>
    <?php endif; ?>
    <p class="big <?= $allPass ? 'ok' : 'bad' ?>"><?= $passCount ?> / <?= $realTotal ?><?= $skippedCount > 0 ? '（跳过 ' . $skippedCount . '）' : '' ?></p>
  </div>

  <div class="card">
    <h2>断言明细</h2>
    <table>
      <thead><tr><th style="width:70px">结果</th><th>检查项</th><th>实际值</th></tr></thead>
      <tbody>
      <?php foreach ($RESULTS as $r): ?>
        <?php $state = !empty($r['skipped']) ? 'skip' : ($r['pass'] ? 'ok' : 'bad'); ?>
        <tr>
          <td class="<?= $state === 'skip' ? 'warn' : ($state === 'ok' ? 'ok' : 'bad') ?>">
            <?= $state === 'skip' ? '跳过' : ($state === 'ok' ? '通过' : '失败') ?>
          </td>
          <td><?= h($r['name']) ?></td>
          <td class="muted"><?= h($r['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$RESULTS): ?>
        <tr><td colspan="3" class="muted">没有执行任何断言</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>数据清理</h2>
    <ul>
      <?php foreach ($cleanLog as $line): ?>
        <li class="muted"><?= h($line) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <h2>下一步</h2>
    <ul>
      <li><a href="check.php">运行系统自检（语法 / 环境 / 表结构）</a></li>
      <li><a href="index.php?r=home/index">进入前台</a> · <a href="<?= e(admin_path()) ?>/index.php?r=dashboard/index">进入后台</a></li>
      <li>确认无误后删除 <code>install.php</code>、<code>check.php</code>、<code>selftest.php</code></li>
    </ul>
  </div>
</div>
</body>
</html>
