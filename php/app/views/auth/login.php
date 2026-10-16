<div class="auth-wrap">
  <div class="card auth-card">
    <h1 class="card-title">登录</h1>
    <p class="muted">登录后即可下单、查看订单与钱包</p>

    <form method="post" action="<?= e(url('auth/login')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>账号 / 手机号 / 邮箱</label>
        <input type="text" name="account" value="<?= e(old('account')) ?>" placeholder="账号、手机号或邮箱都可以" required autofocus>
      </div>
      <div class="form-row">
        <label>密码</label>
        <input type="password" name="password" placeholder="请输入密码" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">登录</button>
    </form>

    <p class="auth-foot">还没有账号？<a href="<?= e(url('auth/register')) ?>">立即注册</a> · <a href="<?= e(url('auth/forgot')) ?>">忘记密码</a></p>
  </div>
</div>
