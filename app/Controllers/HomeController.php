<?php
/**
 * 首页 / 陪玩瀑布流 / 陪玩详情
 */
declare(strict_types=1);

namespace App\Controllers;

use App\CompanionService;
use App\ContentService;
use App\Db;
use App\OrderService;
use App\View;

class HomeController extends BaseController
{
    /** 陪玩列表的筛选条件（列表页与无限滚动接口共用） */
    private function buildFilter(): array
    {
        return [
            'game_id'     => input_int('game_id', 0),
            'gender'      => input_int('gender', 0),
            'keyword'     => input_str('keyword'),
            'sort'        => input_str('sort', 'default'),
            'online_only' => input_int('online_only', 0),
            'min_price'   => input_str('min_price'),
            'max_price'   => input_str('max_price'),
        ];
    }

    /** 首页：推荐陪玩 + 套餐 + 广告 */
    public function index()
    {
        $filter = $this->buildFilter();
        // 注意：这里不要再写 $filter['sort'] = 'default'，否则用户在首页选的排序会被吞掉

        $result = CompanionService::search($filter, 1, 8);
        $games  = Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC LIMIT 8');

        $stats = [
            'companions' => (int) Db::value('SELECT COUNT(*) FROM companion_profiles WHERE audit_status = 1'),
            'orders'     => (int) Db::value('SELECT COUNT(*) FROM orders'),
            'completed'  => (int) Db::value('SELECT COUNT(*) FROM orders WHERE status = ?', [OrderService::STATUS_COMPLETED]),
        ];

        $this->view('home/index', [
            'title'    => site_name(),
            'filter'   => $filter,
            'games'    => $games,
            'result'   => $result,
            'stats'    => $stats,
            'banners'  => ContentService::banners(),
            'packages' => ContentService::packages((int) $filter['game_id']),
        ]);
    }

    /** 直接点单：套餐列表（支持按游戏筛选） */
    public function packages()
    {
        $gameId = input_int('game_id', 0);

        $this->view('home/packages', [
            'title'    => '直接点单',
            'game_id'  => $gameId,
            'games'    => Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC'),
            'packages' => ContentService::packages($gameId),
        ]);
    }

    /** 陪玩：瀑布流列表（下拉无限加载） */
    public function companions()
    {
        $filter  = $this->buildFilter();
        $perPage = 12;

        $result = CompanionService::search($filter, $this->page(), $perPage);
        $games  = Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC');

        $this->view('home/companions', [
            'title'    => '找陪玩 - ' . site_name(),
            'filter'   => $filter,
            'games'    => $games,
            'result'   => $result,
            'per_page' => $perPage,
        ]);
    }

    /** 无限滚动接口：返回下一页卡片 HTML */
    public function more()
    {
        $filter  = $this->buildFilter();
        $perPage = 12;
        $page    = max(2, input_int('page', 2));

        $result = CompanionService::search($filter, $page, $perPage);

        $html = '';
        foreach ($result['rows'] as $c) {
            $html .= View::capture('partials/companion_card', ['c' => $c]);
        }

        $totalPage = (int) ceil($result['total'] / $perPage);

        json_out([
            'code' => 0,
            'msg'  => 'ok',
            'data' => [
                'html'     => $html,
                'page'     => $page,
                'total'    => (int) $result['total'],
                'has_more' => $page < $totalPage,
            ],
        ]);
    }

    /** 陪玩详情 */
    public function companion()
    {
        $id   = input_int('id', 0);
        $data = $id > 0 ? CompanionService::detail($id) : null;

        if (!$data) {
            abort(404, '该陪玩不存在');
        }
        $me     = user();
        $isSelf = $me !== null && (int) $me['id'] === $id;
        if ((int) $data['profile']['audit_status'] !== 1 && !$isSelf && !is_admin()) {
            abort(404, '该陪玩尚未通过审核');
        }
        if ((int) $data['profile']['user_status'] !== 1 && !is_admin()) {
            abort(404, '该陪玩账号已被禁用');
        }

        $this->view('home/companion', [
            'title'   => $data['profile']['nickname'] . ' 的主页 - ' . site_name(),
            'profile' => $data['profile'],
            'skills'  => $data['skills'],
            'reviews' => $data['reviews'],
            'stats'   => $data['stats'],
        ]);
    }
}
