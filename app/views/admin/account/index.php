<?php
/**
 * 后台账号设置
 * @var array $me
 */
?>
<div class="page-head">
  <h1>账号设置</h1>
  <p class="muted">这里是<b>你自己</b>的登录账号。要重置其他用户/陪玩的密码，请到「用户管理 → 用户详情 → 重置密码」。</p>
</div>

<div class="admin-detail-grid">
  <section class="card">
    <h2 class="card-title">修改账号与密码</h2>
    <form method="post" action="<?= e(admin_url('account/save')) ?>">
      <?= csrf_field() ?>

      <div class="form-row">
        <label>登录账号 <span class="req">*</span></label>
        <input type="text" name="username" maxlength="20" value="<?= e((string) $me['username']) ?>" required>
        <p class="hint">4~20 位字母、数字或下划线；改完请记牢，下次要用新账号登录。</p>
      </div>

      <div class="form-row">
        <label>昵称</label>
        <input type="text" name="nickname" maxlength="50" value="<?= e((string) $me['nickname']) ?>">
      </div>

      <div class="form-row">
        <label>邮箱</label>
        <input type="text" name="email" value="<?= e((string) $me['email']) ?>" placeholder="用于接收验证码、找回密码">
      </div>

      <div class="form-row">
        <label>手机号</label>
        <input type="text" name="phone" value="<?= e((string) $me['phone']) ?>">
      </div>

      <div class="form-row">
        <label>当前密码</label>
        <input type="password" name="old_password" placeholder="改账号或改密码时必须填写" autocomplete="current-password">
      </div>

      <div class="form-row">
        <label>新密码</label>
        <input type="password" name="new_password" placeholder="不改就留空，至少 6 位" autocomplete="new-password">
      </div>

      <div class="form-row">
        <label>确认新密码</label>
        <input type="password" name="new_password_confirm" placeholder="再输入一次新密码" autocomplete="new-password">
      </div>

      <button class="btn btn-primary btn-block" type="submit">保存</button>
    </form>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">账号信息</h2>
      <ul class="ticket-meta-list">
        <li><span>账号ID</span><strong>#<?= (int) $me['id'] ?></strong></li>
        <li><span>身份</span><strong><?= e($me['role'] === 'admin' ? '管理员' : (string) $me['role']) ?></strong></li>
        <li><span>注册时间</span><strong><?= e((string) $me['created_at']) ?></strong></li>
        <li><span>最近更新</span><strong><?= e((string) $me['updated_at']) ?></strong></li>
      </ul>
    </section>

    <section class="card">
      <h2 class="card-title">安全提示</h2>
      <ol class="tips">
        <li>修改登录账号或密码都需要先填<b>当前密码</b>，防止他人拿到你的会话后直接改密。</li>
        <li>忘记管理员密码时：在数据库里把 <code>users</code> 表对应行的 <code>password</code> 换成一个新的 bcrypt 串，或重新执行 <code>/install.php</code> 的「重置管理员密码」。</li>
        <li>建议开启邮箱/短信验证码（平台设置 → 验证方式），这样忘记密码也能自助找回。</li>
      </ol>
    </section>
  </aside>
</div>
