<?php
/**
 * 后台发布接单任务（进入接单大厅，由陪玩抢单）
 * @var array $users
 * @var array $games
 * @var int   $pool_count
 * @var int   $preset_user
 */
?>
<div class="page-head">
  <h1>发布接单任务</h1>
  <p class="muted">
    任务发布并付款后会进入 <strong>接单大厅</strong>，陪玩自己抢单；当前大厅待接单：
    <span class="badge badge-success"><?= (int) $pool_count ?> 单</span>
  </p>
</div>

<div class="admin-detail-grid">
  <section class="card">
    <form method="post" action="<?= e(admin_url('order/savePublish')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="form-row">
        <label>任务标题 <span class="req">*</span></label>
        <input type="text" name="title" maxlength="100" value="<?= e(old('title')) ?>" placeholder="例如：三角洲行动 上分代练 1 小时" required>
      </div>

      <div class="form-row">
        <label>游戏</label>
        <select name="game_id">
          <option value="0">不指定游戏</option>
          <?php foreach ($games as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= (int) old('game_id') === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label>任务配图</label>
        <input type="file" name="cover" accept="image/*">
        <p class="hint">会显示在接单大厅的任务卡片上；支持 jpg / png / gif / webp，≤5MB。</p>
      </div>

      <div class="form-row">
        <label>计费方式 <span class="req">*</span></label>
        <select name="service_type" required>
          <?php foreach (service_type_map() as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= old('service_type', 'custom') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label>数量</label>
        <input type="number" name="quantity" min="1" max="99" value="<?= (int) old('quantity', 1) ?>">
        <p class="hint">选择「一口价」时固定按 1 单计。</p>
      </div>

      <div class="form-row">
        <label>单价（元） <span class="req">*</span></label>
        <input type="number" name="unit_price" min="0.01" step="0.01" value="<?= e(old('unit_price')) ?>" placeholder="例如 50" required>
        <p class="hint">陪玩看到的「预计收入」= 金额 ×（1 − 平台抽成比例）。</p>
      </div>

      <div class="form-row">
        <label>付款用户 <span class="req">*</span></label>
        <select name="user_id" required>
          <option value="">请选择由哪个用户付款</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= $preset_user === (int) $u['id'] ? 'selected' : '' ?>>
              <?= e($u['nickname']) ?>（#<?= (int) $u['id'] ?><?= $u['phone'] !== '' ? ' · ' . e($u['phone']) : '' ?>）
            </option>
          <?php endforeach; ?>
        </select>
        <p class="hint">任务需要有人付款（钱进平台托管，完成后再结算给接单陪玩）。</p>
      </div>

      <div class="form-row">
        <label>任务备注</label>
        <input type="text" name="remark" maxlength="200" value="<?= e(old('remark')) ?>" placeholder="例如：晚上 8 点后开始 / 指定地图">
      </div>

      <div class="form-row">
        <label class="check">
          <input type="checkbox" name="pay_now" value="1" checked> 立即从该用户余额扣款（否则任务不会进入大厅）
        </label>
        <p class="hint">未付款的任务状态为「待支付」，接单大厅不显示；可让用户在前台自行支付，或稍后重新发布。</p>
      </div>

      <button class="btn btn-primary btn-block" type="submit">发布到接单大厅</button>
    </form>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">接单大厅流程</h2>
      <ol class="tips">
        <li>后台发布任务并扣款 → 订单进入「待接单」且标记为公开。</li>
        <li>陪玩（后台设为陪玩身份的用户）在底部「接单」页看到任务卡片，点「立即接单」。</li>
        <li>接单后订单自动归属该陪玩并进入「服务中」，其他陪玩看不到了。</li>
        <li>陪玩提交完成 → 用户或后台确认 → 平台抽成后结算给陪玩。</li>
        <li>长期没人接的任务可以到订单详情里「关闭并退款」。</li>
      </ol>
      <p style="margin-top:12px">
        <a class="btn btn-ghost btn-block" href="<?= e(admin_url('order/index', ['status' => 2])) ?>">查看待接单订单</a>
        <a class="btn btn-ghost btn-block" href="<?= e(admin_url('user/index', ['role' => 'user'])) ?>" style="margin-top:8px">去用户管理设置陪玩身份</a>
      </p>
    </section>
  </aside>
</div>
