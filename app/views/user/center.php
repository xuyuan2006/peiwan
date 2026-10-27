<?php
/**
 * 我的（个人中心，底部 Tab「我的」）
 * @var array      $wallet
 * @var array      $counts
 * @var array|null $profile
 * @var array|null $comp_stat
 * @var int        $serving
 */
$__me     = user();
$roleText = ['user' => '普通用户', 'companion' => '陪玩', 'admin' => '管理员'];

/** 线性图标库 */
$__icons = [
    'user'     => '<circle cx="12" cy="8.2" r="3.9"/><path d="M4.4 20.6c0-3.9 3.4-5.9 7.6-5.9s7.6 2 7.6 5.9"/>',
    'wallet'   => '<rect x="2.6" y="6" width="18.8" height="13" rx="3"/><path d="M2.6 10.2h18.8"/><circle cx="17" cy="14.6" r="1.2"/>',
    'list'     => '<path d="M6 3.5h12v17H6z"/><path d="M9 8h6M9 12h6M9 16h3.5"/>',
    'bolt'     => '<path d="M13.2 2.5 4.6 13.6h6L9.4 21.5l8.9-11.3h-6l.9-7.7z"/>',
    'chart'    => '<path d="M4 19.5V11M10 19.5V5M16 19.5v-6M3 21h18"/>',
    'game'     => '<rect x="2" y="7" width="20" height="10.5" rx="4.8"/><path d="M7.2 10.3v4M5.2 12.3h4"/><circle cx="16" cy="11.2" r="1"/><circle cx="18.2" cy="13.4" r="1"/>',
    'settings' => '<circle cx="12" cy="12" r="3.1"/><path d="M12 2.8v2.4M12 18.8v2.4M4.5 7.5l2 1.2M17.5 15.3l2 1.2M4.5 16.5l2-1.2M17.5 8.7l2-1.2"/>',
    'logout'   => '<path d="M9.5 21H5.4A2.4 2.4 0 0 1 3 18.6V5.4A2.4 2.4 0 0 1 5.4 3h4.1"/><path d="M16 16.5 20.5 12 16 7.5"/><path d="M20.5 12H9.5"/>',
    'chat'     => '<path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.4 9 9 0 0 1-3.7-.8L3.5 20.5l1.5-5a8.2 8.2 0 0 1-.9-3.8A8.4 8.4 0 0 1 12.6 3 8.4 8.4 0 0 1 21 11.5z"/>',
];
$__icon = function (string $key) use ($__icons): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($__icons[$key] ?? '') . '</svg>';
};
?>
<section class="center-head-card">
  <div class="center-head">
    <img class="avatar avatar-lg" src="<?= e(avatar_url($__me['avatar'], (string) $__me['nickname'])) ?>" alt="">
    <div class="center-head-info">
      <h1 class="center-name">
        <?= e($__me['nickname']) ?>
        <span class="badge <?= $__me['role'] === 'companion' ? 'badge-success' : 'badge-muted' ?>">
          <?= e($roleText[$__me['role']] ?? '用户') ?>
        </span>
      </h1>
      <p class="muted" style="margin:0">
        信用分 <?= (int) $__me['credit_score'] ?> · 注册于 <?= e(substr((string) $__me['created_at'], 0, 10)) ?>
      </p>
      <?php
      // 用户号（随机 6 位）：报给客服、对账、互加好友都要用，所以必须摆出来 + 可复制。
      // 还没执行升级脚本（users.no 不存在或为 0）时，用和回填 SQL 相同的公式现算 ——
      // 用户有权看到自己的号，不能因为没迁移就整块消失。
      $__no = (int) ($__me['no'] ?? 0);
      if ($__no <= 0) {
          $__no = \App\Auth::fallbackUserNo((int) $__me['id']);
      }
      ?>
      <p class="muted" style="margin:6px 0 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span>我的 ID：<b id="my-uid" style="font-size:16px;color:var(--text)"><?= $__no ?></b></span>
        <button type="button" class="btn btn-ghost btn-sm" data-copy="#my-uid">复制</button>
      </p>
      <?php if ($profile && (int) $profile['audit_status'] === 0): ?>
        <p class="hint">陪玩入驻审核中，通过后就能接单了</p>
      <?php elseif ($profile && (int) $profile['audit_status'] === 2): ?>
        <p class="hint">入驻被拒绝：<?= e($profile['audit_remark'] !== '' ? $profile['audit_remark'] : '资料不符合要求') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="center-wallet">
    <div class="cw-item"><span>可用余额</span><strong><?= e(money((float) $wallet['balance'])) ?></strong></div>
    <div class="cw-item"><span>冻结中</span><strong><?= e(money((float) $wallet['frozen'])) ?></strong></div>
    <div class="cw-actions">
      <a class="btn btn-light btn-sm" href="<?= e(url('wallet/recharge')) ?>">充值</a>
      <a class="btn btn-light btn-sm" href="<?= e(url('wallet/withdraw')) ?>">提现</a>
    </div>
  </div>
</section>

<section class="center-stats">
  <a href="<?= e(url('order/index')) ?>"><strong><?= (int) ($counts['total'] ?? 0) ?></strong><span>全部订单</span></a>
  <a href="<?= e(url('order/index', ['status' => 1])) ?>"><strong><?= (int) ($counts['pending_pay'] ?? 0) ?></strong><span>待支付</span></a>
  <a href="<?= e(url('order/index', ['status' => 3])) ?>"><strong><?= (int) ($counts['serving'] ?? 0) ?></strong><span>进行中</span></a>
  <a href="<?= e(url('order/index', ['status' => 5])) ?>"><strong><?= (int) ($counts['completed'] ?? 0) ?></strong><span>已完成</span></a>
