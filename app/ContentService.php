<?php
/**
 * 站点内容运营：公告（前台顶部走马灯滚动）与广告位（首页轮播）
 *
 * 前端查询在表不存在时返回空数组，保证老库未升级时页面也不会报错。
 */
declare(strict_types=1);

namespace App;

class ContentService
{
    private const TABLES = ['announcements', 'banners', 'order_packages'];

    // ---------------------------------------------------------------- 前端

    /** 启用中的公告 */
    public static function announcements(): array
    {
        try {
            return Db::all('SELECT * FROM announcements WHERE status = 1 ORDER BY sort DESC, id DESC LIMIT 20');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 启用中的广告（必须有图） */
    public static function banners(): array
    {
        try {
            return Db::all("SELECT * FROM banners WHERE status = 1 AND image <> '' ORDER BY sort DESC, id DESC LIMIT 10");
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------- 后台

    public static function announcementList(int $page = 1, int $perPage = 20): array
    {
        $total  = (int) Db::value('SELECT COUNT(*) FROM announcements');
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'rows'     => Db::all("SELECT * FROM announcements ORDER BY sort DESC, id DESC LIMIT $perPage OFFSET $offset"),
        ];
    }

    public static function bannerList(int $page = 1, int $perPage = 20): array
    {
        $total  = (int) Db::value('SELECT COUNT(*) FROM banners');
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'rows'     => Db::all("SELECT * FROM banners ORDER BY sort DESC, id DESC LIMIT $perPage OFFSET $offset"),
        ];
    }

    public static function saveAnnouncement(int $id, string $content, string $linkUrl, int $sort, int $status): int
    {
        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('公告内容不能为空');
        }
        if (mb_strlen($content) > 200) {
            throw new \RuntimeException('公告内容不能超过 200 字');
        }

        $data = [
            'content'    => mb_substr($content, 0, 200),
            'link_url'   => self::safeUrl($linkUrl),
            'sort'       => $sort,
            'status'     => $status === 1 ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            if (!Db::one('SELECT id FROM announcements WHERE id = ? LIMIT 1', [$id])) {
                throw new \RuntimeException('公告不存在');
            }
            Db::update('announcements', $data, 'id = ?', [$id]);
            return $id;
        }

        $data['created_at'] = now();
        return Db::insert('announcements', $data);
    }

    public static function saveBanner(int $id, string $title, string $image, string $linkUrl, int $sort, int $status): int
    {
        $title = mb_substr(trim($title), 0, 100);
        $image = trim($image);

        $data = [
            'title'      => $title,
            'link_url'   => self::safeUrl($linkUrl),
            'sort'       => $sort,
            'status'     => $status === 1 ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            $old = Db::one('SELECT * FROM banners WHERE id = ? LIMIT 1', [$id]);
            if (!$old) {
                throw new \RuntimeException('广告不存在');
            }
            // 没重新上传就保留原图
            $data['image'] = $image !== '' ? mb_substr($image, 0, 255) : (string) $old['image'];
            if ($data['image'] === '') {
                throw new \RuntimeException('请上传广告图片');
            }
            Db::update('banners', $data, 'id = ?', [$id]);
            return $id;
        }

        if ($image === '') {
            throw new \RuntimeException('请上传广告图片');
        }
        $data['image']      = mb_substr($image, 0, 255);
        $data['created_at'] = now();

        return Db::insert('banners', $data);
    }

    public static function delete(string $table, int $id): void
    {
        self::assertTable($table);
        $row = Db::one("SELECT * FROM `$table` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) {
            throw new \RuntimeException('记录不存在');
        }

        Db::query("DELETE FROM `$table` WHERE id = ?", [$id]);

        // 广告（banners.image）与套餐（order_packages.cover）删除时顺手清掉图片文件
        if (in_array($table, ['banners', 'order_packages'], true)) {
            $image = $table === 'banners' ? (string) $row['image'] : (string) $row['cover'];
            if ($image !== '') {
                $path = APP_ROOT . '/public/' . ltrim($image, '/');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /** 启用 / 停用切换 */
    public static function toggle(string $table, int $id): int
    {
        self::assertTable($table);
        $row = Db::one("SELECT * FROM `$table` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) {
            throw new \RuntimeException('记录不存在');
        }

        $status = (int) $row['status'] === 1 ? 0 : 1;
        Db::update($table, ['status' => $status, 'updated_at' => now()], 'id = ?', [$id]);

        return $status;
    }

    // ---------------------------------------------------------------- 内部

    private static function assertTable(string $table): void
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new \RuntimeException('不支持的数据表');
        }
    }

    /** 只允许 http/https 链接：可以直接填域名（自动补 https），危险协议一律拒绝 */
    private static function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // 没写协议就自动补 https（www.xxx.com、xxx.com/xxx 都能用）
        if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('链接只支持 http / https 地址；也可以直接填域名，系统会自动补上 https://');
        }
        return mb_substr($url, 0, 255);
    }

