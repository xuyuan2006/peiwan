-- ============================================================
-- 陪玩点单平台 · App 接口增量升级脚本
--
-- 适用：已经安装过、库里已有数据的站点（不要重跑 install.php，那会重置管理员密码）。
-- 全部语句幂等（CREATE TABLE IF NOT EXISTS / INSERT IGNORE），可重复执行。
--
-- 用法：宝塔面板 → 数据库 → 管理(phpMyAdmin) → 选中你的库 → SQL 窗口 → 粘贴执行。
--       执行完再访问一次 /check.php，结构比对应该全绿。
-- ============================================================

-- ------------------------------------------------------------
-- 1. App 登录令牌（Bearer Token）
--    只存 SHA-256 摘要，即使库被拖走也无法反推出可用 token。
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- 2. 接口限流计数（防登录爆破、防验证码轰炸）
--    固定窗口计数：同一 key 在 window 秒内超过上限即拒绝。
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_throttle` (
  `k` VARCHAR(160) NOT NULL COMMENT '限流键，如 login:1.2.3.4',
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_start` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '窗口起始 unix 时间戳',
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='App 接口限流';

-- ------------------------------------------------------------
-- 3. 设置表放宽：settings.v 从 VARCHAR(500) 改成 TEXT
--
--    新增的「陪玩考核标准」需要在后台填写多行详细内容，500 字符不够用。
--    TEXT 是 VARCHAR 的超集，对已有设置项没有任何影响；重复执行也安全。
--
--    配套改动：Admin\SettingController::save() 里 textarea 类型的截断上限
--    已从 490 放宽到 20000（text 类型仍是 490，仍受各自的业务约束）。
--
--    注意：本次不往 settings 表插数据。新增的 3 个键
--    （companion_assess / companion_contact_qr / companion_contact_text）
--    在 app/Setting.php 的 $defaults 里有默认值，后台打开设置页即可看到并修改，
--    保存后才会写进表里。
-- ------------------------------------------------------------
ALTER TABLE `settings` MODIFY COLUMN `v` TEXT NOT NULL;

-- ------------------------------------------------------------
-- 4. 用户展示号 users.no：随机 6 位，个人中心里展示给用户的那个「ID」
--
--    ⚠️ 本节只能执行一次 —— MySQL 不支持 ADD COLUMN IF NOT EXISTS。
--       若报 "Duplicate column name 'no'"，说明已经加过了，跳过本节即可。
--       建议单独复制到 SQL 窗口执行，免得中途报错影响其它语句。
--
--    为什么不直接用自增主键 id：它会暴露「平台一共有多少用户」，
--    也能看出谁先注册。随机 6 位（10 万~99 万）有 90 万种取值，够用。
--
--    老数据回填用 id 做唯一映射：76271 与 900000 互质，
--    因此 id 不同结果必然不同（不会撞号），而看起来是乱的。
-- ------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN `no` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对外展示的用户号（随机6位）';

UPDATE `users` SET `no` = 100000 + ((`id` * 76271) % 900000) WHERE `no` = 0;

ALTER TABLE `users` ADD UNIQUE KEY `uk_no` (`no`);
