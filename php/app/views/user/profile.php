<?php
/**
 * 个人中心
 * @var array|null $profile
 */
$__me = user();
?>
<div class="page-head">
  <h1>个人中心</h1>
  <p class="muted">账号 ID：<?= (int) $__me['id'] ?> · 注册时间 <?= e(substr((string) $__me['created_at'], 0, 10)) ?></p>
</div>

<div class="detail-grid">
  <section class="card">
    <h2 class="card-title">基本资料</h2>
    <form method="post" action="<?= e(url('user/profile')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-row avatar-row">
        <label>头像</label>
        <div class="avatar-edit">
          <img src="<?= e(avatar_url($__me['avatar'], (string) $__me['nickname'])) ?>" alt="" id="avatar-preview">
          <input type="file" name="avatar" accept="image/*" id="avatar-input">
        </div>
      </div>
      <div class="form-row">
        <label>昵称</label>
        <input type="text" name="nickname" value="<?= e($__me['nickname']) ?>" maxlength="30" required>
      </div>
      <div class="form-row">
        <label>手机号</label>
        <input type="text" name="phone" value="<?= e($__me['phone']) ?>" placeholder="选填">
      </div>
      <div class="form-row">
        <label>邮箱</label>
        <input type="text" name="email" value="<?= e($__me['email'] ?? '') ?>" placeholder="用于找回密码，建议填写常用邮箱">
        <?php if (\App\VerifyCode::isEnabled('reset')): ?>
          <p class="hint">平台已开启验证码找回密码，请填写<?= e(\App\VerifyCode::targetLabel(\App\VerifyCode::channelFor('reset'))) ?>，之后可用「忘记密码」自助重置。</p>
        <?php endif; ?>
      </div>
      <div class="form-row">
        <label>性别</label>
        <select name="gender">
          <option value="0" <?= (int) $__me['gender'] === 0 ? 'selected' : '' ?>>保密</option>
          <option value="1" <?= (int) $__me['gender'] === 1 ? 'selected' : '' ?>>男</option>
          <option value="2" <?= (int) $__me['gender'] === 2 ? 'selected' : '' ?>>女</option>
        </select>
      </div>
      <div class="form-row">
        <label>当前密码</label>
        <input type="password" name="old_password" placeholder="仅修改密码时需要填写" autocomplete="current-password">
      </div>
      <div class="form-row">
        <label>修改密码</label>
        <input type="password" name="password" placeholder="不修改请留空，至少 6 位" autocomplete="new-password">
      </div>
      <button class="btn btn-primary" type="submit">保存资料</button>
    </form>
  </section>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">身份与权限</h2>
      <ul class="stat-list">
        <li><span>当前身份</span><strong><?= e($__me['role'] === 'companion' ? '陪玩' : ($__me['role'] === 'admin' ? '管理员' : '普通用户')) ?></strong></li>
        <li><span>信用分</span><strong><?= (int) $__me['credit_score'] ?></strong></li>
        <li><span>钱包余额</span><strong><?= e(money((float) \App\Wallet::balance((int) $__me['id']))) ?> 元</strong></li>
      </ul>

      <?php if ($profile): ?>
        <p class="hint">入驻审核状态：<strong><?= e(audit_status_text((int) $profile['audit_status'])) ?></strong>
          <?php if ($profile['audit_remark'] !== ''): ?>（<?= e($profile['audit_remark']) ?>）<?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="btn-group">
        <?php if ($__me['role'] === 'companion'): ?>
          <a class="btn btn-primary btn-block" href="<?= e(url('companion/center')) ?>">进入陪玩中心</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-block" href="<?= e(url('user/apply')) ?>"><?= $profile ? '修改入驻资料' : '申请成为陪玩' ?></a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('order/index')) ?>">我的订单</a>
        <a class="btn btn-ghost btn-block" href="<?= e(url('wallet/index')) ?>">我的钱包</a>
      </div>
    </section>
  </aside>
</div>
