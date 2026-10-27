<?php
/**
 * 工单部门 · 员工看板
 *
 * 看谁的：
 *   - 超管：所有经理/组长/客服
 *   - 经理：自己名下的组长 + 这些组长名下的客服
 *   - 组长：自己 + 本组客服
 *   - 客服：无权查看（403）
 *
 * 每行显示：手里多少张（待处理+处理中）、已完结多少张（已解决+已关闭）、合计。
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Db;
use App\KfStaff;

class KfBoardController extends BaseController
{
    protected function allowKfStaff(): bool
    {
        return true; // 具体角色在 index() 里再收窄
    }

    public function index()
    {
        $actor = KfStaff::actor();
        if ($actor['type'] === 'none') {
            abort(403, '请先登录');
        }
        if ((string) $actor['role'] === 'agent') {
            abort(403, '权限不足：客服账号看不到员工看板');
        }

        KfStaff::ensureSchema();
        $missing = KfStaff::missingSchema();
        if ($missing) {
            abort(503, '工单部门还没初始化：缺少 ' . implode('、', $missing) . '。请刷新「工单处理」页面让系统自动创建。');
        }

        $rows = Db::all($this->sql($actor), $this->params($actor));

        $doing = 0;
        $done  = 0;
        foreach ($rows as $r) {
            $doing += (int) $r['doing'];
            $done  += (int) $r['done'];
        }

        $pool = 0;
        if (KfStaff::isSuper($actor) || $actor['role'] === 'manager') {
            $pool = (int) Db::value('SELECT COUNT(*) FROM tickets WHERE staff_id = 0 AND status IN (0, 1)');
        }

        $this->view('admin/kfboard/index', [
            'title' => '员工',
            'actor' => $actor,
            'rows'  => $rows,
            'doing' => $doing,
            'done'  => $done,
            'pool'  => $pool,
        ]);
    }

    /**
     * 点某个员工 → 看他名下的工单（查看/协同处理）
     * 超管、经理、组长都能点开自己范围内的人。
     */
    public function staff()
    {
        $actor = KfStaff::actor();
        if ($actor['type'] === 'none') {
            abort(403, '请先登录');
        }
        if ((string) $actor['role'] === 'agent') {
            abort(403, '权限不足：客服账号看不到员工看板');
        }

        $id     = input_int('id', 0);
        $target = $id > 0 ? KfStaff::find($id) : null;
        if (!$target) {
            abort(404, '账号不存在');
        }
        if (!$this->inScope($actor, $target)) {
            abort(403, '权限不足：这个人不在你的团队范围里');
        }

        $status = input_str('status');
        $where  = ['t.staff_id = ?'];
        $params = [$id];
        if (in_array($status, ['0', '1', '2', '3'], true)) {
            $where[]  = 't.status = ?';
            $params[] = (int) $status;
        }

        $whereSql = implode(' AND ', $where);
        $rows = Db::all(
            "SELECT t.*, u.nickname AS user_nickname
             FROM tickets t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE $whereSql
             ORDER BY t.status ASC, t.id DESC
             LIMIT 200",
            $params
        );

        $doing = 0;
        $done  = 0;
        foreach ($rows as $r) {
            if (in_array((int) $r['status'], [0, 1], true)) {
                $doing++;
            } else {
                $done++;
            }
        }

        $this->view('admin/kfboard/staff', [
            'title'  => '员工工单 · ' . $target['name'],
            'actor'  => $actor,
            'target' => $target,
            'rows'   => $rows,
            'filter' => ['status' => $status],
            'doing'  => $doing,
            'done'   => $done,
        ]);
    }

    /** 目标账号是否在当前操作者的团队范围内 */
    private function inScope(array $actor, array $target): bool
    {
        if (KfStaff::isSuper($actor)) {
            return true;
        }
        $targetId   = (int) $target['id'];
        $targetRole = (string) $target['role'];

        if ($actor['role'] === 'manager') {
            if ($targetId === (int) $actor['id']) {
                return true;
            }
            if ($targetRole === 'leader') {
                return (int) ($target['manager_id'] ?? 0) === (int) $actor['id'];
            }
            if ($targetRole === 'agent') {
                $leader = KfStaff::find((int) ($target['leader_id'] ?? 0));
                return $leader && (int) ($leader['manager_id'] ?? 0) === (int) $actor['id'];
            }
            return false;
        }

        if ($actor['role'] === 'leader') {
            return $targetId === (int) $actor['id']
                || ($targetRole === 'agent' && (int) ($target['leader_id'] ?? 0) === (int) $actor['id']);
        }

        return false;
    }

    /** @return array<int,mixed> */
    private function params(array $actor): array
    {
        if (KfStaff::isSuper($actor)) {
            return [];
        }
        if ($actor['role'] === 'manager') {
            // SQL 里 manager_id 出现了两次，所以 id 要传三个
            return [(int) $actor['id'], (int) $actor['id'], (int) $actor['id']];
        }
        // 组长：s.id = ? 和 s.leader_id = ?
        return [(int) $actor['id'], (int) $actor['id']];
    }

    private function sql(array $actor): string
    {
        if (KfStaff::isSuper($actor)) {
            $where = '1 = 1';
        } elseif ($actor['role'] === 'manager') {
            // 自己 + 自己名下的组长 + 这些组长名下的客服
            $where = "(s.id = ? OR (s.role = 'leader' AND s.manager_id = ?)
                       OR (s.role = 'agent' AND s.leader_id IN (
                            SELECT l.id FROM kf_staff l WHERE l.role = 'leader' AND l.manager_id = ?
                       )))";
        } else {
            // 组长：自己 + 本组客服
            $where = "(s.id = ? OR (s.role = 'agent' AND s.leader_id = ?))";
        }

        return "SELECT s.id, s.staff_no, s.name, s.role, s.status,
                       s.leader_id, s.manager_id,
                       l.name AS leader_name, m.name AS manager_name,
                       COALESCE(t.doing, 0) AS doing,
                       COALESCE(t.done, 0)  AS done,
                       COALESCE(t.total, 0) AS total
                FROM kf_staff s
                LEFT JOIN kf_staff l ON l.id = s.leader_id
                LEFT JOIN kf_staff m ON m.id = s.manager_id
                LEFT JOIN (
                    SELECT staff_id,
                           SUM(status IN (0, 1)) AS doing,
                           SUM(status IN (2, 3)) AS done,
                           COUNT(*)              AS total
                    FROM tickets
                    WHERE staff_id > 0
                    GROUP BY staff_id
                ) t ON t.staff_id = s.id
                WHERE $where
                ORDER BY FIELD(s.role, 'leader', 'agent'), s.leader_id ASC, s.id ASC";
    }
}
