<?php $__flashes = take_flashes(); ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录 - <?= e(site_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-login-body">
<div class="auth-wrap">
  <div class="card auth-card">
    <h1 class="card-title">管理后台</h1>
    <p class="muted"><?= e(site_name()) ?> · 仅限管理员登录</p>

    <?php foreach ($__flashes as $__f): ?>
      <div class="alert alert-<?= e($__f['type']) ?>"><?= e($__f['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(admin_url('auth/login')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>管理员账号</label>
        <input type="text" name="account" placeholder="账号或手机号" required autofocus>
      </div>
      <div class="form-row">
        <label>密码</label>
        <input type="password" name="password" placeholder="请输入密码" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">登录后台</button>
    </form>

    <p class="auth-foot"><a href="<?= e(url('home/index')) ?>">← 返回前台</a></p>
  </div>
</div>
</body>
</html>