</section>

<?php if (is_companion()): ?>
  <?php $__online = (int) ($profile['online_status'] ?? 0) === 1; ?>
  <section class="card">
    <h2 class="card-title">接单状态</h2>
    <div class="online-switch">
      <span class="online-tag <?= $__online ? 'on' : 'off' ?>"><i class="dot"></i></span>
      <span class="os-state <?= $__online ? 'on' : 'off' ?>"><?= $__online ? '在线接单中' : '已离线' ?></span>
      <form method="post" action="<?= e(url('companion/toggleOnline')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="online" value="<?= $__online ? 0 : 1 ?>">
        <button class="btn <?= $__online ? 'btn-ghost' : 'btn-primary' ?>" type="submit">
          <?= $__online ? '下线休息' : '上线接单' ?>
        </button>
      </form>
    </div>
    <p class="hint">
      在线时你会排在「找陪玩」列表最前面，绿色在线标记更容易被点单；
      下线只是不接新单，<b>进行中的订单不受影响</b>。
    </p>
  </section>

  <section class="card">
    <div class="card-head">
      <h2 class="card-title">陪玩中心</h2>
      <a class="btn btn-primary btn-sm" href="<?= e(url('companion/hall')) ?>">去接单</a>
    </div>
    <div class="center-comp">
      <div><span class="muted">累计收入</span><strong><?= e(yuan((float) ($profile['total_income'] ?? 0))) ?></strong></div>
      <div><span class="muted">今日收入</span><strong><?= e(yuan((float) ($comp_stat['today_income'] ?? 0))) ?></strong></div>
      <div><span class="muted">手上单</span><strong><?= (int) $serving ?></strong></div>
    </div>
    <div class="btn-group">
      <a class="btn btn-ghost btn-block" href="<?= e(url('companion/orders')) ?>">我的接单</a>
      <a class="btn btn-ghost btn-block" href="<?= e(url('companion/skills')) ?>">技能管理</a>
    </div>
  </section>
<?php endif; ?>

<section class="card">
  <h2 class="card-title">常用功能</h2>
  <div class="center-menu">
    <a href="<?= e(url('user/profile')) ?>">
      <span class="cm-icon tint-purple"><?= $__icon('user') ?></span><span>编辑资料</span>
    </a>
    <a href="<?= e(url('wallet/index')) ?>">
      <span class="cm-icon tint-blue"><?= $__icon('wallet') ?></span><span>我的钱包</span>
    </a>
    <a href="<?= e(url('wallet/transactions')) ?>">
      <span class="cm-icon tint-gray"><?= $__icon('list') ?></span><span>资金流水</span>
    </a>
    <a href="<?= e(url('ticket/index')) ?>">
        <span class="cm-icon tint-teal"><?= $__icon('chat') ?></span><span>我的工单</span>
      </a>
      <?php $__kfEntry = \App\TicketService::kfChatUrl(); ?>
      <?php if ($__kfEntry !== ''): ?>
        <a href="<?= e($__kfEntry) ?>" target="_blank">
          <span class="cm-icon tint-blue"><?= $__icon('chat') ?></span><span>在线客服</span>
        </a>
      <?php endif; ?>
    <?php if (is_companion()): ?>
      <a href="<?= e(url('companion/hall')) ?>">
        <span class="cm-icon tint-orange"><?= $__icon('bolt') ?></span><span>接单大厅</span>
      </a>
      <a href="<?= e(url('companion/orders')) ?>">
        <span class="cm-icon tint-green"><?= $__icon('game') ?></span><span>我的接单</span>
      </a>
      <a href="<?= e(url('companion/center')) ?>">
        <span class="cm-icon tint-blue"><?= $__icon('chart') ?></span><span>陪玩中心</span>
      </a>
    <?php else: ?>
      <a href="<?= e(url('home/companions')) ?>">
        <span class="cm-icon tint-green"><?= $__icon('game') ?></span><span>找陪玩</span>
      </a>
      <a href="<?= e(url('companion/center')) ?>">
        <span class="cm-icon tint-blue"><?= $__icon('chart') ?></span><span>陪玩中心</span>
      </a>
      <a href="<?= e(url('user/apply')) ?>">
        <span class="cm-icon tint-orange"><?= $__icon('bolt') ?></span><span>成为陪玩</span>
      </a>
    <?php endif; ?>
    <?php if (is_admin()): ?>
      <a href="<?= e(admin_url('dashboard/index')) ?>" target="_blank">
        <span class="cm-icon tint-gray"><?= $__icon('settings') ?></span><span>管理后台</span>
      </a>
    <?php endif; ?>
    <a href="<?= e(url('auth/logout')) ?>" data-confirm="确定退出登录吗？">
      <span class="cm-icon tint-red"><?= $__icon('logout') ?></span><span>退出登录</span>
    </a>
  </div>
</section>

<?php
// 说明文案由后台「平台设置 → 运营展示 → 成为陪玩的说明」维护，留空则不显示这张卡片
$__intro = trim((string) \App\Setting::get('companion_intro', ''));
if ($__intro !== '' && !is_companion()):
    $__lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $__intro)));
?>
  <section class="card">
    <h2 class="card-title">成为陪玩能做什么</h2>
    <ol class="tips">
      <?php foreach ($__lines as $__line): ?>
        <li><?= e($__line) ?></li>
      <?php endforeach; ?>
    </ol>
    <p class="hint">申请入口在上方「常用功能 → 成为陪玩」。</p>
  </section>
<?php endif; ?>
