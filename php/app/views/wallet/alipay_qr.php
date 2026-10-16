<?php
/**
 * 支付宝当面付：扫码/点链接支付页
 * @var array $order
 */
$payUrl = (string) $order['pay_url'];
?>
<div class="page-head">
  <h1>扫码支付</h1>
  <p class="muted">订单号 <?= e($order['order_no']) ?> · 应付 <strong><?= e(yuan((float) $order['amount'])) ?></strong> 元</p>
</div>

<section class="card" style="text-align:center">
  <div id="qr-box" data-qr="<?= e($payUrl) ?>" style="min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px">
    <?php if ($payUrl === ''): ?>
      <p class="muted">没有拿到支付链接，请返回重新发起充值。</p>
    <?php else: ?>
      <p class="muted">用手机支付宝扫描下方内容，或点击按钮在支付宝中打开</p>
      <p style="word-break:break-all;font-size:12.5px;color:#55535f;max-width:420px"><?= e($payUrl) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($payUrl !== ''): ?>
    <div class="btn-group" style="max-width:420px;margin:16px auto 0">
      <a class="btn btn-primary btn-block" href="<?= e($payUrl) ?>" target="_blank" rel="noopener">在支付宝中打开支付链接</a>
      <button type="button" class="btn btn-ghost btn-block" id="qr-copy">复制支付链接</button>
    </div>

    <form method="post" action="<?= e(url('pay/alipayQuery')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
      <button class="btn btn-primary" type="submit">我已完成支付，刷新到账状态</button>
    </form>

    <p class="hint">
      付完后若余额没变，点上面的按钮确认；系统也会在支付宝通知到达时自动入账。
      当面付页面如果没显示二维码图片，可把任意二维码 JS 库（如 qrcode-generator）放到
      <code>public/assets/js/</code> 并在本页引入，页面会自动把链接渲染成二维码。
    </p>
  <?php endif; ?>

  <p style="margin-top:14px"><a class="btn btn-ghost" href="<?= e(url('wallet/index')) ?>">返回钱包</a></p>
</section>

<script>
(function () {
  var box = document.getElementById('qr-box');
  if (box && typeof qrcode === 'function') {
    try {
      var qr = qrcode(0, 'M');
      qr.addData(box.dataset.qr);
      qr.make();
      box.innerHTML = qr.createImgTag(6);
    } catch (e) { /* 库不可用就保持文本展示 */ }
  }

  var btn = document.getElementById('qr-copy');
  if (btn && box) {
    btn.addEventListener('click', function () {
      var text = box.dataset.qr || '';
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { window.alert('支付链接已复制'); });
      } else {
        window.prompt('手动复制支付链接：', text);
      }
    });
  }
})();
</script>
