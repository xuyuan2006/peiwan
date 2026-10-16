<?php
/**
 * 后台用户详情
 * @var array $user
 * @var array $wallet
 * @var array $txns
 * @var array $orderStat
 */
$roleText = ['user' => '普通用户', 'companion' => '陪玩', 'admin' => '管理员'];
?>
<div class="admin-detail-grid">
  <div>
    <section class="card">
      <h2 class="card-title">基本信息</h2>
      <table class="table table-plain">
        <tr>
          <?php
          // 和用户自己看到的「我的 ID」保持一致（6 位用户号）。
          // 没执行升级脚本时用与回填 SQL 相同的公式现算。
          $__no = (int) ($user['no'] ?? 0);
          if ($__no <= 0) {
              $__no = \App\Auth::fallbackUserNo((int) $user['id']);
          }
          ?>
          <th>用户号</th><td><b><?= $__no ?></b></td>
          <th>账号</th><td><?= e($user['username']) ?></td>
        </tr>
        <tr>
          <th>昵称</th><td><?= e($user['nickname']) ?></td>
          <th>手机号</th><td><?= e($user['phone'] !== '' ? $user['phone'] : '-') ?></td>
        </tr>
        <tr>
          <?php // 邮箱：注册时必填的一项，之前这页漏了没显示 ?>
          <th>邮箱</th>
          <td>
            <?php if ((string) ($user['email'] ?? '') !== ''): ?>
              <span id="user-email"><?= e($user['email']) ?></span>
              <button type="button" class="btn btn-ghost btn-sm" data-copy="#user-email">复制</button>
            <?php else: ?>
              <span class="muted">未填写</span>
            <?php endif; ?>
          </td>
          <th>性别</th>
          <td><?= e(['保密', '男', '女'][(int) ($user['gender'] ?? 0)] ?? '-') ?></td>
        </tr>
        <?php // 陪玩申请时填的实名资料，基本信息里也放一份，不用往下翻 ?>
        <?php if (($user['real_name'] ?? '') !== '' || ($user['id_card'] ?? '') !== ''): ?>
        <tr>
          <th>真实姓名</th><td><?= e(($user['real_name'] ?? '') !== '' ? $user['real_name'] : '-') ?></td>
          <th>所在城市</th><td><?= e(($user['city'] ?? '') !== '' ? $user['city'] : '-') ?></td>
        </tr>
        <tr>
          <th>身份证号</th>
          <td colspan="3">
            <?php if (($user['id_card'] ?? '') !== ''): ?>
              <span id="user-idcard"><?= e($user['id_card']) ?></span>
              <button type="button" class="btn btn-ghost btn-sm" data-copy="#user-idcard">复制</button>
            <?php else: ?>
              <span class="muted">未填写</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <th>角色</th><td><?= e($roleText[$user['role']] ?? $user['role']) ?></td>
          <th>状态</th>
          <td><?= (int) $user['status'] === 1 ? '<span class="status status-success">正常</span>' : '<span class="status status-muted">已禁用</span>' ?></td>
        </tr>
        <tr>
          <th>信用分</th><td><?= (int) $user['credit_score'] ?></td>
          <th>注册时间</th><td><?= e((string) $user['created_at']) ?></td>
        </tr>
        <tr>
          <th>最近登录</th><td><?= e((string) ($user['last_login_at'] ?: '-')) ?></td>
          <th>登录IP</th><td><?= e($user['last_login_ip'] !== '' ? $user['last_login_ip'] : '-') ?></td>
        </tr>
        <?php if (isset($user['audit_status']) && $user['audit_status'] !== null): ?>
          <tr>
            <th>入驻审核</th><td><?= e(audit_status_text((int) $user['audit_status'])) ?></td>
            <th>陪玩评分</th>
            <td><?= e(number_format((float) ($user['score'] ?? 5), 2)) ?> · <?= (int) ($user['order_count'] ?? 0) ?> 单</td>
          </tr>
        <?php endif; ?>
      </table>

      <div class="cell-actions" style="margin-top:12px">
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/index', ['keyword' => $user['nickname']])) ?>">查看相关订单</a>
        <?php if ($user['role'] !== 'admin'): ?>
          <form method="post" action="<?= e(admin_url('user/toggleStatus')) ?>" class="inline-form" data-confirm="确定切换该账号状态吗？">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
            <button class="btn btn-sm <?= (int) $user['status'] === 1 ? 'btn-danger' : 'btn-primary' ?>" type="submit">
              <?= (int) $user['status'] === 1 ? '禁用账号' : '启用账号' ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <?php // 陪玩资料区块：把申请时填的内容完整摊开，方便审核与客服核对 ?>
    <?php if (($user['real_name'] ?? '') !== '' || ($user['intro'] ?? '') !== '' || ($user['tags'] ?? '') !== ''): ?>
    <section class="card">
      <h2 class="card-title">陪玩资料</h2>
      <table class="table table-plain">
        <tr>
          <th>真实姓名</th><td><?= e(($user['real_name'] ?? '') !== '' ? $user['real_name'] : '-') ?></td>
          <th>所在城市</th><td><?= e(($user['city'] ?? '') !== '' ? $user['city'] : '-') ?></td>
        </tr>
        <tr>
          <th>身份证号</th>
          <td colspan="3">
            <span id="user-idcard-2"><?= e(($user['id_card'] ?? '') !== '' ? $user['id_card'] : '-') ?></span>
            <?php if (($user['id_card'] ?? '') !== ''): ?>
              <button type="button" class="btn btn-ghost btn-sm" data-copy="#user-idcard-2">复制</button>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>个人标签</th>
          <td colspan="3"><?= e(($user['tags'] ?? '') !== '' ? $user['tags'] : '-') ?></td>
        </tr>
        <tr>
          <th>个人介绍</th>
          <td colspan="3">
            <?= ($user['intro'] ?? '') !== '' ? nl2br(e($user['intro'])) : '<span class="muted">未填写</span>' ?>
          </td>
        </tr>
      </table>
    </section>
    <?php endif; ?>

    <section class="table-card">
      <div class="card-head" style="padding-top:12px">
        <h2 class="card-title">资金流水（最近 20 条）</h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/index', ['keyword' => $user['nickname']])) ?>">订单</a>
      </div>
      <?php if (!$txns): ?>
        <div class="empty">暂无资金记录</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>时间</th><th>类型</th><th>金额</th><th>余额</th><th>冻结</th><th>备注</th></tr></thead>
          <tbody>
          <?php foreach ($txns as $t): ?>
            <tr>
              <td class="muted nowrap"><?= e((string) $t['created_at']) ?></td>
              <td><?= e(wallet_type_text((string) $t['type'])) ?></td>
              <td class="<?= (int) $t['direction'] === 1 ? 'text-success' : 'text-danger' ?>">
                <?= (int) $t['direction'] === 1 ? '+' : '-' ?><?= e(money((float) $t['amount'])) ?>
              </td>
              <td><?= e(money((float) $t['balance_after'])) ?></td>
              <td class="muted"><?= e(money((float) $t['frozen_after'])) ?></td>
              <td class="muted"><?= e(str_limit((string) $t['remark'], 30)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <section class="card">
      <h2 class="card-title">钱包</h2>
      <ul class="stat-list">
        <li><span>可用余额</span><strong><?= e(yuan($wallet['balance'])) ?></strong></li>
        <li><span>冻结金额</span><strong><?= e(yuan($wallet['frozen'])) ?></strong></li>
        <li><span>累计充值</span><strong><?= e(yuan($wallet['total_recharge'])) ?></strong></li>
        <li><span>累计收入</span><strong><?= e(yuan($wallet['total_income'])) ?></strong></li>
        <li><span>累计提现</span><strong><?= e(yuan($wallet['total_withdraw'])) ?></strong></li>
        <li><span>累计消费</span><strong><?= e(yuan($wallet['total_expense'])) ?></strong></li>
      </ul>
    </section>

    <section class="card">
      <h2 class="card-title">人工调整余额</h2>
      <form method="post" action="<?= e(admin_url('user/adjustBalance')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
        <div class="form-row">
          <label>操作类型</label>
          <select name="type">
            <option value="add">加款（补偿/活动）</option>
            <option value="sub">扣款（违规/纠错）</option>
          </select>
        </div>
        <div class="form-row">
          <label>金额（元）</label>
          <input type="number" name="amount" min="0.01" step="0.01" required>
        </div>
        <div class="form-row">
          <label>备注（会写入流水）</label>
          <input type="text" name="remark" maxlength="200" placeholder="例如：活动奖励" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">确认调整</button>
      </form>
      <p class="hint">所有调整都会记录到资金流水和后台操作日志，请谨慎操作。</p>
    </section>

    <section class="card">
      <h2 class="card-title">身份操作</h2>

      <?php if ($user['role'] === 'companion'): ?>
        <p class="muted" style="font-size:13px">该用户已是陪玩。
          <a href="<?= e(admin_url('skill/index', ['keyword' => $user['nickname']])) ?>">去技能管理为其上架服务项目 →</a>
        </p>
        <form method="post" action="<?= e(admin_url('companion/demote')) ?>" data-confirm="取消陪玩资格会下架其全部技能，确定吗？">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
          <button class="btn btn-danger btn-block" type="submit">取消陪玩资格</button>
        </form>
      <?php elseif ($user['role'] === 'admin'): ?>
        <p class="muted" style="font-size:13px">管理员账号无需设为陪玩。</p>
      <?php else: ?>
        <p class="muted" style="font-size:13px">设为陪玩后会立即通过审核，可在「技能管理」为其上架带价格的服务项目。</p>
        <form method="post" action="<?= e(admin_url('companion/promote')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
          <div class="form-row">
            <label>真实姓名（留空则用昵称）</label>
            <input type="text" name="real_name" maxlength="50" placeholder="<?= e($user['nickname']) ?>">
          </div>
          <button class="btn btn-primary btn-block" type="submit">设为陪玩</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2 class="card-title">管理员权限</h2>
      <?php if ($user['role'] === 'admin'): ?>
        <p class="muted" style="font-size:13px">
          该账号是管理员<?= (int) $user['id'] === (int) \App\Auth::id() ? '（就是你自己）' : '' ?>，可以在后台管理全站。
        </p>
        <?php if ((int) $user['id'] === (int) \App\Auth::id()): ?>
          <p class="hint">不能取消自己的管理员身份，请用另一个管理员账号操作。</p>
        <?php else: ?>
          <form method="post" action="<?= e(admin_url('user/demoteAdmin')) ?>" data-confirm="确定取消该账号的管理员身份吗？">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
            <button class="btn btn-danger btn-block" type="submit">取消管理员身份</button>
          </form>
          <p class="hint">系统至少要保留一个管理员，最后一个无法被取消。</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="muted" style="font-size:13px">设为管理员后，该账号可以登录后台管理全站，请谨慎授权。</p>
        <form method="post" action="<?= e(admin_url('user/promoteAdmin')) ?>" data-confirm="确定把该账号设为管理员吗？">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
          <button class="btn btn-primary btn-block" type="submit">设为管理员</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2 class="card-title">重置登录密码</h2>
      <p class="muted" style="font-size:13px">直接为该账号设置一个新密码（不需要旧密码），用户下次用新密码登录即可。</p>
      <form method="post" action="<?= e(admin_url('user/resetPassword')) ?>" data-confirm="确定重置该账号的登录密码吗？">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
        <div class="form-row">
          <label>新密码</label>
          <input type="text" name="new_password" id="reset-pass" maxlength="30" placeholder="至少 6 位" required autocomplete="off">
        </div>
        <div class="btn-group">
          <button class="btn btn-primary btn-block" type="submit">重置密码</button>
          <button type="button" class="btn btn-ghost btn-block" id="reset-pass-random">随机生成</button>
        </div>
      </form>
      <p class="hint">
        重置后请把新密码告知用户，操作会记入后台日志。数据库里存的是 bcrypt 哈希，<b>旧密码无法查看</b>，只能重置。
      </p>
    </section>

    <section class="card">
      <h2 class="card-title">订单统计</h2>
      <ul class="stat-list">
        <li><span>相关订单</span><strong><?= (int) ($orderStat['total'] ?? 0) ?></strong></li>
        <li><span>已完成</span><strong><?= (int) ($orderStat['completed'] ?? 0) ?></strong></li>
        <li><span>成交金额</span><strong><?= e(yuan((float) ($orderStat['amount'] ?? 0))) ?></strong></li>
      </ul>
    </section>
  </aside>
</div>
