<?php
/**
 * 陪玩中心：还不是陪玩 / 审核中 / 被拒绝时的说明页
 *
 * 这样「我的 → 常用功能 → 陪玩中心」对所有人都有意义，
 * 不会点一下就报错或直接被弹回申请页。
 *
 * @var array|null $profile
 * @var string     $intro
 */
$__audit = $profile ? (int) $profile['audit_status'] : -1;
?>
<div class="page-head">
  <h1>陪玩中心</h1>
  <p class="muted">接单、收益、服务项目都在这里管理</p>
</div>

<section class="card">
  <?php if ($__audit === 0): ?>
    <h2 class="card-title">入驻审核中</h2>
    <p class="muted">你的申请已提交，平台审核通过后就能在这里接单赚钱了。审核结果会显示在「我的」页面。</p>
    <a class="btn btn-ghost btn-block" href="<?= e(url('user/center')) ?>">返回我的</a>
  <?php elseif ($__audit === 2): ?>
    <h2 class="card-title">入驻未通过</h2>
    <p class="muted">
      原因：<?= e((string) ($profile['audit_remark'] ?? '') !== '' ? (string) $profile['audit_remark'] : '资料不符合要求') ?>。
      可以补充资料后重新提交。
    </p>
    <a class="btn btn-primary btn-block" href="<?= e(url('user/apply')) ?>">重新提交申请</a>
  <?php else: ?>
    <h2 class="card-title">你还不是陪玩</h2>
    <p class="muted">成为陪玩后就能上架服务项目、接单赚取收益，服务完成钱直接进钱包。</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('user/apply')) ?>">立即申请成为陪玩</a>
  <?php endif; ?>
</section>

<?php if ($intro !== ''): ?>
  <section class="card">
    <h2 class="card-title">成为陪玩能做什么</h2>
    <ol class="tips">
      <?php foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $intro))) as $__line): ?>
        <li><?= e($__line) ?></li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php endif; ?>
