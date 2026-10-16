<?php
/**
 * 陪玩相关业务：资料检索、入驻申请、审核、技能管理、评分重算。
 */
declare(strict_types=1);

namespace App;

class CompanionService
{
    /** 前台陪玩列表检索 */
    public static function search(array $filter, int $page = 1, int $perPage = 12): array
    {
        // 注意：不再要求「必须已上架服务项目」——只要审核通过就出现在列表里，
        // 否则刚设为陪玩、还没上架服务的人会一个都看不到（之前就是这个 bug）。
        // 也不再要求 u.role = 'companion'：管理员的角色不是 companion，但可能也是陪玩。
        $where  = [
            'p.audit_status = 1',
            'u.status = 1',
        ];
        $params = [];

        if (!empty($filter['game_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM companion_skills s WHERE s.companion_id = p.user_id AND s.status = 1 AND s.game_id = ?)';
            $params[] = (int) $filter['game_id'];
        }
        if (!empty($filter['gender'])) {
            $where[]  = 'u.gender = ?';
            $params[] = (int) $filter['gender'];
        }
        if (!empty($filter['online_only'])) {
            $where[] = 'p.online_status = 1';
        }
        if (!empty($filter['keyword'])) {
            $kw      = '%' . like_escape((string) $filter['keyword']) . '%';
            $where[] = '(u.nickname LIKE ? OR p.intro LIKE ? OR p.tags LIKE ? OR p.city LIKE ?)';
            array_push($params, $kw, $kw, $kw, $kw);
        }
        $priceWhere  = [];
        $priceParams = [];
        if (isset($filter['min_price']) && $filter['min_price'] !== '' && $filter['min_price'] !== null) {
            $priceWhere[]  = 's.price >= ?';
            $priceParams[] = (float) $filter['min_price'];
        }
        if (isset($filter['max_price']) && $filter['max_price'] !== '' && $filter['max_price'] !== null) {
            $priceWhere[]  = 's.price <= ?';
            $priceParams[] = (float) $filter['max_price'];
        }
        if ($priceWhere) {
            $where[] = 'EXISTS (SELECT 1 FROM companion_skills s WHERE s.companion_id = p.user_id AND s.status = 1 AND '
                     . implode(' AND ', $priceWhere) . ')';
            foreach ($priceParams as $pp) {
                $params[] = $pp;
            }
        }

        $whereSql = implode(' AND ', $where);

        $sort    = (string) ($filter['sort'] ?? 'default');
        // 默认排序：在线的排在最前面（用户最关心谁能马上接单）
        $orderBy = 'p.online_status DESC, p.is_recommend DESC, p.sort DESC, p.score DESC, p.id DESC';
        if ($sort === 'price_asc') {
            $orderBy = 'min_price ASC, p.id DESC';
        } elseif ($sort === 'price_desc') {
            $orderBy = 'min_price DESC, p.id DESC';
        } elseif ($sort === 'hot') {
            $orderBy = 'p.order_count DESC, p.id DESC';
        } elseif ($sort === 'score') {
            $orderBy = 'p.score DESC, p.review_count DESC, p.id DESC';
        } elseif ($sort === 'new') {
            $orderBy = 'p.id DESC';
        }

        $total = (int) Db::value(
            "SELECT COUNT(*) FROM companion_profiles p JOIN users u ON u.id = p.user_id WHERE $whereSql",
            $params
        );

        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows   = Db::all(
            "SELECT p.*, u.nickname, u.avatar, u.gender, u.id AS uid, u.credit_score,
                    (SELECT MIN(s.price) FROM companion_skills s WHERE s.companion_id = p.user_id AND s.status = 1) AS min_price,
                    (SELECT COUNT(*) FROM companion_skills s WHERE s.companion_id = p.user_id AND s.status = 1) AS skill_count
             FROM companion_profiles p
             JOIN users u ON u.id = p.user_id
             WHERE $whereSql
             ORDER BY $orderBy
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per_page' => $perPage];
    }

    /** 陪玩详情：资料 + 技能 + 评价 + 统计 */
    public static function detail(int $companionId): ?array
    {
        $profile = Db::one(
            'SELECT p.*, u.nickname, u.avatar, u.gender, u.status AS user_status, u.credit_score, u.created_at AS reg_time
             FROM companion_profiles p
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id = ? LIMIT 1',
            [$companionId]
        );
        if (!$profile) {
            return null;
        }

        $skills = Db::all(
            'SELECT s.*, g.`name` AS game_name
             FROM companion_skills s
             LEFT JOIN games g ON g.id = s.game_id
             WHERE s.companion_id = ? AND s.status = 1
             ORDER BY s.sort DESC, s.id DESC',
            [$companionId]
        );

        $reviews = Db::all(
            'SELECT r.*, u.nickname, u.avatar
             FROM order_reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.companion_id = ? AND r.status = 1
             ORDER BY r.id DESC
             LIMIT 20',
            [$companionId]
        );

        $stats = Db::one(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed
             FROM orders WHERE companion_id = ?",
            [OrderService::STATUS_COMPLETED, $companionId]
        );

        return [
            'profile' => $profile,
            'skills'  => $skills,
            'reviews' => $reviews,
            'stats'   => $stats ?: ['total' => 0, 'completed' => 0],
        ];
    }

    /** 申请成为陪玩 / 修改申请资料 */
    public static function apply(int $userId, array $data): void
    {
        $realName = trim((string) ($data['real_name'] ?? ''));
        $idCard   = strtoupper(trim((string) ($data['id_card'] ?? '')));
        $phone    = trim((string) ($data['phone'] ?? ''));
        $intro    = trim((string) ($data['intro'] ?? ''));

        if ($realName === '' || mb_strlen($realName) < 2) {
            throw new \RuntimeException('请填写真实姓名');
        }
        // 陪玩必须实名：身份证号为必填项，不再允许留空
        if ($idCard === '') {
            throw new \RuntimeException('请填写身份证号（平台要求实名）');
        }
        if (!preg_match('/^\d{17}[\dX]$/', $idCard)) {
            throw new \RuntimeException('身份证号格式不正确（应为 18 位）');
        }
        if (!is_phone($phone)) {
            throw new \RuntimeException('请填写正确的手机号');
        }
        if (mb_strlen($intro) > 500) {
            throw new \RuntimeException('个人介绍不能超过 500 字');
        }

        $exists = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);
        $needAudit = Setting::int('companion_need_audit', 1) === 1;

        $payload = [
            'real_name'   => mb_substr($realName, 0, 50),
            'id_card'     => mb_substr(trim((string) ($data['id_card'] ?? '')), 0, 30),
            'phone'       => $phone,
            'gender'      => (int) ($data['gender'] ?? 0),
            'city'        => mb_substr(trim((string) ($data['city'] ?? '')), 0, 50),
            'intro'       => $intro,
            'tags'        => mb_substr(trim((string) ($data['tags'] ?? '')), 0, 200),
            'updated_at'  => now(),
        ];

        if ($exists) {
            if ((int) $exists['audit_status'] === 1) {
                // 已通过审核的资料，修改后不改变审核状态
                Db::update('companion_profiles', $payload, 'user_id = ?', [$userId]);
                return;
            }
            // 平台关闭了入驻审核时，提交即视为通过，避免"有陪玩角色但搜不到、接不到单"
            $payload['audit_status'] = $needAudit ? 0 : 1;
            $payload['audit_remark'] = '';
            if (!$needAudit) {
                $payload['audit_at'] = now();
            }
            Db::update('companion_profiles', $payload, 'user_id = ?', [$userId]);
        } else {
            $payload['user_id']      = $userId;
            $payload['audit_status'] = $needAudit ? 0 : 1;
            $payload['audit_at']     = $needAudit ? null : now();
            $payload['online_status'] = 1;
            $payload['created_at']   = now();
            Db::insert('companion_profiles', $payload);
        }

        if (!$needAudit) {
            self::markRole($userId);
        }
    }

    /** 后台审核 */
    public static function audit(int $userId, int $status, string $remark = ''): void
    {
        if (!in_array($status, [0, 1, 2], true)) {
            throw new \RuntimeException('审核状态不合法');
        }
        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);
        if (!$profile) {
            throw new \RuntimeException('该用户没有提交入驻申请');
        }
        Db::update('companion_profiles', [
            'audit_status' => $status,
            'audit_remark' => mb_substr($remark, 0, 200),
            'audit_at'     => now(),
            'updated_at'   => now(),
        ], 'user_id = ?', [$userId]);

        if ($status === 1) {
            self::markRole($userId);
        }
    }

    /** 把用户角色标记为陪玩 */
    /** 陪玩自己设置在线 / 离线（在线的排在前台列表最前面） */
    public static function setOnline(int $userId, int $online): void
    {
        $profile = Db::one('SELECT id, audit_status FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);
        if (!$profile) {
            throw new \RuntimeException('你还不是陪玩，先提交入驻申请或联系管理员开通');
        }
        if ((int) $profile['audit_status'] !== 1) {
            throw new \RuntimeException('入驻审核通过后才能设置接单状态');
        }

        Db::update('companion_profiles', [
            'online_status' => $online === 1 ? 1 : 0,
            'updated_at'    => now(),
        ], 'user_id = ?', [$userId]);
    }

    public static function markRole(int $userId): void
    {
        $user = Db::one('SELECT role FROM users WHERE id = ? LIMIT 1', [$userId]);
        if ($user && $user['role'] === 'user') {
            Db::update('users', ['role' => 'companion', 'updated_at' => now()], 'id = ?', [$userId]);
        }
    }

    /** 重置为普通用户（封禁陪玩接单能力时使用） */
    public static function demoteRole(int $userId): void
    {
        Db::update('users', ['role' => 'user', 'updated_at' => now()], "id = ? AND role = 'companion'", [$userId]);
    }

    // ---------------------------------------------------------------- 技能

    public static function skills(int $companionId, bool $onlyActive = false): array
    {
        $sql = 'SELECT s.*, g.`name` AS game_name FROM companion_skills s
                LEFT JOIN games g ON g.id = s.game_id
                WHERE s.companion_id = ?';
        if ($onlyActive) {
            $sql .= ' AND s.status = 1';
        }
        $sql .= ' ORDER BY s.sort DESC, s.id DESC';
        return Db::all($sql, [$companionId]);
    }

    public static function saveSkill(int $companionId, array $data): int
    {
        $skillId = (int) ($data['id'] ?? 0);
        $gameId  = (int) ($data['game_id'] ?? 0);
        $title   = trim((string) ($data['title'] ?? ''));
        $type    = (string) ($data['service_type'] ?? 'hour');
        $price   = safe_float($data['price'] ?? 0);
        $rank    = trim((string) ($data['rank_level'] ?? ''));
        $desc    = trim((string) ($data['description'] ?? ''));

        if (!array_key_exists($type, service_type_map())) {
            throw new \RuntimeException('服务类型不正确');
        }
        if ($gameId <= 0 || !Db::one('SELECT id FROM games WHERE id = ? AND status = 1', [$gameId])) {
            throw new \RuntimeException('请选择有效的游戏');
        }
        if ($title === '') {
            throw new \RuntimeException('请填写服务标题，例如：王者荣耀 上分陪练');
        }
        if ($price <= 0) {
            throw new \RuntimeException('价格必须大于 0');
        }
        if ($price > 99999) {
            throw new \RuntimeException('价格设置过高');
        }

        $payload = [
            'game_id'      => $gameId,
            'title'        => mb_substr($title, 0, 100),
            'service_type' => $type,
            'rank_level'   => mb_substr($rank, 0, 50),
            'price'        => $price,
            'description'  => mb_substr($desc, 0, 500),
            'updated_at'   => now(),
        ];

        // 配图：传了才更新，留空表示保持原图
        $cover = trim((string) ($data['cover'] ?? ''));
        if ($cover !== '') {
            $payload['cover'] = mb_substr($cover, 0, 250);
        }

        if ($skillId > 0) {
            $own = Db::one('SELECT id FROM companion_skills WHERE id = ? AND companion_id = ? LIMIT 1', [$skillId, $companionId]);
            if (!$own) {
                throw new \RuntimeException('技能不存在或不属于你');
            }
            Db::update('companion_skills', $payload, 'id = ? AND companion_id = ?', [$skillId, $companionId]);
            return $skillId;
        }

        $payload['companion_id'] = $companionId;
        $payload['status']       = 1;
        $payload['created_at']   = now();
        return Db::insert('companion_skills', $payload);
    }

    public static function deleteSkill(int $companionId, int $skillId): void
    {
        $own = Db::one('SELECT id FROM companion_skills WHERE id = ? AND companion_id = ? LIMIT 1', [$skillId, $companionId]);
        if (!$own) {
            throw new \RuntimeException('技能不存在或不属于你');
        }
        Db::query('DELETE FROM companion_skills WHERE id = ? AND companion_id = ?', [$skillId, $companionId]);
    }

    public static function toggleSkill(int $companionId, int $skillId): int
    {
        $skill = Db::one('SELECT id, status FROM companion_skills WHERE id = ? AND companion_id = ? LIMIT 1', [$skillId, $companionId]);
        if (!$skill) {
            throw new \RuntimeException('技能不存在或不属于你');
        }
        $new = (int) $skill['status'] === 1 ? 0 : 1;
        Db::update('companion_skills', ['status' => $new, 'updated_at' => now()], 'id = ? AND companion_id = ?', [$skillId, $companionId]);
        return $new;
    }

    // ---------------------------------------------------------------- 评分

    public static function recalcScore(int $companionId): void
    {
        $row = Db::one(
            'SELECT COUNT(*) AS cnt, AVG(score) AS avg_score,
                    SUM(CASE WHEN score >= 4 THEN 1 ELSE 0 END) AS good
             FROM order_reviews WHERE companion_id = ? AND status = 1',
            [$companionId]
        );
        $cnt  = (int) ($row['cnt'] ?? 0);
        $avg  = $cnt > 0 ? round((float) $row['avg_score'], 2) : 5.00;
        $good = $cnt > 0 ? round(((int) $row['good']) * 100 / $cnt, 2) : 100.00;

        Db::update('companion_profiles', [
            'score'        => min(5, max(0, $avg)),
            'review_count' => $cnt,
            'good_rate'    => min(100, max(0, $good)),
            'updated_at'   => now(),
        ], 'user_id = ?', [$companionId]);
    }

    /** 陪玩收益/订单统计 */
    public static function incomeStat(int $companionId): array
    {
        $row = Db::one(
            'SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS serving_orders,
                COALESCE(SUM(CASE WHEN status = ? THEN companion_income ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN status = ? THEN companion_income ELSE 0 END), 0) AS today_income
             FROM orders
             WHERE companion_id = ?',
            [
                OrderService::STATUS_COMPLETED,
                OrderService::STATUS_IN_SERVICE,
                OrderService::STATUS_PENDING_CONFIRM,
                OrderService::STATUS_COMPLETED,
                OrderService::STATUS_COMPLETED,
                $companionId,
            ]
        );

        $today = Db::one(
            'SELECT COALESCE(SUM(companion_income), 0) AS amount, COUNT(*) AS cnt
             FROM orders WHERE companion_id = ? AND status = ? AND confirm_time >= ?',
            [$companionId, OrderService::STATUS_COMPLETED, date('Y-m-d 00:00:00')]
        );

        $row = $row ?: [];
        $row['today_income'] = $today['amount'] ?? 0;
        $row['today_orders'] = $today['cnt'] ?? 0;
        return $row;
    }
}
