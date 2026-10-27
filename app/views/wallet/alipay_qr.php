<?php
/**
 * 支付宝当面付：展示支付二维码
 * @var array $order
 */
$payUrl = (string) $order['pay_url'];
$qrData = rawurlencode($payUrl);
// 二维码用公共图片接口生成（不用装任何库）：
//   主接口用国内的 liantu（大陆访问快），失败自动换 qrserver，再失败就回落到"链接 + 复制"
$qrPrimary = 'https://qr.liantu.com/api.php?text=' . $qrData;
$qrBackup  = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=10&data=' . $qrData;
?>
<div class="page-head">
  <h1>扫码支付</h1>
  <p class="muted">订单号 <?= e($order['order_no']) ?> · 应付 <strong><?= e(yuan((float) $order['amount'])) ?></strong> 元</p>
</div>

<section class="card" style="text-align:center">
  <?php if ($payUrl === ''): ?>
    <p class="muted">没有拿到支付链接，请返回重新发起充值。</p>
  <?php else: ?>
    <p class="muted">打开手机支付宝 →「扫一扫」，扫描下方二维码完成支付</p>

    <div style="display:inline-block;padding:12px;background:#fff;border:1px solid #eceaf3;border-radius:14px;margin:8px 0">
      <img id="qr-img"
           src="<?= e($qrPrimary) ?>"
           data-backup="<?= e($qrBackup) ?>"
           alt="支付宝支付二维码"
           width="280" height="280"
           style="display:block;width:280px;height:280px;object-fit:contain">
    </div>

    <p id="qr-wait" class="muted" style="font-size:13px">支付完成后会自动跳转，请勿关闭本页…</p>

    <div class="btn-group" style="max-width:420px;margin:14px auto 0">
      <a class="btn btn-ghost btn-block" href="<?= e($payUrl) ?>" target="_blank" rel="noopener">在支付宝中打开支付链接</a>
      <button type="button" class="btn btn-ghost btn-block" id="qr-copy">复制支付链接</button>
    </div>

    <form method="post" action="<?= e(url('pay/alipayQuery')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
      <button class="btn btn-primary" type="submit">我已完成支付，刷新到账状态</button>
    </form>

    <p class="hint">
      二维码生成不了也能付：点「在支付宝中打开支付链接」，或点「复制支付链接」发给手机。
      支付宝的异步通知到达后会立刻入账，本页每隔几秒自动检查一次。
    </p>
  <?php endif; ?>

  <p style="margin-top:14px"><a class="btn btn-ghost" href="<?= e(url('wallet/index')) ?>">返回钱包</a></p>
</section>

<?php if ($payUrl !== ''): ?>
<script>
(function () {
  var orderId = <?= (int) $order['id'] ?>;

  // 二维码图片加载失败 → 换备用接口
  var img = document.getElementById('qr-img');
  if (img) {
    img.addEventListener('error', function () {
      var backup = img.getAttribute('data-backup') || '';
      if (backup && img.src !== backup) {
        img.src = backup;
      } else {
        img.style.display = 'none';
      }
    });
  }

  // 复制链接
  var copyBtn = document.getElementById('qr-copy');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var text = <?= json_encode($payUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () { window.alert('支付链接已复制'); });
      } else {
        window.prompt('手动复制支付链接：', text);
      }
    });
  }

  // 轮询到账状态：已支付就回到本页（控制器会提示到账并跳钱包）
  var tries = 0;
  var timer = setInterval(function () {
    tries++;
    if (tries > 150) { clearInterval(timer); return; } // 约 10 分钟后停止

    fetch('<?= e(url('pay/alipayStatus', ['id' => (int) $order['id']])) ?>', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.code === 0 && res.data && res.data.paid) {
          clearInterval(timer);
          var tip = document.getElementById('qr-wait');
          if (tip) tip.textContent = '支付成功，正在跳转…';
          window.location.href = '<?= e(url('wallet/alipayQr', ['id' => (int) $order['id']])) ?>';
        }
      })
      .catch(function () { /* 轮询失败就下次再试 */ });
  }, 4000);
})();
</script>
<?php endif; ?>
