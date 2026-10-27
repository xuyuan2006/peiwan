<?php
/**
 * 内容浏览接口：首页聚合、游戏分类、套餐、陪玩列表与详情、搜索。
 *
 * 这些接口全部允许游客访问；带 token 时会额外返回「是否已收藏/是否本人」等个性化信息。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\Presenter;
use App\Api\Request;
use App\Api\TokenAuth;
use App\CompanionService;
use App\ContentService;
use App\Db;
use App\OrderService;
use App\Setting;

class HomeController extends BaseController
{
    /** 首页一次性聚合，减少 App 启动时的请求数 */
    public function index(): void
    {
        $filter = $this->buildFilter();
        $result = CompanionService::search($filter, 1, 8);
        $games  = Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC LIMIT 8');

        $stats = [
            'companions' => (int) Db::value('SELECT COUNT(*) FROM companion_profiles WHERE audit_status = 1'),
            'orders'     => (int) Db::value('SELECT COUNT(*) FROM orders'),
            'completed'  => (int) Db::value('SELECT COUNT(*) FROM orders WHERE status = ?', [OrderService::STATUS_COMPLETED]),
        ];

        $this->ok([
            'site_name'       => site_name(),
            'site_notice'     => (string) Setting::get('site_notice', ''),
            'service_phone'   => (string) Setting::get('service_phone', ''),
            'banners'         => array_map([Presenter::class, 'banner'], ContentService::banners()),
            'announcements'   => array_map([Presenter::class, 'announcement'], ContentService::announcements()),
            'notice_interval' => Setting::int('notice_interval', 4),
            'banner_interval' => Setting::int('banner_interval', 5),
            'games'           => array_map([Presenter::class, 'game'], $games),
            'packages'        => array_map([Presenter::class, 'package'], ContentService::packages((int) $filter['game_id'])),
            'companions'      => array_map([Presenter::class, 'companion'], $result['rows']),
            'companion_total' => (int) $result['total'],
            'stats'           => $stats,
        ]);
    }

    /** 游戏分类（全部启用中的） */
    public function games(): void
    {
        $this->ok(array_map(
            [Presenter::class, 'game'],
            Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC')
        ));
    }

    /** 点单套餐列表（支持按游戏筛选） */
    public function packages(): void
    {
        $gameId = Request::int('game_id', 0);

        $this->ok([
            'games' => array_map(
                [Presenter::class, 'game'],
                Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC')
            ),
            'list' => array_map([Presenter::class, 'package'], ContentService::packages($gameId)),
        ]);
    }

    /** 陪玩瀑布流（分页） */
    public function companions(): void
    {
        $filter  = $this->buildFilter();
        $perPage = $this->perPage(12, 30);
        $page    = $this->page();

        $result = CompanionService::search($filter, $page, $perPage);

        $this->ok(array_merge(
            Presenter::page($result, [Presenter::class, 'companion']),
            [
                'games' => array_map(
                    [Presenter::class, 'game'],
                    Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC')
                ),
            ]
        ));
    }

    /** 陪玩主页：资料 + 服务项目 + 评价 + 统计 */
    public function companion(): void
    {
        $id = Request::int('id', 0);
        if ($id <= 0) {
            throw ApiException::invalid('缺少陪玩 ID');
        }

        $data = CompanionService::detail($id);
        if (!$data) {
            throw ApiException::notFound('该陪玩不存在');
        }

        $me     = TokenAuth::id();
        $isSelf = $me > 0 && $me === $id;

        // 注意：这里刻意用 403 而不是 404。
        // 线上 nginx 配了 error_page 404，会把 404 响应体替换成站点的 HTML 404 页，
        // 导致 App 拿不到具体原因（「尚未通过审核」会退化成「内容不存在」）。
        // 403 不在 nginx 的拦截范围内，文案能原样传到客户端。
        if ((int) $data['profile']['audit_status'] !== 1 && !$isSelf && !TokenAuth::isAdmin()) {
            throw ApiException::forbidden('该陪玩尚未通过审核');
        }
        if ((int) $data['profile']['user_status'] !== 1 && !TokenAuth::isAdmin()) {
            throw ApiException::forbidden('该陪玩账号已被禁用');
        }

        $stats = $data['stats'] ?: [];

        // CompanionService::detail() 的 profile 行没有 min_price / skill_count
        // （那两个是列表 SQL 里的子查询算出来的），直接交给 Presenter 会恒显示「面议」。
        // 这里用已经查出来的技能列表补上，保证详情页与列表页口径一致。
        $profile = $data['profile'];
        if (!array_key_exists('min_price', $profile)) {
            $prices = [];
            foreach ($data['skills'] as $skill) {
                $prices[] = (float) $skill['price'];
            }
            $profile['min_price']   = $prices ? min($prices) : null;
            $profile['skill_count'] = count($data['skills']);
        }

        $this->ok([
            'profile' => Presenter::companion($profile),
            'skills'  => array_map([Presenter::class, 'skill'], $data['skills']),
            'reviews' => array_map([Presenter::class, 'review'], $data['reviews']),
            'stats'   => [
                'total'     => (int) ($stats['total'] ?? 0),
                'completed' => (int) ($stats['completed'] ?? 0),
            ],
        ]);
    }

    /** 搜索：同时命中陪玩与套餐，供 App 搜索页使用 */
    public function search(): void
    {
        $keyword = Request::str('keyword');
        if ($keyword === '') {
            throw ApiException::invalid('请输入搜索关键词');
        }

        $filter = $this->buildFilter();
        $filter['keyword'] = $keyword;

        $result = CompanionService::search($filter, 1, 20);

        // 套餐没有独立检索方法，这里按标题/描述模糊匹配（走预处理，安全）
        $kw       = '%' . like_escape($keyword) . '%';
        $packages = Db::all(
            'SELECT * FROM order_packages
             WHERE status = 1 AND (title LIKE ? OR description LIKE ?)
             ORDER BY sort DESC, id DESC LIMIT 20',
            [$kw, $kw]
        );

        $this->ok([
            'keyword'    => $keyword,
            'companions' => array_map([Presenter::class, 'companion'], $result['rows']),
            'packages'   => array_map([Presenter::class, 'package'], $packages),
        ]);
    }

    // ---------------------------------------------------------------- 内部

    /** 陪玩列表筛选条件（与网页端 home/companions 完全一致） */
    private function buildFilter(): array
    {
        return [
            'game_id'     => Request::int('game_id', 0),
            'gender'      => Request::int('gender', 0),
            'keyword'     => Request::str('keyword'),
            'sort'        => Request::str('sort', 'default'),
            'online_only' => Request::int('online_only', 0),
            // 价格区间用字符串：CompanionService 靠 !== '' 判断「用户到底填没填」
            'min_price'   => Request::str('min_price', ''),
            'max_price'   => Request::str('max_price', ''),
        ];
    }
}
