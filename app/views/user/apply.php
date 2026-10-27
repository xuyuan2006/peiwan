<?php
/**
 * 陪玩入驻申请
 * @var array|null $profile
 */
$__me = user();
?>
<div class="page-head">
  <h1>成为陪玩</h1>
  <p class="muted">提交资料后由平台审核，通过后即可上架技能、接单赚钱</p>
</div>

<div class="detail-grid">
  <section class="card">
    <?php if ($profile && (int) $profile['audit_status'] === 0): ?>
      <div class="alert alert-info">你的申请正在审核中，审核期间可以继续修改资料。</div>
    <?php elseif ($profile && (int) $profile['audit_status'] === 1): ?>
      <div class="alert alert-success">你已通过审核，可以到 <a href="<?= e(url('companion/skills')) ?>">技能管理</a> 上架服务。</div>
    <?php elseif ($profile && (int) $profile['audit_status'] === 2): ?>
      <div class="alert alert-error">申请被拒绝：<?= e($profile['audit_remark'] !== '' ? $profile['audit_remark'] : '资料不符合要求') ?>，可修改后重新提交。</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('user/apply')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label>真实姓名 <span class="req">*</span></label>
        <input type="text" name="real_name" value="<?= e($profile['real_name'] ?? '') ?>" maxlength="50" placeholder="用于实名核验" required>
      </div>
      <div class="form-row">
        <label>身份证号 <span class="req">*</span></label>
        <input type="text" name="id_card" value="<?= e($profile['id_card'] ?? '') ?>" maxlength="18" placeholder="18 位身份证号，仅平台审核可见" required>
      </div>
      <div class="form-row">
        <label>联系手机号 <span class="req">*</span></label>
        <input type="text" name="phone" value="<?= e($profile['phone'] ?? ($__me['phone'] ?? '')) ?>" maxlength="20" required>
      </div>
      <div class="form-row">
        <label>性别</label>
        <select name="gender">
          <?php $g = (int) ($profile['gender'] ?? $__me['gender']); ?>
          <option value="0" <?= $g === 0 ? 'selected' : '' ?>>保密</option>
          <option value="1" <?= $g === 1 ? 'selected' : '' ?>>男</option>
          <option value="2" <?= $g === 2 ? 'selected' : '' ?>>女</option>
        </select>
      </div>
      <div class="form-row">
        <label>所在城市</label>
        <input type="text" name="city" value="<?= e($profile['city'] ?? '') ?>" maxlength="50" placeholder="例如：上海">
      </div>
      <div class="form-row">
        <label>个人标签</label>
        <input type="text" name="tags" value="<?= e($profile['tags'] ?? '') ?>" maxlength="200" placeholder="逗号分隔，例如：技术好,声音甜,带飞">
      </div>
      <div class="form-row">
        <label>个人介绍</label>
        <textarea name="intro" rows="4" maxlength="500" placeholder="擅长的游戏、段位、服务风格、在线时间段等"><?= e($profile['intro'] ?? '') ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit" id="apply-submit"><?= $profile ? '保存并重新提交' : '提交申请' ?></button>
    </form>
  </section>

  <aside class="detail-side">
    <?php if ($assess !== ''): ?>
      <section class="card" id="assess-card">
        <h2 class="card-title">陪玩考核标准</h2>
        <div class="tips" style="max-height:320px;overflow-y:auto;white-space:pre-wrap"><?= e($assess) ?></div>
      </section>
      <?php // 提交按钮在倒计时结束前保持禁用；倒计时由 app.js 的 [data-assess-gate] 处理 ?>
      <div data-assess-gate="#apply-submit" data-assess-seconds="4"></div>
    <?php else: ?>
      <section class="card">
        <h2 class="card-title">入驻要求</h2>
        <ol class="tips">
          <li>年满 18 周岁，需实名认证。</li>
          <li>提供真实有效的联系方式，方便平台与用户联系。</li>
          <li>禁止代练外挂、代打他人账号等违规服务。</li>
          <li>严禁任何擦边、涉黄、涉赌内容，违者永久封禁。</li>
          <li>服务时间内需保持在线，爽约会影响信用分与推荐权重。</li>
        </ol>
      </section>
    <?php endif; ?>

    <?php
    // 已经提交过申请，就把客服联系方式摆出来（这是加客服的唯一引导入口）
    $__qr   = trim((string) ($contact_qr ?? ''));
    $__text = trim((string) ($contact_text ?? ''));
    if ($profile && ($__qr !== '' || $__text !== '')):
    ?>
      <section class="card">
        <h2 class="card-title">联系客服完成考核</h2>
        <p class="muted" style="margin-top:0">添加时备注一下你的昵称，方便客服核对。</p>
        <?php if ($__qr !== ''): ?>
          <img src="<?= e(upload_url($__qr)) ?>" alt="客服二维码"
               style="width:100%;max-width:220px;border-radius:12px;display:block;margin:8px auto">
        <?php endif; ?>
        <?php if ($__text !== ''): ?>
          <p style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:8px 0 0">
            <b id="service-contact"><?= e($__text) ?></b>
            <button type="button" class="btn btn-ghost btn-sm" data-copy="#service-contact">复制</button>
          </p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </aside>
</div>
