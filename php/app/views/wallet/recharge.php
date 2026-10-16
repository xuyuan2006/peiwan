<?php
/**
 * 充值
 * @var string $channel      manual / epay / alipay
 * @var string $alipay_mode  page / wap / precreate
 * @var string $service_phone
 */
$channelText = [
    'manual' => '人工转账（提交后由客服核对入账）',
    'epay'   => '在线支付（跳转第三方收银台，支持微信 / 支付宝）',
    'alipay' => '支付宝在线支付',
];
?>
<div class="page-head">
  <h1>账户充值</h1>
  <p class="muted">当前渠道：<?= e($channelText[$channel] ?? $channel) ?></p>
</div>

<div class="detail-grid">
  <section class="card">
    <form method="post" action="<?= e(url('wallet/recharge')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>充值金额（元）</label>
        <div class="chip-row amount-chips">
          <?php foreach ([50, 100, 200, 500, 1000] as $amt): ?>
            <button type="button" class="chip amount-chip" data-amount="<?= $amt ?>"><?= $amt ?> 元</button>
          <?php endforeach; ?>
        </div>
        <input type="number" name="amount" id="recharge-amount" min="1" max="50000" step="0.01" placeholder="请输入充值金额" required>
      </div>

      <?php if ($channel === 'manual'): ?>
        <div class="form-row">
          <label>转账凭证（选填）</label>
          <input type="file" name="voucher" accept="image/*">
          <p class="hint">请先联系客服获取收款方式并完成转账，然后上传转账截图，客服核对后为你入账。</p>
        </div>
      <?php else: ?>
        <div class="alert alert-info">
          提交后会跳转到在线支付页面，支付成功后余额自动到账。
          <?php if ($channel === 'alipay' && $alipay_mode === 'precreate'): ?>
            当面付模式会展示一个支付链接（可复制/可扫码）。
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="form-row">
        <label>备注（选填）</label>
        <input type="text" name="remark" maxlength="200" placeholder="例如：已转账，微信昵称 xxx">
      </div>

      <button class="btn btn-primary btn-block" type="submit">
        <?= $channel === 'manual' ? '提交充值申请' : '去支付' ?>
      </button>
    </form>
  </section>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">充值说明</h2>
      <ol class="tips">
        <li>充值金额仅用于站内下单消费，不可直接提现。</li>
        <li><?= $channel === 'manual' ? '人工充值需客服核对转账，通常 10 分钟内到账。' : '在线支付成功后余额会自动到账；若未到账可点「我已完成支付」刷新。' ?></li>
        <li>如有疑问请联系客服<?= $service_phone !== '' ? '：' . e($service_phone) : '' ?>。</li>
        <li>禁止通过充值进行洗钱等违法行为，平台有权冻结异常账户。</li>
      </ol>
    </section>
  </aside>
</div>
