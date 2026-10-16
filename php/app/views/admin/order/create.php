<?php
/**
 * 后台代客下单
 * @var array $users       可选下单用户
 * @var array $companions  已通过审核的陪玩
 * @var array $skills      上架中的服务项目
 * @var int   $preset_user
 * @var int   $preset_companion
 */
?>
<div class="page-head">
  <h1>代客下单</h1>
  <p class="muted">用于客服补单、线下成交补录、纠纷调解等场景。订单金额 = 单价 × 数量；不选服务项目时可只填单价，按「一口价」建单。</p>
</div>

<div class="admin-detail-grid">
  <section class="card">
    <form method="post" action="<?= e(admin_url('order/saveByAdmin')) ?>" id="admin-order-form">
      <?= csrf_field() ?>

      <div class="form-row">
        <label>下单用户 <span class="req">*</span></label>
        <select name="user_id" required>
          <option value="">请选择用户</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= $preset_user === (int) $u['id'] ? 'selected' : '' ?>>
              <?= e($u['nickname']) ?>（#<?= (int) $u['id'] ?><?= $u['phone'] !== '' ? ' · ' . e($u['phone']) : '' ?>）
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label>陪玩 <span class="req">*</span></label>
        <select name="companion_id" id="ao-companion" required>
          <option value="">请选择陪玩</option>
          <?php foreach ($companions as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $preset_companion === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['nickname']) ?>（#<?= (int) $c['id'] ?> · <?= e(number_format((float) $c['score'], 1)) ?> 分）
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label>服务项目（选填）</label>
        <select name="skill_id" id="ao-skill">
          <option value="0" data-companion="0" data-price="">不指定（按一口价）</option>
          <?php foreach ($skills as $s): ?>
            <option value="<?= (int) $s['id'] ?>" data-companion="<?= (int) $s['companion_id'] ?>" data-price="<?= e(money((float) $s['price'])) ?>">
              #<?= (int) $s['id'] ?> · <?= e($s['title']) ?>（<?= e(yuan((float) $s['price'])) ?> / <?= e(service_unit_suffix((string) $s['service_type'])) ?>）
            </option>
          <?php endforeach; ?>
        </select>
        <p class="hint">选择陪玩后，这里只显示该陪玩上架中的项目；选中项目会自动带出单价。</p>
      </div>

      <div class="form-row">
        <label>数量</label>
        <input type="number" name="quantity" id="ao-qty" min="1" max="99" value="<?= (int) old('quantity', 1) ?>">
        <p class="hint">一口价项目固定按 1 单计。</p>
      </div>

      <div class="form-row">
        <label>单价（元） <span class="req">*</span></label>
        <input type="number" name="unit_price" id="ao-price" min="0.01" step="0.01" value="<?= e(old('unit_price')) ?>" placeholder="可自定义，留空则用服务项目原价" required>
        <p class="hint">订单金额：<strong id="ao-total">-</strong></p>
      </div>

      <div class="form-row">
        <label>备注</label>
        <input type="text" name="remark" maxlength="200" value="<?= e(old('remark')) ?>" placeholder="例如：客服补单 / 线下已收款 / 纠纷补偿">
      </div>

      <div class="form-row">
        <label class="check">
          <input type="checkbox" name="pay_now" value="1"> 立即用「下单用户」的钱包余额支付
        </label>
        <p class="hint">不勾选则订单为「待支付」，用户可在前台自行支付；余额不足时支付会失败并整体回滚，不会留下订单。</p>
      </div>

      <button class="btn btn-primary btn-block" type="submit">创建订单</button>
    </form>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">使用须知</h2>
      <ol class="tips">
        <li>代客下单会写一条订单日志，操作人与金额都会记录在「操作日志」里，便于对账追责。</li>
        <li>自定义单价会绕开服务项目原价，请勿用来规避平台抽成。</li>
        <li>勾选「立即支付」会真实扣除用户余额，扣款后金额进入平台托管，订单完成后才结算给陪玩。</li>
        <li>下单后可到订单详情里强制完成、关闭退款或审核退款。</li>
      </ol>
      <p style="margin-top:12px"><a class="btn btn-ghost btn-block" href="<?= e(admin_url('order/index')) ?>">返回订单列表</a></p>
    </section>
  </aside>
</div>

<script>
(function () {
  var companion = document.getElementById('ao-companion');
  var skill     = document.getElementById('ao-skill');
  var priceInput = document.getElementById('ao-price');
  var qtyInput   = document.getElementById('ao-qty');
  var totalEl    = document.getElementById('ao-total');

  function filterSkills() {
    if (!companion || !skill) return;
    var cid = companion.value;
    var first = true;
    Array.prototype.forEach.call(skill.options, function (opt) {
      var owner = opt.getAttribute('data-companion');
      var show = (opt.value === '0') || (owner && owner === cid);
      opt.hidden = !show;
      opt.disabled = !show;
      if (show && opt.value !== '0' && first) { first = false; }
    });
    if (skill.selectedOptions[0] && skill.selectedOptions[0].disabled) {
      skill.value = '0';
    }
  }

  function fillPrice() {
    if (!skill || !priceInput) return;
    var opt = skill.options[skill.selectedIndex];
    var price = opt ? opt.getAttribute('data-price') : '';
    if (price) { priceInput.value = price; }
    render();
  }

  function render() {
    if (!totalEl) return;
    var p = parseFloat((priceInput && priceInput.value) || '0');
    var q = parseInt((qtyInput && qtyInput.value) || '1', 10);
    if (isNaN(p) || p <= 0) { totalEl.textContent = '-'; return; }
    if (isNaN(q) || q < 1) q = 1;
    totalEl.textContent = '¥' + (Math.round(p * q * 100) / 100).toFixed(2);
  }

  if (companion) companion.addEventListener('change', filterSkills);
  if (skill) skill.addEventListener('change', fillPrice);
  if (priceInput) priceInput.addEventListener('input', render);
  if (qtyInput) qtyInput.addEventListener('input', render);

  filterSkills();
  render();
})();
</script>
