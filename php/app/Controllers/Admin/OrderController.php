<?php
/**
 * 后台订单管理
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Db;
use App\OrderService;
use App\Upload;

class OrderController extends BaseController
{
    /** 列表筛选条件（列表与导出共用） */
    private function currentFilter(): array
    {
        $filter = [
            'status'     => input_str('status', ''),
            'keyword'    => input_str('keyword'),
            'date_start' => input_str('date_start'),
            'date_end'   => input_str('date_end'),
        ];
        if ($filter['status'] !== '' && !ctype_digit($filter['status'])) {
            $filter['status'] = '';
        }
        return $filter;
    }

    public function index()
    {
        $filter = $this->currentFilter();

        $result = OrderService::paginate($filter, $this->page(), 20);

        // 用状态常量拼 SQL，避免魔法数字与 OrderService 定义漂移
        $pendingPay     = OrderService::STATUS_PENDING_PAY;
        $inService      = OrderService::STATUS_IN_SERVICE;
        $pendingConfirm = OrderService::STATUS_PENDING_CONFIRM;
        $refunding      = OrderService::STATUS_REFUNDING;
        $completed      = OrderService::STATUS_COMPLETED;

        $counts = Db::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = $pendingPay THEN 1 ELSE 0 END) AS pending_pay,
                    SUM(CASE WHEN status IN ($inService, $pendingConfirm) THEN 1 ELSE 0 END) AS serving,
                    SUM(CASE WHEN status = $refunding THEN 1 ELSE 0 END) AS refunding,
                    SUM(CASE WHEN status = $completed THEN 1 ELSE 0 END) AS completed
             FROM orders"
        );

        $this->view('admin/order/index', [
            'title'  => '订单管理',
            'result' => $result,
            'filter' => $filter,
            'counts' => $counts ?: [],
        ]);
    }

    /** 导出当前筛选条件下的订单流水（CSV，供财务对账） */
    public function export()
    {
        $filter = $this->currentFilter();
        $rows   = OrderService::paginate($filter, 1, 5000)['rows'];

        $filename = 'orders_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM，方便 Excel 正确识别 UTF-8

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            '订单号', '下单用户', '陪玩', '游戏', '服务内容', '数量', '单价',
            '订单金额', '平台抽成', '陪玩实收', '订单状态', '下单时间', '支付时间', '完成时间', '确认时间',
        ]);
        foreach ($rows as $o) {
            fputcsv($out, [
                $o['order_no'],
                $o['user_nickname'],
                $o['companion_nickname'],
                $o['game_name'],
                $o['skill_title'],
                (int) $o['quantity'],
                money((float) $o['unit_price']),
                money((float) $o['amount']),
                money((float) $o['platform_fee']),
                money((float) $o['companion_income']),
                order_status_text((int) $o['status']),
                (string) $o['created_at'],
                (string) ($o['pay_time'] ?: ''),
                (string) ($o['finish_time'] ?: ''),
                (string) ($o['confirm_time'] ?: ''),
            ]);
        }
        fclose($out);

        Auth::logAdmin('导出订单', '筛选条件 ' . json_encode($filter, JSON_UNESCAPED_UNICODE) . '，共 ' . count($rows) . ' 条');
        exit;
    }

    /** 代客下单：表单（可自定金额） */
    public function create()
    {
        $users = Db::all(
            "SELECT id, nickname, username, phone FROM users WHERE status = 1 AND role <> 'admin' ORDER BY id DESC LIMIT 300"
        );
        $companions = Db::all(
            "SELECT u.id, u.nickname, p.score
             FROM users u JOIN companion_profiles p ON p.user_id = u.id
             WHERE p.audit_status = 1 AND u.status = 1
             ORDER BY u.id DESC LIMIT 300"
        );
        $skills = Db::all(
            "SELECT s.id, s.companion_id, s.title, s.price, s.service_type
             FROM companion_skills s
             WHERE s.status = 1
             ORDER BY s.id DESC LIMIT 500"
        );

        $this->view('admin/order/create', [
            'title'      => '代客下单',
            'users'      => $users,
            'companions' => $companions,
            'skills'     => $skills,
            'preset_user'      => input_int('user_id', 0),
            'preset_companion' => input_int('companion_id', 0),
        ]);
    }

    /** 代客下单：提交 */
    public function saveByAdmin()
    {
        require_post();
        verify_csrf();

        try {
            $order = OrderService::adminCreate(
                Auth::id(),
                input_int('user_id', 0),
                input_int('companion_id', 0),
                input_int('skill_id', 0),
                input_int('quantity', 1),
                input_float('unit_price', 0),
                input_str('remark'),
                input_int('pay_now', 0) === 1
            );

            Auth::logAdmin('代客下单', '订单ID ' . (int) $order['id'] . '，金额 ' . money((float) $order['amount'])
                . '，用户ID ' . (int) $order['user_id'] . '，陪玩ID ' . (int) $order['companion_id']);

            flash('success', '订单已创建，金额 ' . money((float) $order['amount']) . ' 元');
            redirect(admin_url('order/detail', ['id' => (int) $order['id']]));
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            keep_old($_POST);
            redirect(admin_url('order/create'));
        }
    }

    /** 发布接单任务：表单（任务会进入接单大厅，由陪玩抢单） */
    public function publish()
    {
        $users = Db::all(
            "SELECT id, nickname, username, phone FROM users WHERE status = 1 AND role <> 'admin' ORDER BY id DESC LIMIT 300"
        );
        $games = Db::all('SELECT id, name FROM games WHERE status = 1 ORDER BY sort DESC, id ASC');

        $poolCount = (int) Db::value(
            'SELECT COUNT(*) FROM orders WHERE is_public = 1 AND companion_id = 0 AND status = ?',
            [OrderService::STATUS_PENDING_ACCEPT]
        );

        $this->view('admin/order/publish', [
            'title'       => '发布接单任务',
            'users'       => $users,
            'games'       => $games,
            'pool_count'  => $poolCount,
            'preset_user' => input_int('user_id', 0),
        ]);
    }

    /** 发布接单任务：提交 */
    public function savePublish()
    {
        require_post();
        verify_csrf();

        try {
            // 先做基本校验，避免金额非法时留下孤儿图片
            $title = input_str('title');
            $price = input_float('unit_price', 0);
            if ($title === '') {
                throw new \RuntimeException('请填写任务标题，例如：三角洲行动 上分');
            }
            if ($price <= 0) {
                throw new \RuntimeException('请填写正确的单价');
            }

            $cover = Upload::image('cover', 'task');

            $order = OrderService::adminPublishTask(
                Auth::id(),
                input_int('user_id', 0),
                $title,
                input_int('game_id', 0),
                input_str('service_type', 'custom'),
                input_int('quantity', 1),
                $price,
                (string) $cover,
                input_str('remark'),
                input_int('pay_now', 1) === 1
            );

            Auth::logAdmin(
                '发布接单任务',
                '订单ID ' . (int) $order['id'] . '：' . $order['skill_title'] . '，金额 ' . money((float) $order['amount'])
            );

            flash('success', (int) $order['status'] === OrderService::STATUS_PENDING_ACCEPT
                ? '任务已发布到接单大厅，陪玩可以接单了'
                : '任务已创建但未付款，付款后才会进入接单大厅');

            redirect(admin_url('order/detail', ['id' => (int) $order['id']]));
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            keep_old($_POST);
            redirect(admin_url('order/publish'));
        }
    }

    public function detail()
    {
        $id    = input_int('id', 0);
        $order = Db::one(
            'SELECT o.*, u.nickname AS user_nickname, u.avatar AS user_avatar, u.phone AS user_phone,
                    c.nickname AS companion_nickname, c.avatar AS companion_avatar, c.phone AS companion_phone
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN users c ON c.id = o.companion_id
             WHERE o.id = ? LIMIT 1',
            [$id]
        );
        if (!$order) {
            abort(404, '订单不存在');
        }

        $logs   = Db::all('SELECT * FROM order_logs WHERE order_id = ? ORDER BY id ASC', [$id]);
        $review = Db::one('SELECT * FROM order_reviews WHERE order_id = ? LIMIT 1', [$id]);

        $this->view('admin/order/detail', [
            'title'    => '订单详情',
            'order'    => $order,
            'logs'     => $logs,
            'review'   => $review,
            'messages' => OrderService::messages($id),
        ]);
    }

    /** 后台在订单里以「客服」身份介入（内容会显示在双方的订单页） */
    public function replyMessage()
    {
        require_post();
        verify_csrf();

        $id      = input_int('id', 0);
        $content = input_str('content');

        $this->run(function () use ($id, $content) {
            OrderService::sendMessage($id, Auth::id(), $content);
            Auth::logAdmin('订单内客服发言', '订单ID ' . $id . '：' . mb_substr($content, 0, 60));
            return true;
        }, '已发送，双方订单页都会看到这条「客服」消息', admin_url('order/detail', ['id' => $id]));
    }

    /** 退款审核 */
    public function refundAudit()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $agree  = input_int('agree', 1) === 1;
        $remark = input_str('remark');

        $this->run(function () use ($id, $agree, $remark) {
            OrderService::auditRefund($id, Auth::id(), $agree, $remark);
            Auth::logAdmin('订单退款审核', '订单ID ' . $id . ' ' . ($agree ? '同意退款' : '驳回退款') . ' ' . $remark);
            return true;
        }, '处理完成', admin_url('order/detail', ['id' => $id]));
    }

    /** 强制完成并结算 */
    public function complete()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            OrderService::confirm($id, Auth::id(), 'admin');
            Auth::logAdmin('订单强制完成', '订单ID ' . $id);
            return true;
        }, '订单已完成并结算', admin_url('order/detail', ['id' => $id]));
    }

    /** 强制关闭 / 退款 */
    public function close()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $reason = input_str('reason', '平台关闭订单');
        $this->run(function () use ($id, $reason) {
            $order  = OrderService::find($id);
            $status = (int) $order['status'];

            if ($status === OrderService::STATUS_REFUNDING) {
                // 退款审核中：直接同意退款
                OrderService::auditRefund($id, Auth::id(), true, $reason);
            } elseif (in_array($status, [OrderService::STATUS_IN_SERVICE, OrderService::STATUS_PENDING_CONFIRM], true)) {
                // 服务中/待确认：后台强制退款关闭
                OrderService::adminRefund($id, Auth::id(), $reason);
            } else {
                OrderService::cancel($id, Auth::id(), 'admin', $reason);
            }

            Auth::logAdmin('订单关闭', '订单ID ' . $id . '（原状态 ' . $status . '）：' . $reason);
            return true;
        }, '订单已关闭并退款', admin_url('order/detail', ['id' => $id]));
    }

    /** 手动执行自动任务（超时关闭 + 自动确认） */
    public function autoTask()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $closed   = OrderService::autoCloseExpired();
            $confirm  = OrderService::autoConfirmFinished();
            $recharge = \App\FinanceService::autoCloseExpiredRecharge(24);
            Auth::logAdmin('执行自动任务', '关闭超时订单 ' . $closed . ' 笔，自动确认 ' . $confirm . ' 笔，关闭未支付充值单 ' . $recharge . ' 笔');
            return ['closed' => $closed, 'confirmed' => $confirm, 'recharge_closed' => $recharge];
        }, '自动任务执行完成', admin_url('order/index'));
    }
}