    // ---------------------------------------------------------------- 点单套餐

    /** 前端：启用中的点单套餐（可按游戏筛选，0 = 全部） */
    public static function packages(int $gameId = 0): array
    {
        try {
            if ($gameId > 0) {
                return Db::all(
                    'SELECT p.*, g.name AS game_name FROM order_packages p
                     LEFT JOIN games g ON g.id = p.game_id
                     WHERE p.status = 1 AND (p.game_id = ? OR p.game_id = 0)
                     ORDER BY p.game_id = ? DESC, p.sort DESC, p.id DESC LIMIT 50',
                    [$gameId, $gameId]
                );
            }

            return Db::all(
                'SELECT p.*, g.name AS game_name FROM order_packages p
                 LEFT JOIN games g ON g.id = p.game_id
                 WHERE p.status = 1
                 ORDER BY p.sort DESC, p.id DESC LIMIT 50'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    public static function findPackage(int $id): ?array
    {
        return Db::one('SELECT * FROM order_packages WHERE id = ? LIMIT 1', [$id]);
    }

    public static function packageList(int $page = 1, int $perPage = 20): array
    {
        $total  = (int) Db::value('SELECT COUNT(*) FROM order_packages');
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'rows'     => Db::all(
                'SELECT p.*, g.name AS game_name FROM order_packages p
                 LEFT JOIN games g ON g.id = p.game_id
                 ORDER BY p.sort DESC, p.id DESC
                 LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
            ),
        ];
    }

    public static function savePackage(int $id, string $title, int $gameId, float $price, string $cover, string $description, int $sort, int $status): int
    {
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException('请填写套餐标题，例如：王者荣耀 陪玩 5 小时');
        }
        if (mb_strlen($title) > 100) {
            throw new \RuntimeException('套餐标题不能超过 100 字');
        }

        $price = safe_float($price);
        if ($price <= 0) {
            throw new \RuntimeException('请填写正确的套餐价格');
        }
        if ($price > 99999) {
            throw new \RuntimeException('价格过高，请检查');
        }

        if ($gameId > 0 && !Db::one('SELECT id FROM games WHERE id = ? LIMIT 1', [$gameId])) {
            throw new \RuntimeException('所选游戏不存在');
        }

        $data = [
            'title'       => mb_substr($title, 0, 100),
            'game_id'     => $gameId,
            'price'       => $price,
            'description' => mb_substr(trim($description), 0, 500),
            'sort'        => $sort,
            'status'      => $status === 1 ? 1 : 0,
            'updated_at'  => now(),
        ];

        $cover = trim($cover);
        if ($id > 0) {
            $old = self::findPackage($id);
            if (!$old) {
                throw new \RuntimeException('套餐不存在');
            }
            $data['cover'] = $cover !== '' ? mb_substr($cover, 0, 255) : (string) $old['cover'];
            Db::update('order_packages', $data, 'id = ?', [$id]);
            return $id;
        }

        $data['cover']      = mb_substr($cover, 0, 255);
        $data['created_at'] = now();
        return Db::insert('order_packages', $data);
    }
}
