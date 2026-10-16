<?php
/**
 * 后台运营内容：公告管理（走马灯）与广告管理（首页轮播）
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\ContentService;
use App\Db;
use App\Upload;

class ContentController extends BaseController
{
    /** 公告管理 */
    public function index()
    {
        $editId = input_int('edit', 0);

        $this->view('admin/content/index', [
            'title'  => '公告管理',
            'result' => ContentService::announcementList($this->page(), 20),
            'edit'   => $editId > 0 ? Db::one('SELECT * FROM announcements WHERE id = ? LIMIT 1', [$editId]) : null,
        ]);
    }

    public function saveAnnouncement()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $content = input_str('content');
            $id      = ContentService::saveAnnouncement(
                input_int('id', 0),
                $content,
                input_str('link_url'),
                input_int('sort', 0),
                input_int('status', 1)
            );
            Auth::logAdmin('保存公告', 'ID ' . $id . '：' . mb_substr($content, 0, 60));
            return true;
        }, '公告已保存', admin_url('content/index'));
    }

    public function deleteAnnouncement()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            ContentService::delete('announcements', $id);
            Auth::logAdmin('删除公告', 'ID ' . $id);
            return true;
        }, '公告已删除', admin_url('content/index'));
    }

    /** 广告管理 */
    public function banners()
    {
        $editId = input_int('edit', 0);

        $this->view('admin/content/banners', [
            'title'  => '广告管理',
            'result' => ContentService::bannerList($this->page(), 20),
            'edit'   => $editId > 0 ? Db::one('SELECT * FROM banners WHERE id = ? LIMIT 1', [$editId]) : null,
        ]);
    }

    public function saveBanner()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $id    = input_int('id', 0);
            $title = input_str('title');
            // 图片选填：编辑时不重新上传就保留原图（在 ContentService 里处理）
            $image = Upload::image('image', 'banner');

            $saved = ContentService::saveBanner(
                $id,
                $title,
                (string) $image,
                input_str('link_url'),
                input_int('sort', 0),
                input_int('status', 1)
            );
            Auth::logAdmin('保存广告', 'ID ' . $saved . '：' . $title);
            return true;
        }, '广告已保存', admin_url('content/banners'));
    }

    public function deleteBanner()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            ContentService::delete('banners', $id);
            Auth::logAdmin('删除广告', 'ID ' . $id);
            return true;
        }, '广告已删除', admin_url('content/banners'));
    }

    /** 点单套餐管理（后台预置一口价单子，普通用户可直接下单） */
    public function packages()
    {
        $editId = input_int('edit', 0);

        $this->view('admin/content/packages', [
            'title'  => '点单套餐',
            'result' => ContentService::packageList($this->page(), 20),
            'edit'   => $editId > 0 ? ContentService::findPackage($editId) : null,
            'games'  => Db::all('SELECT id, name FROM games WHERE status = 1 ORDER BY sort DESC, id ASC'),
        ]);
    }

    public function savePackage()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $id    = input_int('id', 0);
            $title = input_str('title');
            // 图片选填：编辑时不重新上传就保留原图
            $cover = Upload::image('cover', 'package');

            $saved = ContentService::savePackage(
                $id,
                $title,
                input_int('game_id', 0),
                input_float('price', 0),
                (string) $cover,
                input_str('description'),
                input_int('sort', 0),
                input_int('status', 1)
            );
            Auth::logAdmin('保存点单套餐', 'ID ' . $saved . '：' . $title);
            return true;
        }, '套餐已保存', admin_url('content/packages'));
    }

    public function deletePackage()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            ContentService::delete('order_packages', $id);
            Auth::logAdmin('删除点单套餐', 'ID ' . $id);
            return true;
        }, '套餐已删除', admin_url('content/packages'));
    }

    /** 启用 / 停用（公告与广告共用，table 参数白名单校验） */
    public function toggle()
    {
        require_post();
        verify_csrf();

        $table = input_str('table');
        $id    = input_int('id', 0);
        $back  = 'content/index';
        if ($table === 'banners') {
            $back = 'content/banners';
        } elseif ($table === 'order_packages') {
            $back = 'content/packages';
        }

        $this->run(function () use ($table, $id) {
            $status = ContentService::toggle($table, $id);
            Auth::logAdmin('切换启用状态', $table . ' ID ' . $id . ' → ' . ($status === 1 ? '启用' : '停用'));
            return true;
        }, '状态已更新', admin_url($back));
    }
}
