<?php
/**
 * 申请提现
 * @var array $wallet
 * @var float $min
 * @var float $fee_rate
 */
?>
<div class="page-head">
  <h1>申请提现</h1>
  <p class="muted">可用余额 <?= e(money((float) $wallet['balance'])) ?> 元 · 冻结中 <?= e(money((float) $wallet['frozen'])) ?> 元</p>
</div>

<div class="detail-grid">
  <section class="card">
    <form method="post" action="<?= e(url('wallet/withdraw')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>提现金额（元）</label>
        <input type="number" name="amount" id="withdraw-amount" min="<?= e(money($min)) ?>" step="0.01"
               placeholder="最低 <?= e(money($min)) ?> 元" data-fee-rate="<?= e(money($fee_rate)) ?>" required>
        <p class="hint">手续费 <?= e(money($fee_rate)) ?>%，实际到账：<strong id="withdraw-real">-</strong> 元</p>
      </div>

      <div class="form-row">
        <label>收款方式</label>
        <select name="account_type" required>
          <option value="alipay">支付宝</option>
          <option value="wechat">微信</option>
          <option value="bank">银行卡</option>
        </select>
      </div>
      <div class="form-row">
        <label>收款人姓名</label>
        <input type="text" name="account_name" maxlength="50" placeholder="需与账号实名一致" required>
      </div>
      <div class="form-row">
        <label>收款账号</label>
        <input type="text" name="account_no" maxlength="100" placeholder="支付宝/微信账号或银行卡号" required>
      </div>

      <button class="btn btn-primary btn-block" type="submit">提交提现申请</button>
    </form>
  </section>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">提现说明</h2>
      <ol class="tips">
        <li>最低提现 <?= e(money($min)) ?> 元，手续费 <?= e(money($fee_rate)) ?>%。</li>
        <li>提交后金额会先冻结，平台审核通过后 1~3 个工作日打款。</li>
        <li>审核驳回时金额会自动解冻回到可用余额。</li>
        <li>请确保收款账号信息准确，因信息错误导致的损失需自行承担。</li>
      </ol>
    </section>
  </aside>
</div>
