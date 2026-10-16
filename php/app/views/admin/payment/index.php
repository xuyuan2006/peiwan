<?php
/**
 * 后台支付设置
 * @var array  $fields
 * @var array  $current
 * @var string $active     当前实际生效的渠道
 * @var bool   $epay_ok
 * @var bool   $alipay_ok
 * @var string $epay_notify / $epay_return / $ali_notify / $ali_return
 */
$channelText = ['manual' => '人工转账', 'epay' => '易支付', 'alipay' => '支付宝'];
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>支付设置</h1>
      <p class="muted">
        当前生效渠道：<span class="badge badge-success"><?= e($channelText[$active] ?? $active) ?></span>
        （参数没配完整时会自动退回「人工转账」）
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(admin_url('recharge/index')) ?>">充值审核</a>
    </div>
  </div>
</div>

<form method="post" action="<?= e(admin_url('payment/save')) ?>">
  <?= csrf_field() ?>

  <div class="admin-detail-grid">
    <section class="card">
      <h2 class="card-title">支付渠道</h2>
      <?php foreach ($fields as $key => $meta): ?>
        <?php if ($key !== 'pay_channel') { continue; } ?>
        <div class="form-row">
          <label><?= e($meta[0]) ?></label>
          <select name="<?= e($key) ?>">
            <?php foreach ($meta[3] as $optValue => $optLabel): ?>
              <option value="<?= e($optValue) ?>" <?= (string) ($current[$key] ?? 'manual') === (string) $optValue ? 'selected' : '' ?>><?= e($optLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint"><?= e($meta[2]) ?></p>
        </div>
      <?php endforeach; ?>

      <table class="table table-plain">
        <tr>
          <th>易支付状态</th>
          <td><?= $epay_ok ? '<span class="status status-success">参数已配置</span>' : '<span class="status status-warn">参数未配置完整</span>' ?></td>
        </tr>
        <tr>
          <th>支付宝状态</th>
          <td><?= $alipay_ok ? '<span class="status status-success">参数已配置</span>' : '<span class="status status-warn">参数未配置完整</span>' ?></td>
        </tr>
      </table>
    </section>

    <aside>
      <section class="card">
        <h2 class="card-title">回调地址（填到支付平台后台）</h2>
        <div class="form-row">
          <label>易支付 · 异步通知</label>
          <input type="text" value="<?= e($epay_notify) ?>" readonly onclick="this.select()">
        </div>
        <div class="form-row">
          <label>易支付 · 同步跳回</label>
          <input type="text" value="<?= e($epay_return) ?>" readonly onclick="this.select()">
        </div>
        <div class="form-row">
          <label>支付宝 · 异步通知</label>
          <input type="text" value="<?= e($ali_notify) ?>" readonly onclick="this.select()">
        </div>
        <div class="form-row">
          <label>支付宝 · 同步跳回</label>
          <input type="text" value="<?= e($ali_return) ?>" readonly onclick="this.select()">
        </div>
        <p class="hint">点击输入框可全选复制。支付宝需要把异步通知地址填入应用或交易接口配置。</p>
      </section>
    </aside>
  </div>

  <div class="admin-detail-grid">
    <section class="card">
      <h2 class="card-title">易支付参数</h2>
      <?php foreach (['epay_api_url', 'epay_pid', 'epay_key'] as $key): ?>
        <?php $meta = $fields[$key]; ?>
        <div class="form-row">
          <label><?= e($meta[0]) ?></label>
          <?php if ($meta[1] === 'password'): ?>
            <input type="password" name="<?= e($key) ?>" value="" placeholder="<?= ($current[$key] ?? '') !== '' ? '已配置，留空不修改' : '请输入商户密钥' ?>" autocomplete="new-password">
          <?php else: ?>
            <input type="text" name="<?= e($key) ?>" value="<?= e($current[$key] ?? '') ?>" maxlength="490">
          <?php endif; ?>
          <?php if ($meta[2] !== ''): ?><p class="hint"><?= e($meta[2]) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <p class="hint">易支付走 <code>submit.php</code> 协议，异步通知按 <code>pid + 参数 + key</code> 做 MD5 校验。</p>
    </section>

    <aside>
      <section class="card">
        <h2 class="card-title">支付宝参数</h2>
        <?php foreach (['alipay_app_id', 'alipay_private_key', 'alipay_public_key', 'alipay_gateway', 'alipay_mode'] as $key): ?>
          <?php $meta = $fields[$key]; ?>
          <div class="form-row">
            <label><?= e($meta[0]) ?></label>
            <?php if ($meta[1] === 'select'): ?>
              <select name="<?= e($key) ?>">
                <?php foreach ($meta[3] as $optValue => $optLabel): ?>
                  <option value="<?= e($optValue) ?>" <?= (string) ($current[$key] ?? '') === (string) $optValue ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($meta[1] === 'password'): ?>
              <input type="password" name="<?= e($key) ?>" value="" placeholder="<?= ($current[$key] ?? '') !== '' ? '已配置，留空不修改' : '请输入' ?>" autocomplete="new-password">
            <?php else: ?>
              <input type="text" name="<?= e($key) ?>" value="<?= e($current[$key] ?? '') ?>" maxlength="490">
            <?php endif; ?>
            <?php if ($meta[2] !== ''): ?><p class="hint"><?= e($meta[2]) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>

        <button class="btn btn-primary btn-block" type="submit">保存支付设置</button>
      </section>
    </aside>
  </div>
</form>

<div class="admin-detail-grid">
  <section class="card">
    <h2 class="card-title">测试支付宝密钥</h2>
    <form method="post" action="<?= e(admin_url('payment/testKeys')) ?>" class="filter-bar" style="margin:0;padding:0;border:none;background:transparent;box-shadow:none">
      <?= csrf_field() ?>
      <div class="actions">
        <button class="btn btn-primary btn-sm" type="submit">检测密钥</button>
      </div>
    </form>
    <p class="hint">只做本地检测：应用私钥能否签名、支付宝公钥能否解析，不会发起真实交易。</p>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">接入须知</h2>
      <ol class="tips">
        <li><strong>人工转账</strong>：用户在充值页提交金额与凭证，客服在「充值审核」确认到账。</li>
        <li><strong>易支付</strong>：需在第三方支付平台配置上面的「异步通知 / 同步跳回」地址。</li>
        <li><strong>支付宝</strong>：开放平台创建应用并签约「电脑网站支付」「手机网站支付」或「当面付」，密钥用<strong>公钥模式</strong>（非证书模式）。</li>
        <li>当面付返回的是 <code>qr_code</code> 直链，页面会展示成可点击、可复制的链接；如需渲染成二维码图片，把任意二维码 JS 库放到 <code>public/assets/js/</code> 即可（页面会自动识别）。</li>
        <li>资金入账只认<strong>验签通过的异步通知</strong>或交易查询结果，回调接口不会重复入账。</li>
      </ol>
    </section>
  </aside>
</div>
