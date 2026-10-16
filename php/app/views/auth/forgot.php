<?php
/**
 * 找回密码
 * @var string $channel      off / email / sms
 * @var string $verify_label 邮箱 / 手机号
 */
?>
<div class="auth-wrap">
  <div class="card auth-card">
    <h1 class="card-title">找回密码</h1>

    <?php if ($channel === 'off'): ?>
      <div class="alert alert-warning">
        平台未开启「验证码找回密码」。请联系客服协助重置，或登录后在「我的 → 编辑资料」里自行修改密码。
      </div>
      <p class="auth-foot"><a href="<?= e(url('auth/login')) ?>">← 返回登录</a></p>
    <?php else: ?>
      <p class="muted">填写注册绑定的<?= e($verify_label) ?>，收验证码后设置新密码</p>

      <form method="post" action="<?= e(url('auth/forgot')) ?>">
        <?= csrf_field() ?>

        <?php if ($channel === 'email'): ?>
          <div class="form-row">
            <label>邮箱 <span class="req">*</span></label>
            <div class="code-row">
              <input type="text" name="email" id="reset-email" value="<?= e(old('email')) ?>" placeholder="注册时绑定的邮箱" required>
              <button type="button" class="btn btn-ghost btn-sm" data-send-code
                      data-url="<?= e(url('auth/sendCode')) ?>"
                      data-target="#reset-email"
                      data-scene="reset"
                      data-token="<?= e(csrf_token()) ?>">发送验证码</button>
            </div>
          </div>
        <?php else: ?>
          <div class="form-row">
            <label>手机号 <span class="req">*</span></label>
            <div class="code-row">
              <input type="text" name="phone" id="reset-phone" value="<?= e(old('phone')) ?>" placeholder="注册时绑定的手机号" required>
              <button type="button" class="btn btn-ghost btn-sm" data-send-code
                      data-url="<?= e(url('auth/sendCode')) ?>"
                      data-target="#reset-phone"
                      data-scene="reset"
                      data-token="<?= e(csrf_token()) ?>">发送验证码</button>
            </div>
          </div>
        <?php endif; ?>

        <div class="form-row">
          <label>验证码 <span class="req">*</span></label>
          <input type="text" name="email_code" maxlength="10" placeholder="6 位数字，<?= (int) \App\VerifyCode::ttlMinutes() ?> 分钟内有效" required>
        </div>
        <div class="form-row">
          <label>新密码 <span class="req">*</span></label>
          <input type="password" name="password" placeholder="至少 6 位" required>
        </div>
        <div class="form-row">
          <label>确认新密码 <span class="req">*</span></label>
          <input type="password" name="password_confirm" placeholder="再输入一次新密码" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">重置密码</button>
      </form>

      <p class="auth-foot">想起来了？<a href="<?= e(url('auth/login')) ?>">去登录</a></p>
    <?php endif; ?>
  </div>
</div>
