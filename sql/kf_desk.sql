-- ============================================================
-- 工单部门 · 数据库补丁（已装好的站点手动执行一次）
--
-- 说明：
--   1. 本补丁只新增表 / 新增字段，**不改动、不删除任何已有数据**。
--   2. MySQL 不支持 ADD COLUMN IF NOT EXISTS，重复执行会报 1060
--      （Duplicate column name），看到就是已经加过了，忽略即可。
-- ============================================================

-- 1. 工单部门账号（独立于 users 表，只用于处理工单）
CREATE TABLE IF NOT EXISTS `kf_staff` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_no` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '工号，自动生成，如 KF0001',
  `account` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '登录账号',
  `password` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'password_hash',
  `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '姓名/昵称',
  `email` VARCHAR(100) NOT NULL DEFAULT '',
  `phone` VARCHAR(20) NOT NULL DEFAULT '',
  `role` VARCHAR(20) NOT NULL DEFAULT 'agent' COMMENT 'manager经理 / leader组长 / agent客服',
  `leader_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客服所属组长（kf_staff.id），0=未分组',
  `manager_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '组长所属经理（kf_staff.id），0=未分组',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1正常 0停用',
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_login_at` DATETIME DEFAULT NULL,
  `last_login_ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_account` (`account`),
  UNIQUE KEY `uk_no` (`staff_no`),
  KEY `idx_role` (`role`, `status`),
  KEY `idx_leader` (`leader_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单部门账号';

-- 2. 工单增加「处理客服」（0 = 还在公海，等着分配）
ALTER TABLE `tickets`
  ADD COLUMN `staff_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '处理客服 kf_staff.id，0=公海',
  ADD KEY `idx_staff` (`staff_id`, `status`);

-- 3. 对话增加「发送人显示名」，让用户看到「客服：小美」而不是笼统的「客服」
ALTER TABLE `ticket_replies`
  ADD COLUMN `sender_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '回复人显示名（工单部门用）';

-- 4. 组长挂在经理下面（层级：经理 > 组长 > 客服）
--    新装站点不需要执行这条（建表语句里已经带了）；
--    已经建过 kf_staff 的老库执行这一条即可。
ALTER TABLE `kf_staff`
  ADD COLUMN `manager_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '组长所属经理 kf_staff.id';

-- 5. 已读记录：每个客服对每张单读到哪一条回复
--    打开工单即标记已读；用户再次回复后自动变回未读。
CREATE TABLE IF NOT EXISTS `kf_ticket_reads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `ticket_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_reply_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_staff_ticket` (`staff_id`, `ticket_id`),
  KEY `idx_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单已读记录';

-- 6. 工单附件（图片 / 视频 / 文档，存 JSON 数组）
ALTER TABLE `tickets`
  ADD COLUMN `attachments` TEXT NULL COMMENT '附件（JSON 数组，图片/视频/文档）';
ALTER TABLE `ticket_replies`
  ADD COLUMN `attachments` TEXT NULL COMMENT '附件（JSON 数组）';
