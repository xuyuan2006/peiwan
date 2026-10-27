<?php
/**
 * 注册
 * @var string $verify_channel off / email / sms
 * @var string $verify_label   邮箱 / 手机号
 */
?>
<div class="auth-wrap">
  <div class="card auth-card">
    <h1 class="card-title">注册</h1>
    <p class="muted">注册后可以点单、充值，也可以申请成为陪玩</p>

    <?php if ($verify_channel !== 'off'): ?>
      <div class="alert alert-info">
        本平台注册需要<?= e($verify_label) ?>验证码，请先获取验证码再提交。
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('auth/register')) ?>">
      <?= csrf_field() ?>
      <!-- 账号由服务端自动分配，用户不需要填写 -->
      <div class="form-row">
        <label>昵称 <span class="req">*</span></label>
        <input type="text" name="nickname" value="<?= e(old('nickname')) ?>" placeholder="这是别人看到的名字" required>
      </div>

      <div class="form-row">
        <label>邮箱 <span class="req">*</span></label>
        <div class="code-row">
          <input type="text" name="email" id="reg-email" value="<?= e(old('email')) ?>" placeholder="用于接收验证码、找回密码" required>
          <?php if ($verify_channel === 'email'): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-send-code
                    data-url="<?= e(url('auth/sendCode')) ?>"
                    data-target="#reg-email"
                    data-scene="register"
                    data-token="<?= e(csrf_token()) ?>">发送验证码</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <label>手机号 <span class="req">*</span></label>
        <div class="code-row">
          <input type="text" name="phone" id="reg-phone" value="<?= e(old('phone')) ?>" placeholder="也是登录时用的账号" required>
          <?php if ($verify_channel === 'sms'): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-send-code
                    data-url="<?= e(url('auth/sendCode')) ?>"
                    data-target="#reg-phone"
                    data-scene="register"
                    data-token="<?= e(csrf_token()) ?>">发送验证码</button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($verify_channel !== 'off'): ?>
        <div class="form-row">
          <label><?= e($verify_label) ?>验证码 <span class="req">*</span></label>
          <input type="text" name="email_code" maxlength="10" placeholder="6 位数字，<?= (int) \App\VerifyCode::ttlMinutes() ?> 分钟内有效" required>
        </div>
      <?php endif; ?>

      <div class="form-row">
        <label>性别</label>
        <select name="gender">
          <option value="0">保密</option>
          <option value="1" <?= (int) old('gender') === 1 ? 'selected' : '' ?>>男</option>
          <option value="2" <?= (int) old('gender') === 2 ? 'selected' : '' ?>>女</option>
        </select>
      </div>
      <div class="form-row">
        <label>密码 <span class="req">*</span></label>
        <input type="password" name="password" placeholder="至少 6 位" required>
      </div>
      <div class="form-row">
        <label>确认密码 <span class="req">*</span></label>
        <input type="password" name="password_confirm" placeholder="再输入一次密码" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">注册</button>
    </form>

    <p class="auth-foot">已有账号？<a href="<?= e(url('auth/login')) ?>">去登录</a></p>
  </div>
</div>
