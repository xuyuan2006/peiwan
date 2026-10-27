<?php
/**
 * 工单部门 · 处理工单
 *
 * 权限：
 *   - 超管 / 主管：看公海 + 全部，派单/转单给任意客服
 *   - 组长：看公海 + 本组客服的单，派单给本组客服
 *   - 客服：只看派给自己的单
 *
 * 只有本控制器对「工单部门账号」开放，其它后台页面会被 BaseController 拦成 403。
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Db;
use App\KfStaff;
use App\TicketService;

class KfDeskController extends BaseController
{
    /** 工单部门账号允许进入本控制器 */
    protected function allowKfStaff(): bool
    {
        return true;
    }

    // ---------------------------------------------------------------- 列表

    public function index()
    {
        $actor = $this->actor();
        $scopes = $this->scopes($actor);

        $scope = input_str('scope', $scopes[0]);
        if (!in_array($scope, $scopes, true)) {
            $scope = $scopes[0];
        }

        $filter = [
            'status'   => input_str('status'),
            'category' => input_str('category'),
            'keyword'  => input_str('keyword'),
            'unread'   => input_str('unread'),
        ];
        $page = max(1, input_int('page', 1));

        $this->view('admin/kfdesk/index', [
            'title'   => '工单处理',
            'actor'   => $actor,
            'scope'   => $scope,
            'scopes'  => $scopes,
            'filter'  => $filter,
            'result'  => $this->query($actor, $scope, $filter, $page, 20),
            'counts'  => $this->counts($actor),
            // 公海 / 全部列表里直接分配、转接用
            'targets'   => KfStaff::dispatchTargets($actor),
            'canAssign' => KfStaff::canAssign($actor),
        ]);
    }

    // ---------------------------------------------------------------- 详情

    public function detail()
    {
        $actor  = $this->actor();
        $id     = input_int('id', 0);
        $ticket = $id > 0 ? TicketService::find($id) : null;
        if (!$ticket) {
            abort(404, '工单不存在');
        }
        if (!$this->canView($actor, $ticket)) {
            abort(403, '权限不足：这张工单不在你手上');
        }

        // 补上处理人姓名（TicketService::find 不 JOIN kf_staff）
        $staffId = (int) ($ticket['staff_id'] ?? 0);
        $ticket['staff_name'] = '';
        if ($staffId > 0) {
            $staffRow = KfStaff::find($staffId);
            $ticket['staff_name'] = $staffRow ? (string) $staffRow['name'] : '';
        }

        // 打开就算已读（记到最后一条回复）；用户之后再次回复会自动变回未读
        if ($actor['type'] === 'staff') {
            KfStaff::markRead((int) $actor['id'], $id);
        }

        $this->view('admin/kfdesk/detail', [
            'title'      => '工单 ' . $ticket['ticket_no'],
            'actor'      => $actor,
            'ticket'     => $ticket,
            'thread'     => TicketService::thread($id),
            'targets'    => KfStaff::dispatchTargets($actor),
            'canReply'   => $this->canView($actor, $ticket),
            'canAssign'  => KfStaff::canAssign($actor),
        ]);
    }

    // ---------------------------------------------------------------- 动作

    /** 回复 */
    public function reply()
    {
        require_post();
        verify_csrf();
        $actor  = $this->actor();
        $id     = input_int('id', 0);
        $ticket = $id > 0 ? TicketService::find($id) : null;

        $this->runAction(function () use ($actor, $id, $ticket) {
            if (!$ticket) {
                throw new \RuntimeException('工单不存在');
            }
            if (!$this->canView($actor, $ticket)) {
                throw new \RuntimeException('这张工单不在你手上，不能回复');
            }

            $senderType = $actor['type'] === 'admin' ? 'admin' : 'kf';
            $content    = input_str('content');

            $attachments = \App\Upload::files('attachments', 'tickets', 5);
            TicketService::reply($id, $senderType, (int) $actor['id'], $content, (string) $actor['name'], $attachments);

            // 客服回复公海里的单，自动领走（组长及以上不领单）
            if ((int) ($ticket['staff_id'] ?? 0) === 0 && $actor['role'] === 'agent') {
                Db::update('tickets', [
                    'staff_id'   => (int) $actor['id'],
                    'updated_at' => now(),
                ], 'id = ?', [$id]);
            }
            return true;
        }, '回复已发送', admin_url('kf_desk/detail', ['id' => $id]));
    }

    /** 派单 / 转单 */
    public function assign()
    {
        require_post();
        verify_csrf();
        $actor = $this->actor();
        $id    = input_int('id', 0);
        $to    = input_int('staff_id', 0);

        // 在列表里分配时，操作完回到列表而不是详情页
        $redirect = input_str('back') === 'list'
            ? admin_url('kf_desk/index', ['scope' => input_str('scope', 'all')])
            : admin_url('kf_desk/detail', ['id' => $id]);

        $this->runAction(function () use ($actor, $id, $to) {
            // 超管 / 经理 / 组长都能派单，但能派给谁不一样
            if (!KfStaff::canAssign($actor)) {
                throw new \RuntimeException('只有组长及以上可以派单');
            }
            $this->assertTarget($actor, $to);

            Db::update('tickets', [
                'staff_id'   => $to,
                'updated_at' => now(),
            ], 'id = ?', [$id]);

            return true;
        }, '已派单', $redirect);
    }

    /** 改状态 */
    public function status()
    {
        require_post();
        verify_csrf();
        $actor  = $this->actor();
        $id     = input_int('id', 0);
        $status = input_int('status', 0);
        $ticket = $id > 0 ? TicketService::find($id) : null;

        $this->runAction(function () use ($actor, $id, $status, $ticket) {
            if (!$ticket) {
                throw new \RuntimeException('工单不存在');
            }
            if (!$this->canView($actor, $ticket)) {
                throw new \RuntimeException('这张工单不在你手上');
            }
            TicketService::setStatus($id, $status, (int) $actor['id']);
            return true;
        }, '状态已更新', admin_url('kf_desk/detail', ['id' => $id]));
    }

    /** 批量转接：经理选中多张单 → 一次性转给某个组长（或客服）；组长 → 转给本组客服 */
    public function batchAssign()
    {
        require_post();
        verify_csrf();

        $actor = $this->actor();
        $to    = input_int('staff_id', 0);
        $scope = input_str('scope', 'all');
        $ids   = $_POST['ids'] ?? [];

        $back = admin_url('kf_desk/index', ['scope' => $scope]);

        if (!KfStaff::canAssign($actor)) {
            flash('error', '只有组长及以上可以转接工单');
            redirect($back);
        }
        if (!is_array($ids) || !$ids) {
            flash('error', '请先勾选要转接的工单');
            redirect($back);
        }
        if ($to <= 0) {
            flash('error', '请选择转接给谁');
            redirect($back);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        try {
            $this->assertTarget($actor, $to);

            $done = 0;
            $skip = 0;
            foreach ($ids as $tid) {
                $ticket = TicketService::find($tid);
                if (!$ticket || !$this->canView($actor, $ticket)) {
                    $skip++;
                    continue;
                }
                Db::update('tickets', [
                    'staff_id'   => $to,
                    'updated_at' => now(),
                ], 'id = ?', [$tid]);
                $done++;
            }

            $msg = '已转接 ' . $done . ' 张工单';
            if ($skip > 0) {
                $msg .= '，' . $skip . ' 张不在你权限范围内已跳过';
            }
            flash('success', $msg);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect($back);
    }

    /** 派单目标校验（单条派单与批量转接共用） */
    private function assertTarget(array $actor, int $to): void
    {
        $target = KfStaff::find($to);
        if (!$target) {
            throw new \RuntimeException('派单对象不存在');
        }
        if ((int) $target['status'] !== 1) {
            throw new \RuntimeException('该账号已停用');
        }

        $targetRole = (string) $target['role'];

        if (KfStaff::isSuper($actor)) {
            if (!in_array($targetRole, ['leader', 'agent'], true)) {
                throw new \RuntimeException('工单只能派给组长或客服');
            }
            return;
        }

        if ($actor['role'] === 'manager') {
            // 经理：派给自己名下的组长，或这些组长名下的客服
            if ($targetRole === 'leader') {
                if ((int) ($target['manager_id'] ?? 0) !== (int) $actor['id']) {
                    throw new \RuntimeException('只能派给自己名下的组长');
                }
                return;
            }
            if ($targetRole === 'agent') {
                $leader = KfStaff::find((int) ($target['leader_id'] ?? 0));
                if (!$leader || (int) ($leader['manager_id'] ?? 0) !== (int) $actor['id']) {
                    throw new \RuntimeException('只能派给自己团队的客服');
                }
                return;
            }
            throw new \RuntimeException('工单只能派给组长或客服');
        }

        // 组长：只能派给自己组里的客服
        if ($targetRole !== 'agent' || (int) ($target['leader_id'] ?? 0) !== (int) $actor['id']) {
            throw new \RuntimeException('只能派给自己组里的客服');
        }
    }

    // ---------------------------------------------------------------- 内部

    /** @return array{type:string,id:int,role:string,name:string,leader_id:int} */
    private function actor(): array
    {
        // 第一次进入时自动补齐表/字段（幂等）；补不上再给出明确提示，而不是"服务异常"
        KfStaff::ensureSchema();
        $missing = KfStaff::missingSchema();
        if ($missing) {
            abort(503, '工单部门还没初始化：缺少 ' . implode('、', $missing)
                . '。请先在数据库里执行 sql/kf_desk.sql 的全部内容，或给数据库账号建表权限后刷新本页。');
        }

        $actor = KfStaff::actor();
        if ($actor['type'] === 'none') {
            abort(403, '请先登录');
        }
        return $actor;
    }

    /** @return array<int,string> */
    private function scopes(array $actor): array
    {
        if (KfStaff::isSuper($actor) || $actor['role'] === 'manager') {
            return ['all', 'mine', 'pool'];   // 经理 / 超管：所有工单 + 公海
        }
        if ($actor['role'] === 'leader') {
            return ['group', 'mine'];         // 组长：只看本组和派给自己的，**看不到公海**
        }
        return ['mine'];                      // 客服：只有自己的
    }

    private function canView(array $actor, array $ticket): bool
    {
        $staffId = (int) ($ticket['staff_id'] ?? 0);

        // 超管和经理能看到所有工单
        if (KfStaff::isSuper($actor) || $actor['role'] === 'manager') {
            return true;
        }

        // 组长：只能看派给自己的、和自己组里客服的（公海看不到）
        if ($actor['role'] === 'leader') {
            if ($staffId === (int) $actor['id']) {
                return true;
            }
            if ($staffId === 0) {
                return false;
            }
            $row = KfStaff::find($staffId);
            return $row && (int) $row['leader_id'] === (int) $actor['id'];
        }

        // 客服：只看派给自己的
        return $staffId === (int) $actor['id'];
    }

    /** @return array<string,mixed> */
    private function query(array $actor, string $scope, array $filter, int $page, int $per): array
    {
        $meId   = (int) $actor['id'];
        $where  = ['1 = 1'];
        $params = [];

        if ($scope === 'pool') {
            $where[] = 't.staff_id = 0';
            $where[] = 't.status IN (0, 1)';
        } elseif ($scope === 'mine') {
            $where[]  = 't.staff_id = ?';
            $params[] = $meId;
        } elseif ($scope === 'group') {
            $ids = array_map('intval', array_column(KfStaff::agents((int) $actor['id']), 'id'));
            if (!$ids) {
                $where[] = '1 = 0';
            } else {
                $where[] = 't.staff_id IN (' . implode(',', $ids) . ')';
            }
        }

        $status = (string) ($filter['status'] ?? '');
        if ($status !== '' && in_array($status, ['0', '1', '2', '3'], true)) {
            $where[]  = 't.status = ?';
            $params[] = (int) $status;
        }
        $category = (string) ($filter['category'] ?? '');
        if ($category !== '' && array_key_exists($category, TicketService::categories())) {
            $where[]  = 't.category = ?';
            $params[] = $category;
        }
        $keyword = trim((string) ($filter['keyword'] ?? ''));
        if ($keyword !== '') {
            $kw      = '%' . like_escape($keyword) . '%';
            $where[] = '(t.ticket_no LIKE ? OR t.title LIKE ? OR u.nickname LIKE ?)';
            array_push($params, $kw, $kw, $kw);
        }
        // 只看未读
        if ((string) ($filter['unread'] ?? '') === '1') {
            $where[] = "(kr.id IS NULL OR EXISTS (
                           SELECT 1 FROM ticket_replies ru
                           WHERE ru.ticket_id = t.id AND ru.sender_type = 'user' AND ru.id > kr.last_reply_id
                        ))";
        }

        $whereSql = implode(' AND ', $where);

        // 两张查询共用同一组 JOIN，保证「未读」筛选后的总数也对
        $joins = "FROM tickets t
                  LEFT JOIN users u ON u.id = t.user_id
                  LEFT JOIN kf_staff s ON s.id = t.staff_id
                  LEFT JOIN kf_ticket_reads kr ON kr.ticket_id = t.id AND kr.staff_id = $meId";

        $total = (int) Db::value("SELECT COUNT(*) $joins WHERE $whereSql", $params);

        $page   = max(1, $page);
        $offset = ($page - 1) * $per;
        $rows   = Db::all(
            "SELECT t.*, u.nickname AS user_nickname, s.name AS staff_name,
                    (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = t.id) AS reply_count,
                    kr.id AS read_id, COALESCE(kr.last_reply_id, 0) AS last_reply_id,
                    (SELECT COUNT(*) FROM ticket_replies r2
                      WHERE r2.ticket_id = t.id AND r2.sender_type = 'user'
                        AND r2.id > COALESCE(kr.last_reply_id, 0)) AS new_replies
             $joins
             WHERE $whereSql
             ORDER BY ((kr.id IS NULL) OR EXISTS (
                          SELECT 1 FROM ticket_replies ru2
                          WHERE ru2.ticket_id = t.id AND ru2.sender_type = 'user' AND ru2.id > kr.last_reply_id
                      )) DESC,
                      t.status ASC, t.id DESC
             LIMIT $per OFFSET $offset",
            $params
        );

        // 未读判定：没打开过 = 未读；打开过则看用户有没有新追问
        foreach ($rows as $i => $row) {
            $rows[$i]['is_unread'] = ((int) ($row['read_id'] ?? 0) === 0) || ((int) ($row['new_replies'] ?? 0) > 0);
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per' => $per];
    }

    /** @return array<string,int> */
    private function counts(array $actor): array
    {
        $mine = (int) Db::value('SELECT COUNT(*) FROM tickets WHERE staff_id = ? AND status IN (0,1,2)', [(int) $actor['id']]);
        $pool = (int) Db::value('SELECT COUNT(*) FROM tickets WHERE staff_id = 0 AND status IN (0,1)');
        $all  = (int) Db::value('SELECT COUNT(*) FROM tickets WHERE status IN (0,1)');

        // 组长的「本组工单」
        $group = 0;
        if ((string) $actor['role'] === 'leader') {
            $ids = array_map('intval', array_column(KfStaff::agents((int) $actor['id']), 'id'));
            if ($ids) {
                $in    = implode(',', $ids);
                $group = (int) Db::value("SELECT COUNT(*) FROM tickets WHERE staff_id IN ($in) AND status IN (0,1)");
            }
        }

        return ['mine' => $mine, 'pool' => $pool, 'all' => $all, 'group' => $group];
    }

    /** 统一的写操作收尾：成功回列表，失败给提示 */
    private function runAction(callable $fn, string $success, string $redirect): void
    {
        try {
            $fn();
            flash('success', $success);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect($redirect);
    }
}
