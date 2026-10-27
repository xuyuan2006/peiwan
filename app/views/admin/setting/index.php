<?php
/**
 * 后台平台设置（分组 + 类型驱动渲染）
 * @var array  $fields           键 => [标题, 类型, 分组, 选项/说明]
 * @var array  $groups           分组键 => 标题
 * @var array  $current          当前设置值
 * @var bool   $mail_ok          SMTP 是否配置完整
 * @var bool   $sms_ok           短信是否配置完整
 * @var string $channel_register 注册实际生效的验证方式
 * @var string $channel_reset    找回密码实际生效的验证方式
 */
$channelText = ['off' => '关闭', 'email' => '邮箱验证码', 'sms' => '手机短信验证码'];

/** 渲染单个设置项（闭包，避免两列重复代码） */
$renderField = function (string $key, array $meta, array $current): void {
    $type = $meta[1];
    $name = e($key);
    if ($type === 'bool') {
        echo '<div class="form-row"><label class="check">'
            . '<input type="checkbox" name="' . $name . '" value="1" '
            . ((string) ($current[$key] ?? '0') === '1' ? 'checked' : '') . '>'
            . e($meta[0]) . '</label>';
        if (!empty($meta[3]) && is_string($meta[3])) {
            echo '<p class="hint">' . e($meta[3]) . '</p>';
        }
        echo '</div>';
        return;
    }

    echo '<div class="form-row"><label>' . e($meta[0]) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea name="' . $name . '" rows="3" maxlength="490">' . e($current[$key] ?? '') . '</textarea>';
    } elseif ($type === 'number') {
        echo '<input type="number" step="0.01" min="0" name="' . $name . '" value="' . e($current[$key] ?? '0') . '">';
    } elseif ($type === 'select') {
        echo '<select name="' . $name . '">';
        foreach ((array) $meta[3] as $optValue => $optLabel) {
            $selected = (string) ($current[$key] ?? '') === (string) $optValue ? 'selected' : '';
            echo '<option value="' . e($optValue) . '" ' . $selected . '>' . e($optLabel) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'image') {
        // 图片类设置（如站点 Logo）：显示当前图 + 上传新图 + 删除回默认
        $cur = (string) ($current[$key] ?? '');
        if ($cur !== '') {
            echo '<img src="' . e(upload_url($cur)) . '" alt="" style="max-width:180px;max-height:90px;display:block;margin:6px 0;border-radius:8px;background:#f2f3f7;padding:4px">';
        }
        echo '<input type="file" name="' . $name . '_file" accept="image/*">';
        if ($cur !== '') {
            echo '<label class="check" style="margin-top:6px"><input type="checkbox" name="' . $name . '_clear" value="1"> 删除当前图片，改回默认</label>';
        }
    } elseif ($type === 'password') {
        $placeholder = ($current[$key] ?? '') !== '' ? '已配置，留空不修改' : '请输入';
        echo '<input type="password" name="' . $name . '" value="" placeholder="' . e($placeholder) . '" autocomplete="new-password">';
    } else {
        echo '<input type="text" name="' . $name . '" value="' . e($current[$key] ?? '') . '" maxlength="490">';
    }
    if (!empty($meta[3]) && is_string($meta[3])) {
        echo '<p class="hint">' . e($meta[3]) . '</p>';
    }
    echo '</div>';
};
?>
<form method="post" action="<?= e(admin_url('setting/save')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="admin-detail-grid">
    <div>
      <?php foreach (['basic', 'trade', 'order', 'display'] as $group): ?>
        <section class="card">
          <h2 class="card-title"><?= e($groups[$group] ?? $group) ?></h2>
          <?php foreach ($fields as $key => $meta): ?>
            <?php if ($meta[2] === $group) { $renderField($key, $meta, $current); } ?>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <section class="card">
        <h2 class="card-title"><?= e($groups['verify'] ?? '验证方式') ?></h2>
        <p class="hint">
          当前生效：注册 = <strong><?= e($channelText[$channel_register] ?? $channel_register) ?></strong>
          · 找回密码 = <strong><?= e($channelText[$channel_reset] ?? $channel_reset) ?></strong>
          （渠道没配置完整时会自动按「关闭」处理，避免用户收不到码）
        </p>
        <?php foreach ($fields as $key => $meta): ?>
          <?php if ($meta[2] === 'verify') { $renderField($key, $meta, $current); } ?>
        <?php endforeach; ?>
      </section>
    </div>

    <aside>
      <section class="card">
        <h2 class="card-title"><?= e($groups['mail'] ?? '邮箱 / SMTP') ?></h2>
        <?php if ($mail_ok): ?>
          <div class="alert alert-success">SMTP 已配置</div>
        <?php else: ?>
          <div class="alert alert-warning">SMTP 尚未配置完整，选「邮箱验证码」时会提示先配置。</div>
        <?php endif; ?>
        <?php foreach ($fields as $key => $meta): ?>
          <?php if ($meta[2] === 'mail') { $renderField($key, $meta, $current); } ?>
        <?php endforeach; ?>
        <p class="hint">QQ / 163 邮箱请在邮箱设置里开启 SMTP 并获取「授权码」，把授权码填到密码栏。</p>
      </section>

      <section class="card">
        <h2 class="card-title"><?= e($groups['sms'] ?? '短信（阿里云）') ?></h2>
        <?php if ($sms_ok): ?>
          <div class="alert alert-success">短信服务已配置</div>
        <?php else: ?>
          <div class="alert alert-warning">短信尚未配置完整，选「手机短信验证码」时会提示先配置。</div>
        <?php endif; ?>
        <?php foreach ($fields as $key => $meta): ?>
          <?php if ($meta[2] === 'sms') { $renderField($key, $meta, $current); } ?>
        <?php endforeach; ?>
        <p class="hint">需要先到阿里云开通短信服务、申请签名与模板（模板内容里要有 <code>${code}</code>）。</p>
      </section>

      <section class="card">
        <h2 class="card-title">保存</h2>
        <button class="btn btn-primary btn-block" type="submit">保存设置</button>
      </section>
    </aside>
  </div>
