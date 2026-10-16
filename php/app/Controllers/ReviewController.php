<?php
/**
 * 评价：用户评价订单、陪玩回复评价
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\CompanionService;
use App\Db;
use App\OrderService;

class ReviewController extends BaseController
{
    public function create()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $orderId  = input_int('order_id', 0);
        $score    = input_int('score', 5);
        $content  = input_str('content');
        $tagsArr  = input_arr('tags');
        $tags     = implode(',', array_slice(array_filter(array_map(function ($t) {
            return mb_substr(trim((string) $t), 0, 20);
        }, $tagsArr)), 0, 10));
        $anonymous = input_int('is_anonymous', 0);

        try {
            if ($score < 1 || $score > 5) {
                throw new \RuntimeException('评分需在 1~5 星之间');
            }
            if (mb_strlen($content) > 500) {
                throw new \RuntimeException('评价内容不能超过 500 字');
            }

            Db::tx(function () use ($orderId, $score, $content, $tags, $anonymous) {
                $order = Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
                if (!$order) {
                    throw new \RuntimeException('订单不存在');
                }
                if ((int) $order['user_id'] !== Auth::id()) {
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
                    'user_id'      => Auth::id(),
                    'companion_id' => (int) $order['companion_id'],
                    'score'        => $score,
                    'content'      => mb_substr($content, 0, 500),
                    'tags'         => mb_substr($tags, 0, 200),
                    'is_anonymous' => $anonymous === 1 ? 1 : 0,
                    'created_at'   => now(),
                ]);
                Db::update('orders', ['is_reviewed' => 1, 'updated_at' => now()], 'id = ?', [$orderId]);
                CompanionService::recalcScore((int) $order['companion_id']);
            });

            flash('success', '评价已提交，感谢你的反馈');
            redirect(url('order/detail', ['id' => $orderId]));
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect(url('order/detail', ['id' => $orderId]));
        }
    }

    public function reply()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $reviewId = input_int('review_id', 0);
        $reply    = input_str('reply');

        try {
            if ($reply === '') {
                throw new \RuntimeException('请填写回复内容');
            }
            $review = Db::one('SELECT * FROM order_reviews WHERE id = ? LIMIT 1', [$reviewId]);
            if (!$review) {
                throw new \RuntimeException('评价不存在');
            }
            if ((int) $review['companion_id'] !== Auth::id()) {
                throw new \RuntimeException('只能回复自己的评价');
            }
            Db::update('order_reviews', [
                'reply'    => mb_substr($reply, 0, 500),
                'reply_at' => now(),
            ], 'id = ?', [$reviewId]);

            flash('success', '回复成功');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect_back('companion/center');
    }
}
