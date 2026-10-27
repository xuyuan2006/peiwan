<?php
/**
 * 工单部门 · 新建 / 编辑账号
 * @var array $actor
 * @var array|null $editing
 * @var array $roles
 * @var array $roleMap
 * @var array $leaders
 */
$isEdit = !empty($editing);
$val = function (string $key, string $default = '') use ($editing) {
    if ($editing) {
        return (string) ($editing[$key] ?? $default);
    }
    return (string) input_str($key, $default);
};
?>
<p><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kf_staff/index')) ?>">← 返回账号列表</a></p>

<div class="card" style="max-width:640px">
  <h2 class="card-title"><?= $isEdit ? '编辑工单账号' : '新建工单账号' ?></h2>
  <p class="muted">
    工号由系统按顺序自动生成（KF0001、KF0002…），不用手填。<br>
    客服需要指定「所属组长」，否则组长看不到、也派不了他的单。
  </p>

  <form method="post" action="<?= e(admin_url('kf_staff/save')) ?>">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <?php endif; ?>

    <label>登录账号
      <?php if ($isEdit): ?>
        <input type="text" value="<?= e((string) $editing['account']) ?>" disabled>
        <span class="muted" style="font-size:12px">账号创建后不能改；工号 <?= e((string) $editing['staff_no']) ?></span>
      <?php else: ?>
        <input type="text" name="account" value="<?= e((string) $val('account')) ?>" placeholder="3~20 位字母/数字/下划线" required>
      <?php endif; ?>
    </label>

    <label>姓名 / 昵称
      <input type="text" name="name" value="<?= e((string) $val('name')) ?>" maxlength="20" required>
    </label>

    <label>邮箱（必填）
      <input type="email" name="email" value="<?= e((string) $val('email')) ?>" placeholder="name@example.com" required>
    </label>

    <label>手机号（必填）
      <input type="text" name="phone" value="<?= e((string) $val('phone')) ?>" placeholder="13800000000" maxlength="11" required>
    </label>

    <label>角色
      <select name="role" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r) ?>" <?= (string) $val('role', 'agent') === $r ? 'selected' : '' ?>>
            <?= e($roleMap[$r] ?? $r) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">
        超管可建主管/组长/客服；主管只能建组长/客服。
      </span>
    </label>

    <label id="wrap-manager">所属经理（组长才需要）
      <select name="manager_id">
        <option value="0">不指定</option>
        <?php foreach ($managers as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= (int) $val('manager_id', '0') === (int) $m['id'] ? 'selected' : '' ?>>
            <?= e((string) $m['name']) ?>（<?= e((string) $m['staff_no']) ?>）
          </option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">组长挂到经理名下，经理才能把工单派给他。</span>
    </label>

    <label id="wrap-leader">所属组长（客服才需要）
      <select name="leader_id">
        <option value="0">不指定</option>
        <?php foreach ($leaders as $l): ?>
          <option value="<?= (int) $l['id'] ?>" <?= (int) $val('leader_id', '0') === (int) $l['id'] ? 'selected' : '' ?>>
            <?= e((string) $l['name']) ?>（<?= e((string) $l['staff_no']) ?>）
          </option>
        <?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:12px">客服挂到组长名下，组长才能把工单派给他。</span>
    </label>

    <?php if (!$isEdit): ?>
      <label>初始密码
        <input type="text" name="password" value="" placeholder="至少 8 位，含字母和数字" required>
        <span class="muted" style="font-size:12px">明文输入方便复制给本人；系统只存加密串。</span>
      </label>
    <?php endif; ?>

    <div class="actions" style="margin-top:14px">
      <button class="btn btn-primary" type="submit"><?= $isEdit ? '保存' : '创建账号' ?></button>
      <a class="btn btn-ghost" href="<?= e(admin_url('kf_staff/index')) ?>">取消</a>
    </div>
  </form>
</div>

<script>
(function () {
  // 选「组长」显示所属经理；选「客服」显示所属组长；经理本身两者都不显示
  var role = document.querySelector('form select[name="role"]');
  var wrapManager = document.getElementById('wrap-manager');
  var wrapLeader  = document.getElementById('wrap-leader');

  function sync() {
    if (!role || !wrapManager || !wrapLeader) return;
    var v = role.value;
    wrapManager.style.display = v === 'leader' ? '' : 'none';
    wrapLeader.style.display  = v === 'agent'  ? '' : 'none';
  }

  if (role) {
    role.addEventListener('change', sync);
    sync();
  }
})();
</script>