</form>

<div class="admin-detail-grid">
  <section class="card">
    <h2 class="card-title">发送测试邮件</h2>
    <form method="post" action="<?= e(admin_url('setting/testMail')) ?>" class="filter-bar" style="margin:0;padding:0;border:none;background:transparent;box-shadow:none">
      <?= csrf_field() ?>
      <label style="flex:1">接收测试邮件的邮箱
        <input type="text" name="to" placeholder="例如 you@example.com" required>
      </label>
      <div class="actions">
        <button class="btn btn-primary btn-sm" type="submit">发送测试</button>
      </div>
    </form>
    <p class="hint">建议先保存 SMTP 配置再发测试邮件；收不到请检查垃圾箱与授权码。</p>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">发送测试短信</h2>
      <form method="post" action="<?= e(admin_url('setting/testSms')) ?>" class="filter-bar" style="margin:0;padding:0;border:none;background:transparent;box-shadow:none">
        <?= csrf_field() ?>
        <label style="flex:1">接收测试短信的手机号
          <input type="text" name="phone" placeholder="例如 13800000000" required>
        </label>
        <div class="actions">
          <button class="btn btn-primary btn-sm" type="submit">发送测试</button>
        </div>
      </form>
      <p class="hint">需要先保存短信配置；测试短信使用与注册相同的模板，请确认模板里有 <code>${code}</code>。</p>
    </section>

    <section class="card">
      <h2 class="card-title">支付渠道</h2>
      <p class="hint">
        支付渠道即将迁到独立的「支付设置」页（人工转账 / 易支付 / 支付宝当面付）；
        当前仍由 <code>config/config.php</code> 的 <code>pay</code> 节点控制：
        <?= \App\Epay::enabled() ? '<span class="status status-success">易支付已启用</span>' : '<span class="status status-warn">人工审核充值</span>' ?>
      </p>
    </section>
  </aside>
</div>
