<?php
/**
 * 评价接口：用户评价订单、陪玩回复评价、查看我收到/我发出的评价。
 *
 * 与网页端 ReviewController 的区别：那里是「flash + redirect」，这里是纯 JSON，
 * 校验规则（评分区间、字数、只能评价自己已完成的订单且不可重复评价）完全照搬。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\Presenter;
use App\Api\Request;
use App\CompanionService;
use App\Db;
use App\OrderService;

class ReviewController extends BaseController
{
    /** 评价订单（仅下单方本人，订单须已完成，且未评价过） */
    public function create(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        $orderId   = Request::int('order_id', 0);
        $score     = Request::int('score', 5);
        $content   = Request::str('content');
        $tags      = implode(',', Request::strList('tags', 10, 20));
        $anonymous = Request::int('is_anonymous', 0) === 1 ? 1 : 0;

        if ($orderId <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }
        if ($score < 1 || $score > 5) {
            throw ApiException::invalid('评分需在 1~5 星之间');
        }
        if (mb_strlen($content) > 500) {
            throw ApiException::invalid('评价内容不能超过 500 字');
        }

        Db::tx(function () use ($orderId, $uid, $score, $content, $tags, $anonymous) {
            // FOR UPDATE 行锁：防止同一订单被并发提交两条评价
            $order = Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$order) {
                throw new \RuntimeException('订单不存在');
            }
            if ((int) $order['user_id'] !== $uid) {
                throw new \RuntimeException('只能评价自己的订单');
            }
            if ((int) $order['status'] !== OrderService::STATUS_COMPLETED) {
                throw new \RuntimeException('订单完成后才能评价');
            }
            if ((int) $order['is_reviewed'] === 1) {
                throw new \RuntimeException('该订单已评价过了');
            }

            Db::insert('order_reviews', [
                'order_id'     => $orderId,
                'user_id'      => $uid,
                'companion_id' => (int) $order['companion_id'],
                'score'        => $score,
                'content'      => mb_substr($content, 0, 500),
                'tags'         => mb_substr($tags, 0, 200),
                'is_anonymous' => $anonymous,
                'created_at'   => now(),
            ]);
            Db::update('orders', ['is_reviewed' => 1, 'updated_at' => now()], 'id = ?', [$orderId]);
            CompanionService::recalcScore((int) $order['companion_id']);
        });

        $review = Db::one(
            'SELECT r.*, u.nickname, u.avatar FROM order_reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.order_id = ? LIMIT 1',
            [$orderId]
        );

        $this->ok(['review' => Presenter::review($review)], '评价已提交，感谢你的反馈');
    }

    /** 陪玩回复评价（可重复覆盖，与网页端一致） */
    public function reply(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        $reviewId = Request::int('review_id', 0);
        $reply    = Request::str('reply');

        if ($reviewId <= 0) {
            throw ApiException::invalid('缺少评价 ID');
        }
        if ($reply === '') {
            throw ApiException::invalid('请填写回复内容');
        }

        $review = Db::one('SELECT * FROM order_reviews WHERE id = ? LIMIT 1', [$reviewId]);
        if (!$review) {
            throw ApiException::notFound('评价不存在');
        }
        if ((int) $review['companion_id'] !== $uid) {
            throw ApiException::forbidden('只能回复自己的评价');
        }

        Db::update('order_reviews', [
            'reply'    => mb_substr($reply, 0, 500),
            'reply_at' => now(),
        ], 'id = ?', [$reviewId]);

        $this->ok(null, '回复成功');
    }

    /** 我收到（作为陪玩）与我发出（作为买家）的评价 */
    public function mine(): void
    {
        $uid     = (int) $this->user()['id'];
        $perPage = $this->perPage(10, 50);
        $page    = $this->page();
        $offset  = ($page - 1) * $perPage;

        $receivedTotal = (int) Db::value('SELECT COUNT(*) FROM order_reviews WHERE companion_id = ?', [$uid]);
        $received = Db::all(
            "SELECT r.*, u.nickname, u.avatar FROM order_reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.companion_id = ?
             ORDER BY r.id DESC LIMIT $perPage OFFSET $offset",
            [$uid]
        );

        $givenTotal = (int) Db::value('SELECT COUNT(*) FROM order_reviews WHERE user_id = ?', [$uid]);
        $given = Db::all(
            "SELECT r.*, u.nickname, u.avatar FROM order_reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.user_id = ?
             ORDER BY r.id DESC LIMIT $perPage OFFSET $offset",
            [$uid]
        );

        $this->ok([
            'received' => Presenter::page(
                ['rows' => $received, 'total' => $receivedTotal, 'page' => $page, 'per_page' => $perPage],
                [Presenter::class, 'review']
            ),
            'given' => Presenter::page(
                ['rows' => $given, 'total' => $givenTotal, 'page' => $page, 'per_page' => $perPage],
                [Presenter::class, 'review']
            ),
        ]);
    }
}
