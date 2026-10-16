-- ============================================================
-- 陪玩点单系统 - 数据库结构（MySQL 5.7+ / 8.0）
-- 由 install.php 自动执行，也可手动导入
-- ============================================================

-- 1. 用户表（前台用户 / 陪玩 / 管理员共用）
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL DEFAULT '',
  `password` VARCHAR(255) NOT NULL DEFAULT '',
  `nickname` VARCHAR(50) NOT NULL DEFAULT '',
  `phone` VARCHAR(20) NOT NULL DEFAULT '',
  `email` VARCHAR(100) NOT NULL DEFAULT '',
  `avatar` VARCHAR(255) NOT NULL DEFAULT '',
  `gender` TINYINT NOT NULL DEFAULT 0 COMMENT '0保密 1男 2女',
  `role` VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'user普通用户 companion陪玩 admin管理员',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `credit_score` INT NOT NULL DEFAULT 100 COMMENT '信用分',
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `last_login_at` DATETIME DEFAULT NULL,
  `last_login_ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 2. 陪玩资料表
CREATE TABLE IF NOT EXISTS `companion_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `real_name` VARCHAR(50) NOT NULL DEFAULT '',
  `id_card` VARCHAR(30) NOT NULL DEFAULT '',
  `phone` VARCHAR(20) NOT NULL DEFAULT '',
  `gender` TINYINT NOT NULL DEFAULT 0 COMMENT '0保密 1男 2女',
  `city` VARCHAR(50) NOT NULL DEFAULT '',
  `intro` VARCHAR(600) NOT NULL DEFAULT '',
  `cover` VARCHAR(255) NOT NULL DEFAULT '',
  `voice_url` VARCHAR(255) NOT NULL DEFAULT '',
  `tags` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '逗号分隔，如 技术好,声音甜',
  `score` DECIMAL(3,2) NOT NULL DEFAULT 5.00 COMMENT '平均评分',
  `order_count` INT NOT NULL DEFAULT 0 COMMENT '完成订单数',
  `review_count` INT NOT NULL DEFAULT 0 COMMENT '评价数',
  `good_rate` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT '好评率',
  `total_income` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计收入',
  `online_status` TINYINT NOT NULL DEFAULT 1 COMMENT '1接单中 0休息中',
  `audit_status` TINYINT NOT NULL DEFAULT 0 COMMENT '0待审核 1通过 2拒绝',
  `audit_remark` VARCHAR(255) NOT NULL DEFAULT '',
  `audit_at` DATETIME DEFAULT NULL,
  `is_recommend` TINYINT NOT NULL DEFAULT 0,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`user_id`),
  KEY `idx_audit` (`audit_status`),
  KEY `idx_recommend` (`is_recommend`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='陪玩资料表';

-- 3. 游戏表
CREATE TABLE IF NOT EXISTS `games` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL DEFAULT '',
  `icon` VARCHAR(255) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 0,
  `is_hot` TINYINT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏分类表';

-- 4. 陪玩技能（服务项目）表
CREATE TABLE IF NOT EXISTS `companion_skills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `companion_id` INT UNSIGNED NOT NULL COMMENT '陪玩用户ID',
  `game_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(100) NOT NULL DEFAULT '',
  `service_type` VARCHAR(20) NOT NULL DEFAULT 'hour' COMMENT 'hour按小时 round按局 day按天 custom一口价',
  `rank_level` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '段位/水平',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cover` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '项目配图',
  `description` VARCHAR(600) NOT NULL DEFAULT '',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_companion` (`companion_id`, `status`),
  KEY `idx_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='陪玩技能表';

-- 5. 订单表
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(40) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL COMMENT '下单用户',
  `companion_id` INT UNSIGNED NOT NULL COMMENT '接单陪玩',
  `game_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `skill_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `game_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '下单快照',
  `skill_title` VARCHAR(100) NOT NULL DEFAULT '',
  `service_type` VARCHAR(20) NOT NULL DEFAULT 'hour',
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单总额',
  `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '平台抽成',
  `companion_income` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '陪玩实收',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1待支付 2待接单 3服务中 4待确认 5已完成 6退款中 7已关闭 8已退款',
  `user_remark` VARCHAR(255) NOT NULL DEFAULT '',
  `cancel_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `refund_reason` VARCHAR(255) NOT NULL DEFAULT '',
  `refund_remark` VARCHAR(255) NOT NULL DEFAULT '',
  `refund_operator` VARCHAR(20) NOT NULL DEFAULT '',
  `refund_from_status` TINYINT NOT NULL DEFAULT 0 COMMENT '申请退款前的状态，驳回时原样退回',
  `cover` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单/任务配图',
  `is_public` TINYINT NOT NULL DEFAULT 0 COMMENT '1=进入接单大厅，等陪玩抢单',
  `taken_at` DATETIME DEFAULT NULL COMMENT '陪玩接单时间',
  `is_reviewed` TINYINT NOT NULL DEFAULT 0,
  `expire_at` DATETIME DEFAULT NULL COMMENT '支付超时时间',
  `pay_time` DATETIME DEFAULT NULL,
  `accept_time` DATETIME DEFAULT NULL,
  `finish_time` DATETIME DEFAULT NULL,
  `confirm_time` DATETIME DEFAULT NULL,
  `close_time` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`, `status`),
  KEY `idx_companion` (`companion_id`, `status`),
  KEY `idx_skill` (`skill_id`),
  KEY `idx_public` (`is_public`, `status`, `companion_id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_expire` (`status`, `expire_at`),
  KEY `idx_finish` (`status`, `finish_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

-- 6. 订单状态日志
CREATE TABLE IF NOT EXISTS `order_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `from_status` TINYINT NOT NULL DEFAULT 0,
  `to_status` TINYINT NOT NULL DEFAULT 0,
  `operator_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `operator_type` VARCHAR(20) NOT NULL DEFAULT 'system' COMMENT 'user companion admin system',
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单状态日志';

-- 7. 订单评价
CREATE TABLE IF NOT EXISTS `order_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `companion_id` INT UNSIGNED NOT NULL,
  `score` TINYINT NOT NULL DEFAULT 5 COMMENT '1-5 星',
  `content` VARCHAR(600) NOT NULL DEFAULT '',
  `tags` VARCHAR(255) NOT NULL DEFAULT '',
  `is_anonymous` TINYINT NOT NULL DEFAULT 0,
  `reply` VARCHAR(600) NOT NULL DEFAULT '' COMMENT '陪玩回复',
  `reply_at` DATETIME DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1显示 0隐藏',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order` (`order_id`),
  KEY `idx_companion` (`companion_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单评价表';

-- 8. 用户钱包
CREATE TABLE IF NOT EXISTS `user_wallets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '可用余额',
  `frozen` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '冻结金额',
  `total_recharge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_income` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_withdraw` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_expense` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户钱包';

-- 9. 资金流水
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '见 wallet_type_map',
  `direction` TINYINT NOT NULL DEFAULT 1 COMMENT '1收入 2支出',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `frozen_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `frozen_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `biz_type` VARCHAR(30) NOT NULL DEFAULT '',
  `biz_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`, `id`),
  KEY `idx_biz` (`biz_type`, `biz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资金流水';

-- 10. 充值单
CREATE TABLE IF NOT EXISTS `recharge_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(40) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `channel` VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'manual人工 epay易支付',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '0待处理 1已到账 2已关闭 3已驳回',
  `pay_voucher` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '转账凭证图',
  `pay_url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '在线支付链接/二维码内容',
  `trade_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '第三方流水号',
  `audit_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `audit_at` DATETIME DEFAULT NULL,
  `audit_remark` VARCHAR(255) NOT NULL DEFAULT '',
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值单';

-- 11. 提现单
CREATE TABLE IF NOT EXISTS `withdraw_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(40) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '申请金额',
  `fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '手续费',
  `real_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '实际到账',
  `account_type` VARCHAR(20) NOT NULL DEFAULT 'alipay' COMMENT 'alipay wechat bank',
  `account_name` VARCHAR(50) NOT NULL DEFAULT '',
  `account_no` VARCHAR(100) NOT NULL DEFAULT '',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '0待审核 1已打款 2已驳回',
  `audit_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `audit_at` DATETIME DEFAULT NULL,
  `audit_remark` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现单';

-- 12. 平台设置
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(50) NOT NULL,
  `v` VARCHAR(500) NOT NULL DEFAULT '',
  `title` VARCHAR(100) NOT NULL DEFAULT '',
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台设置';

-- 13. 后台操作日志
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(100) NOT NULL DEFAULT '',
  `detail` VARCHAR(1000) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台操作日志';

-- 工单表（下方与文件末尾各有一份相同定义，重复定义不影响安装：CREATE TABLE IF NOT EXISTS 是空操作）
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_no` VARCHAR(40) NOT NULL DEFAULT '',
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `category` VARCHAR(20) NOT NULL DEFAULT 'presale' COMMENT 'presale售前 aftersale售后 complaint投诉',
  `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(100) NOT NULL DEFAULT '',
  `content` VARCHAR(1000) NOT NULL DEFAULT '',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '0待处理 1处理中 2已解决 3已关闭',
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `replied_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_no` (`ticket_no`),
  KEY `idx_user` (`user_id`, `status`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单';

-- 17. 工单回复（用户与客服的对话）
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `sender_type` VARCHAR(10) NOT NULL DEFAULT 'user' COMMENT 'user用户 admin客服',
  `sender_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `content` VARCHAR(1000) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复';
-- 14. 验证码（邮箱 / 短信通用）
CREATE TABLE IF NOT EXISTS `verify_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `target` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '邮箱或手机号',
  `channel` VARCHAR(10) NOT NULL DEFAULT 'email' COMMENT 'email邮箱 sms短信',
  `scene` VARCHAR(20) NOT NULL DEFAULT 'register' COMMENT 'register注册 reset找回密码',
  `code` VARCHAR(10) NOT NULL DEFAULT '',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '0未使用 1已使用',
  `attempts` INT NOT NULL DEFAULT 0 COMMENT '已尝试次数',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `expire_at` DATETIME DEFAULT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_target_scene` (`target`, `scene`, `status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='验证码';

-- 17. 公告（前台顶部走马灯滚动）
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `content` VARCHAR(200) NOT NULL DEFAULT '',
  `link_url` VARCHAR(255) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '越大越靠前',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告';

-- 18. 广告位（首页轮播图）
CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL DEFAULT '',
  `image` VARCHAR(255) NOT NULL DEFAULT '',
  `link_url` VARCHAR(255) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '越大越靠前',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='广告位';

-- 19. 点单套餐（后台预置的一口价单子，普通用户直接下单，订单进入接单大厅由陪玩抢）
CREATE TABLE IF NOT EXISTS `order_packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '例如：王者荣耀 陪玩 5 小时',
  `game_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '一口价（总价）',
  `cover` VARCHAR(255) NOT NULL DEFAULT '',
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '越大越靠前',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='点单套餐';

-- 示例套餐（可删可改）：让后台一眼看到效果
INSERT IGNORE INTO `order_packages` (`id`, `title`, `game_id`, `price`, `cover`, `description`, `sort`, `status`, `created_at`, `updated_at`) VALUES
 (1, '王者荣耀 陪玩 5 小时（一口价）', 0, 150.00, '', '含 5 小时陪玩时间，可连麦、可指定位置；超时按 30 元/小时结算。下单后由陪玩接单。', 10, 1, NOW(), NOW()),
 (2, '三角洲行动 保 100 万哈夫币', 0, 80.00, '', '按约定带打，保底 100 万哈夫币；未达成可申请退款。下单后由陪玩接单。', 9, 1, NOW(), NOW());

-- 20. 订单聊天消息（接单后下单方与陪玩在订单内沟通）
CREATE TABLE IF NOT EXISTS `order_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `sender_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `sender_role` VARCHAR(10) NOT NULL DEFAULT 'user' COMMENT 'user下单方 companion陪玩 admin客服',
  `content` VARCHAR(500) NOT NULL DEFAULT '',
  `is_read` TINYINT NOT NULL DEFAULT 0 COMMENT '接收方是否已读',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`, `id`),
  KEY `idx_unread` (`is_read`, `order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单聊天消息';

-- 15. 工单（售前 / 售后 / 投诉）
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_no` VARCHAR(40) NOT NULL DEFAULT '',
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `category` VARCHAR(20) NOT NULL DEFAULT 'presale' COMMENT 'presale售前 aftersale售后 complaint投诉',
  `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(100) NOT NULL DEFAULT '',
  `content` VARCHAR(1000) NOT NULL DEFAULT '',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '0待处理 1处理中 2已解决 3已关闭',
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `replied_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_no` (`ticket_no`),
  KEY `idx_user` (`user_id`, `status`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单';

-- 16. 工单回复（用户与客服的对话）
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `sender_type` VARCHAR(10) NOT NULL DEFAULT 'user' COMMENT 'user用户 admin客服',
  `sender_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `content` VARCHAR(1000) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复';

-- ============================================================
-- 初始化数据
-- ============================================================
INSERT IGNORE INTO `settings` (`k`, `v`, `title`, `remark`, `updated_at`) VALUES
 ('site_name', '陪玩点单平台', '站点名称', '', NOW()),
 ('site_notice', '欢迎来到陪玩点单平台，下单后请耐心等待陪玩接单。', '首页公告', '', NOW()),
 ('platform_fee_rate', '20', '平台抽成比例(%)', '订单完成后从订单金额中抽取的比例', NOW()),
 ('min_withdraw', '50', '最低提现金额(元)', '', NOW()),
 ('withdraw_fee_rate', '1', '提现手续费(%)', '', NOW()),
 ('order_auto_cancel_minutes', '30', '未支付自动关闭(分钟)', '', NOW()),
 ('order_auto_confirm_hours', '24', '服务完成自动确认(小时)', '', NOW()),
 ('companion_need_audit', '1', '陪玩入驻是否需要审核', '1需要 0不需要', NOW()),
 ('service_phone', '', '客服电话', '', NOW()),
 ('mail_enabled', '0', '开启邮箱验证码', '已由「注册/找回密码验证方式」取代，保留兼容', NOW()),
 ('verify_register_channel', 'email', '注册验证方式', 'off关闭 email邮箱验证码 sms手机短信验证码', NOW()),
 ('verify_code_ttl_minutes', '3', '验证码有效期(分钟)', '建议 3~10 分钟；邮件文案里的时长会自动跟这里保持一致', NOW()),
 ('notice_interval', '4', '公告走马灯间隔(秒)', '前台公告栏每隔几秒翻下一条，2~60', NOW()),
 ('banner_interval', '5', '广告轮播间隔(秒)', '首页广告图每隔几秒切换，2~60', NOW()),
 ('companion_intro', '上架自己的服务项目（带配图与价格），用户可以直接下单买你的时间。\n在「接单大厅」抢平台发布的订单，接单后按约定时间提供服务。\n服务完成后钱进钱包，可随时申请提现。', '成为陪玩的说明', '显示在「我的」页面底部；每行一条，留空则整块不显示', NOW()),
 ('verify_reset_channel', 'email', '找回密码验证方式', 'off关闭 email邮箱验证码 sms手机短信验证码', NOW()),
 ('sms_driver', 'none', '短信服务商', 'none未配置 aliyun阿里云短信', NOW()),
 ('sms_access_key_id', '', '短信 AccessKeyId', '', NOW()),
 ('sms_access_key_secret', '', '短信 AccessKeySecret', '', NOW()),
 ('sms_sign_name', '', '短信签名', '例如：陪玩平台', NOW()),
 ('sms_template_code', '', '短信模板 CODE', '例如：SMS_123456789，模板内容需含 ${code}', NOW()),
 ('sms_region', 'cn-hangzhou', '短信接入区域', '一般填 cn-hangzhou', NOW()),
 ('pay_channel', 'manual', '支付渠道', 'manual人工转账 epay易支付 alipay支付宝当面付/网站支付', NOW()),
 ('epay_api_url', '', '易支付网关地址', '例如 https://pay.example.com', NOW()),
 ('epay_pid', '', '易支付商户ID', '', NOW()),
 ('epay_key', '', '易支付商户密钥', '', NOW()),
 ('alipay_app_id', '', '支付宝 APPID', '', NOW()),
 ('alipay_private_key', '', '支付宝应用私钥', 'PKCS8 一行 base64 或完整 PEM', NOW()),
 ('alipay_public_key', '', '支付宝公钥', '用于回调验签', NOW()),
 ('alipay_gateway', 'https://openapi.alipay.com/gateway.do', '支付宝网关', '沙箱环境填 https://openapi.alipaydev.com/gateway.do', NOW()),
 ('alipay_mode', 'page', '支付宝收款方式', 'page电脑网站支付 wap手机网站支付 precreate当面付预下单', NOW()),
 ('mail_verify_register', '1', '注册需要邮箱验证', '仅在开启邮箱验证码后生效', NOW()),
 ('mail_verify_reset', '1', '找回密码需要邮箱验证', '仅在开启邮箱验证码后生效', NOW()),
 ('mail_smtp_host', '', 'SMTP 服务器', '例如 smtp.qq.com', NOW()),
 ('mail_smtp_port', '465', 'SMTP 端口', 'SSL 一般 465，STARTTLS 一般 587', NOW()),
 ('mail_smtp_secure', 'ssl', 'SMTP 加密方式', 'ssl / tls / none', NOW()),
 ('mail_smtp_user', '', 'SMTP 账号', '', NOW()),
 ('mail_smtp_pass', '', 'SMTP 密码/授权码', '', NOW()),
 ('mail_from', '', '发件人邮箱', '留空则用 SMTP 账号', NOW()),
 ('mail_from_name', '', '发件人名称', '留空则用站点名称', NOW());

INSERT INTO `games` (`name`, `icon`, `sort`, `is_hot`, `status`, `created_at`, `updated_at`) VALUES
 ('王者荣耀', '', 100, 1, 1, NOW(), NOW()),
 ('和平精英', '', 95, 1, 1, NOW(), NOW()),
 ('英雄联盟', '', 90, 1, 1, NOW(), NOW()),
 ('无畏契约', '', 80, 0, 1, NOW(), NOW()),
 ('永劫无间', '', 70, 0, 1, NOW(), NOW()),
 ('云顶之弈', '', 60, 0, 1, NOW(), NOW()),
 ('三角洲行动', '', 50, 0, 1, NOW(), NOW()),
 ('CS2', '', 40, 0, 1, NOW(), NOW());

-- ============================================================
-- App（安卓客户端）接口表
-- 已经装过、库里有数据的老站点，请单独执行 sql/api_upgrade.sql 增量创建，
-- 不要重跑 install.php（那会重置管理员密码）。
-- ============================================================

-- 21. App 登录令牌（Bearer Token）：只存 SHA-256 摘要
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `token_hash` CHAR(64) NOT NULL DEFAULT '' COMMENT 'SHA-256(token)',
  `platform` VARCHAR(20) NOT NULL DEFAULT 'android' COMMENT 'android ios',
  `device` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '机型/设备名',
  `app_version` VARCHAR(20) NOT NULL DEFAULT '',
  `last_ip` VARCHAR(45) NOT NULL DEFAULT '',
  `last_used_at` DATETIME DEFAULT NULL,
  `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间，NULL=永不过期',
  `revoked_at` DATETIME DEFAULT NULL COMMENT '登出/踢下线时间',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`, `revoked_at`),
  KEY `idx_expire` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App 登录令牌';

-- 22. 接口限流计数（防登录爆破、防验证码轰炸）
CREATE TABLE IF NOT EXISTS `api_throttle` (
  `k` VARCHAR(160) NOT NULL COMMENT '限流键，如 login:1.2.3.4',
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_start` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '窗口起始 unix 时间戳',
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App 接口限流';
