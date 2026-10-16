# App（安卓）接口文档 · v1

> 本文件描述为安卓客户端新增的 JSON 接口。**网页端原有功能与页面完全不受影响**，
> 所有新增文件都在新目录下，没有改动任何既有 PHP 文件。

---

## 一、部署（三步）

### 1. 上传新增文件

把下面这些**新增**文件上传到服务器，目录结构与本地一致：

```
public/api/index.php                    ← 接口入口（新增目录）
app/api_bootstrap.php                   ← 接口引导（不启动 Session）
app/Api/ApiException.php
app/Api/Response.php
app/Api/Request.php
app/Api/TokenAuth.php
app/Api/RateLimit.php
app/Api/Presenter.php
app/Api/OrderQuery.php
app/Api/Router.php
app/Controllers/Api/BaseController.php
app/Controllers/Api/CommonController.php
app/Controllers/Api/AuthController.php
app/Controllers/Api/HomeController.php
app/Controllers/Api/OrderController.php
app/Controllers/Api/WalletController.php
app/Controllers/Api/CompanionController.php
app/Controllers/Api/UserController.php
app/Controllers/Api/TicketController.php
app/Controllers/Api/ReviewController.php
app/Controllers/Api/UploadController.php
sql/api_upgrade.sql
```

> ⚠️ **不要重跑 `install.php`** —— 升级模式也会重置管理员密码。
> `sql/schema.sql` 虽然已同步加入新表（供以后全新安装使用），但那不是给现有站点用的。

### 2. 建表

宝塔面板 → 数据库 → 管理(phpMyAdmin) → 选中你的库 → SQL 窗口 → 粘贴执行

`sql/api_upgrade.sql` 的全部内容（两条 `CREATE TABLE IF NOT EXISTS`，幂等，可重复执行）。

### 3. 验证

**第一步：让服务器自己检查语法（这一步能替代 `php -l`）**

浏览器访问：

```
http://103.47.80.215/check.php
```

`check.php` 内部用 `token_get_all($code, TOKEN_PARSE)` 对 `app/`、`config/`、`cron/`、`public/`
下**所有** PHP 文件做语法解析，效果等价于逐个跑 `php -l`。看到「全部文件语法正确」就说明
本次新增的 21 个 PHP 文件没有语法问题。同时它还会核对 SQL 占位符数量与表结构差异。

> 用管理员身份登录 `check.php` 还能看到 文件:行号 + 报错明细。

**第二步：确认接口表结构已就绪**

```
http://103.47.80.215/api/index.php?r=common/init
```

期望返回：

```json
{"code":0,"msg":"ok","data":{"api_version":"v1","api_ready":true, ...}}
```

`api_ready` 为 `true` 表示 `api_tokens` 表已就绪；为 `false` 说明第 2 步没执行成功
（此时受保护接口会返回 503，并提示需要执行 `sql/api_upgrade.sql`）。

**第三步：试登录**

```
POST http://103.47.80.215/api/index.php?r=auth/login
Content-Type: application/json

{"account":"你的账号","password":"你的密码"}
```

成功会返回 `data.token`，后续所有需要登录的接口都带上：

```
Authorization: Bearer <token>
```

---

## 二之前：两个部署注意点

**1. 建议在 `config/config.php` 里写死 `app.url`**

没有配置时，接口会用 `SERVER_NAME` 反推站点地址（拿不到才退回 `HTTP_HOST`），
用于拼接头像、轮播图以及支付回调地址。生产环境写死更稳妥：

```php
'app' => [
    'url' => 'http://103.47.80.215',   // 不要带结尾斜杠
    // ...
],
```

**2. 确认 `session.auto_start = 0`**

接口层刻意不调用 `session_start()`（会话文件锁会把移动端的并发请求串行化）。
如果 php.ini 里 `session.auto_start = 1`，会话会被自动开启，这个优化就失效了。
宝塔默认是 0，一般无需改动。

**3. （可选）关掉 nginx 对 404 的拦截**

线上实测：nginx 配了 `error_page 404`，会把接口返回的 **404 响应体替换成站点的 HTML 404 页**，
导致 App 读不到具体原因（只能退化成「内容不存在或已下架」）。
401 / 405 / 422 不受影响，实测都能正常返回 JSON。

想彻底解决，在站点的 nginx 配置里加一行（宝塔：站点 → 配置文件）：

```nginx
fastcgi_intercept_errors off;   # 让 PHP 的响应体原样透传
```

不加也不影响功能：App 侧对 404 有兜底文案，只是提示不够精确。
接口层也已经把「陪玩未过审 / 账号被禁用」这类**有具体原因**的场景改用了 403，所以这些提示不会丢。

---

## 二、通用约定

### 请求

| 项 | 说明 |
| --- | --- |
| Base URL | `http://103.47.80.215/api/index.php` |
| 路由 | 一律用查询参数 `?r=控制器/方法`，**不需要 URL 重写**，虚拟主机也能跑 |
| 请求体 | `Content-Type: application/json`（推荐）或普通表单 `application/x-www-form-urlencoded`，两者都支持 |
| 上传 | 只有 `upload/image` 用 `multipart/form-data` |
| 鉴权 | `Authorization: Bearer <token>`（也兼容 `X-Token` 头） |
| 编码 | 全部 UTF-8 |

### 响应

```json
{ "code": 0, "msg": "ok", "data": { } }
```

| code | 含义 | App 端建议处理 |
| --- | --- | --- |
| `0` | 成功 | 取 `data` |
| `400` | 业务失败 | 直接 toast `msg` |
| `401` | 未登录 / token 失效 | **清 token 并跳登录页** |
| `403` | 无权限 | toast `msg` |
| `404` | 内容不存在 | toast `msg` |
| `405` | 方法不对 | 开发期问题 |
| `422` | 参数校验失败 | 表单字段级提示 |
| `429` | 触发限流 | toast `msg`（含剩余秒数） |
| `500` | 服务端异常 | toast「服务异常，请稍后再试」 |
| `503` | 站点未安装 / 缺表 | 提示联系管理员 |

> 约定：**只有 401 代表登录态失效**，密码错误用的是 400。
> 这样 App 的拦截器可以放心地用 401 作为「踢回登录页」的唯一信号。

### 分页

统一返回：

```json
{
  "list": [],
  "total": 128,
  "page": 1,
  "per_page": 12,
  "total_page": 11,
  "has_more": true
}
```

请求参数：`page`（从 1 开始）、`per_page`（上限 50，默认值各接口不同）。

### 时间 / 金额

- 时间：`"2026-10-15 18:45:18"` 字符串（服务器时区，默认 `Asia/Shanghai`）
- 金额：JSON 数字（如 `150.0`），另附 `*_text` 字段（如 `"¥150.00"`）可直接展示
- 图片：一律返回**绝对 URL**（如 `http://103.47.80.215/uploads/avatar/202610/xxx.jpg`）；
  头像为空时返回空串 `""`，由 App 自己画首字母占位图（**不会返回 SVG data URI**）

---

## 三、接口清单

### 3.1 公共（无需登录）

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `common/init` | GET | App 冷启动握手：`api_version`、`api_ready`、`force_update` |
| `common/config` | GET | 站点配置：站点名、公告、客服电话、抽成、提现规则、验证方式、可用充值渠道 |
| `home/index` | GET | 首页聚合：轮播 + 公告 + 游戏 + 套餐 + 推荐陪玩 + 统计 |
| `home/games` | GET | 全部启用的游戏分类 |
| `home/packages` | GET | 套餐列表（`?game_id=` 可选筛选） |
| `home/companions` | GET | 陪玩列表，见下方筛选参数 |
| `home/companion` | GET | 陪玩主页（`?id=`）：资料 + 服务项目 + 评价 + 统计 |
| `home/search` | GET | 搜索（`?keyword=`）：同时返回陪玩与套餐 |
| `auth/verifyChannel` | GET | 注册/找回密码用哪种验证方式（决定表单显示邮箱还是手机号） |
| `auth/sendCode` | POST | 发验证码 `{scene: register\|reset, target}` |
| `auth/register` | POST | 注册，见下 |
| `auth/login` | POST | 登录，见下 |
| `auth/forgot` | POST | 找回密码 |

**`home/companions` 筛选参数**

| 参数 | 说明 |
| --- | --- |
| `game_id` | 游戏 ID，0 = 全部 |
| `gender` | 0 不限 / 1 男 / 2 女 |
| `keyword` | 关键词（昵称、简介、标签、城市） |
| `sort` | `default` / `price_asc` / `price_desc` / `hot` / `score` / `new` |
| `online_only` | 1 = 只看接单中 |
| `min_price` / `max_price` | 价格区间 |
| `page` / `per_page` | 分页，默认 12 |

**`auth/register` 参数**

```json
{
  "username": "testuser01",
  "password": "abc123456",
  "password_confirm": "abc123456",
  "nickname": "小明",
  "phone": "13800138000",
  "email": "a@b.com",
  "gender": 1,
  "code": "123456"
}
```

`phone` / `email` / `code` 是否必填，取决于 `auth/verifyChannel` 返回的 `register.channel`：
`off` 不需要验证码，`email` 必须填邮箱 + 验证码，`sms` 必须填手机号 + 验证码。

**`auth/login` 参数**

```json
{ "account": "testuser01", "password": "abc123456", "device": "Xiaomi 14", "platform": "android", "app_version": "1.0.0" }
```

成功返回：

```json
{
  "code": 0,
  "msg": "登录成功",
  "data": {
    "token": "64位十六进制字符串",
    "expire_at": "2026-11-14 18:45:18",
    "me": {
      "user": { "id": 2, "nickname": "小明", "avatar_url": "", "role": "user", "credit_score": 100 },
      "wallet": { "balance": 0.0, "frozen": 0.0, "balance_text": "0.00" },
      "companion": { "applied": false, "audit_status": -1, "audit_text": "未申请" },
      "unread_message": 0
    }
  }
}
```

> 限流：同一 IP 或同一账号 15 分钟内失败 10 次即锁定，返回 429。

### 3.2 登录态

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `auth/me` | GET | 当前用户 + 钱包 + 陪玩身份（结构同登录返回的 `me`） |
| `auth/logout` | POST | 撤销**当前设备**的 token |
| `auth/changePassword` | POST | `{old_password, password, password_confirm}`，改完踢掉其它设备 |

### 3.3 订单

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `order/create` | POST | `{skill_id, quantity, remark}` 下单（服务项目下单） |
| `order/buyPackage` | POST | `{package_id, remark}` 套餐一口价，余额够会自动付款 |
| `order/index` | GET | 我的订单（下单方），`?status=&page=` |
| `order/detail` | GET | `?id=` 订单详情 + 状态时间线 + 聊天可用性 |
| `order/logs` | GET | `?id=` 状态流转时间线 |
| `order/pay` | POST | `{id}` 余额支付 |
| `order/cancel` | POST | `{id, reason}` 取消 |
| `order/refund` | POST | `{id, reason}` 申请退款（`reason` 必填） |
| `order/confirm` | POST | `{id}` 确认完成并结算 |
| `order/messages` | GET | `?id=&since_id=` 订单聊天，传 `since_id` 只取增量（轮询用） |
| `order/sendMessage` | POST | `{id, content}` 发消息 |

**`status` 筛选值**

| 值 | 含义 |
| --- | --- |
| `all`（或省略） | 全部 |
| `1`~`8` | 精确匹配单个状态 |
| `refund` | 退款中(6) + 已退款(8) |
| `closed` | 已关闭(7) |

**订单状态码**

| 码 | 含义 |
| --- | --- |
| 1 | 待支付 |
| 2 | 待接单 |
| 3 | 服务中 |
| 4 | 待确认 |
| 5 | 已完成 |
| 6 | 退款中 |
| 7 | 已关闭 |
| 8 | 已退款 |

**订单对象关键字段**

```json
{
  "id": 12, "order_no": "PO20261015184518...", "status": 3, "status_text": "服务中",
  "amount": 150.0, "amount_text": "¥150.00",
  "game_name": "王者荣耀", "skill_title": "上分陪练", "quantity": 2, "unit_price": 75.0,
  "companion_id": 5, "companion_nickname": "小红", "companion_avatar_url": "http://...",
  "user_id": 2, "user_nickname": "小明", "user_avatar_url": "",
  "my_role": "buyer", "can_chat": true,
  "can_pay": false, "can_cancel": false, "can_refund": true, "can_confirm": false,
  "can_review": false, "can_grab": false, "can_accept": false, "can_finish": false
}
```

> **直接用 `can_*` 字段控制按钮显隐**，不要自己在客户端推断状态机——服务端已经按网页端规则算好了。
> 注意两个反直觉的地方：状态 3（服务中）起只能「申请退款」不能「取消」；
> 取消一张「待接单」的订单，结果是**已退款(8)** 而不是已关闭(7)。

### 3.4 钱包

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `wallet/index` | GET | 余额概览 + 待办数量 + 提现规则 |
| `wallet/transactions` | GET | 资金流水，`?type=&direction=&page=` |
| `wallet/recharge` | POST | 发起充值 `{amount, voucher, remark}` |
| `wallet/rechargePay` | GET | `?id=` 重新获取待支付充值单的支付链接/二维码 |
| `wallet/rechargeDetail` | GET | `?id=` 充值单详情 |
| `wallet/rechargeList` | GET | 充值记录 |
| `wallet/withdraw` | POST | `{amount, account_type, account_name, account_no}` |
| `wallet/withdrawList` | GET | 提现记录 |

**充值流程（两步）**

1. 若渠道是 `manual`（人工转账）：先 `POST upload/image?type=voucher` 上传转账凭证 →
   拿到 `data.path` → 再调 `wallet/recharge` 时把 `path` 填进 `voucher` 字段。
2. 若渠道是在线的：`wallet/recharge` 会返回支付指引：

```json
{
  "code": 0,
  "data": {
    "recharge": { "id": 8, "amount": 100.0, "status": 0, "status_text": "待处理" },
    "pay_mode": "redirect",      // redirect=跳转浏览器  qrcode=App 内展示二维码  manual=人工转账
    "pay_url": "https://...",    // pay_mode=redirect 时用
    "qr_code": ""                // pay_mode=qrcode 时用（内容是一串支付链接，用二维码库渲染）
  }
}
```

> 到账与否**只以服务端回调为准**，App 不要自己改余额，付款后轮询 `wallet/rechargeDetail` 看 `status` 是否变成 1。

`account_type` 取值：`alipay` / `wechat` / `bank`。

### 3.5 陪玩端

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `companion/hall` | GET | 接单大厅，`?game_id=&keyword=&page=` |
| `companion/grab` | POST | `{id}` 抢单（大厅公开单） |
| `companion/orders` | GET | 我接到的订单，`?status=&page=`（`status` 同订单列表） |
| `companion/accept` | POST | `{id}` 接单（订单已被指派给我时用） |
| `companion/finish` | POST | `{id}` 提交完成，等待用户确认 |
| `companion/toggleOnline` | POST | `{online: true\|false}` 一键上下线 |
| `companion/center` | GET | 陪玩中心：资料 + 收益 + 待办 + 技能 |
| `companion/profile` | GET | 我的陪玩资料（含未过审状态） |
| `companion/apply` | POST | 入驻申请，见下 |
| `companion/skills` | GET | 我的服务项目（`?all=1` 含已下架） |
| `companion/skillSave` | POST | 新增/编辑服务项目（传 `id` 表示编辑） |
| `companion/skillDelete` | POST | `{id}` 删除服务项目 |
| `companion/skillToggle` | POST | `{id}` 上架/下架 |
| `companion/incomeStat` | GET | 收益统计 |

> ⚠️ **两套陪玩资料结构，用途不同，别混用：**
>
> | 出现位置 | 结构 | 是否含实名 |
> | --- | --- | --- |
> | `home/companion`（公开主页） | 扁平对象，含 `min_price` / `online` / `score` / `tags` | **否**，`real_name` / `id_card` 一律不返回 |
> | `companion/profile`、`companion/center`、`user/profile` 里的 `profile` 字段 | 扁平对象，含 `real_name` / `id_card` / `phone` / `audit_status` / `audit_remark` | **是**（仅本人可见） |
>
> 创作页面时：展示给别人看的用前者，自己编辑资料的表单用后者。
> 编辑表单提交时请把 `real_name` / `id_card` 原样回填，否则会被覆盖成空。

**`companion/apply` 参数**

```json
{
  "real_name": "张三", "phone": "13800138000", "id_card": "110101...",
  "gender": 1, "city": "北京", "intro": "5 年王者荣耀经验...", "tags": "技术好,声音甜"
}
```

> `real_name` 与 `phone` 必填，`phone` 必须是合法手机号。
> 若后台把「陪玩入驻是否需要审核」关掉，提交即视为通过。

**`companion/skillSave` 参数**

```json
{
  "id": 0, "game_id": 1, "title": "王者荣耀 上分陪练",
  "service_type": "hour", "rank_level": "王者 50 星",
  "price": 30, "description": "...", "cover": "uploads/skill/202610/xxx.jpg"
}
```

`service_type`：`hour` 按小时 / `round` 按局 / `day` 按天 / `custom` 一口价。
`cover` 留空表示保持原图（先调 `upload/image?type=skill` 拿 `path`）。

### 3.6 用户中心 / 工单 / 评价 / 上传

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `user/center` | GET | 「我的」页聚合：用户 + 钱包 + 订单四宫格 + 陪玩状态 + 收益卡 |
| `user/profile` | GET | 个人资料（含陪玩资料） |
| `user/saveProfile` | POST | 改昵称/手机/邮箱/性别/头像，可选改密码 |
| `user/apply` | POST | 陪玩入驻申请（与 `companion/apply` 等价） |
| `ticket/categories` | GET | 工单类型（含 `hint` 说明）与状态选项 |
| `ticket/index` | GET | 我的工单 |
| `ticket/create` | POST | `{category, title, content, order_id}` |
| `ticket/detail` | GET | `?id=` 工单详情 + 对话 |
| `ticket/reply` | POST | `{id, content}` 追问 |
| `ticket/close` | POST | `{id}` 关闭工单 |
| `review/create` | POST | 评价订单 `{order_id, score(1-5), content, tags[], is_anonymous}` |
| `review/reply` | POST | 陪玩回复评价 `{review_id, reply}` |
| `review/mine` | GET | 我收到（陪玩）/ 我发出（买家）的评价 |
| `upload/image` | POST | 上传图片，`?type=avatar\|voucher\|skill\|image` |

**`user/saveProfile` 参数**

```json
{
  "nickname": "小明", "phone": "138...", "email": "a@b.com", "gender": 1,
  "avatar": "uploads/avatar/202610/xxx.jpg",
  "old_password": "", "password": ""
}
```

> 要改密码时必须同时提供 `old_password`，否则返回 422。
> `avatar` 留空表示不改头像。

**`upload/image`**

- `POST multipart/form-data`，`?type=` 决定存储目录与用途
- `type` 取值：`avatar` / `voucher` / `skill` / `image`
- 文件字段名：优先 `file`，也兼容 `avatar` / `cover` / `image` / `voucher`
- 限制：单张 ≤ 5MB，仅 jpg / png / gif / webp
- 返回 `{ "path": "uploads/avatar/202610/xxx.jpg", "url": "http://.../xxx.jpg", "type": "avatar" }`
- **业务接口传的是 `path`（相对路径），不是 `url`**
  - `type=avatar` → 填到 `user/saveProfile` 的 `avatar`
  - `type=voucher` → 填到 `wallet/recharge` 的 `voucher`（仅人工转账渠道需要）
  - `type=skill` → 填到 `companion/skillSave` 的 `cover`

---

## 三点五、订单角标计数口径

`order/index`、`companion/orders`、`user/center` 返回的 `counts` 里各字段**互不重叠**：

| 字段 | 含义 |
| --- | --- |
| `pending_pay` | 待支付（1） |
| `pending_accept` | 待接单（2） |
| `serving` | **仅**服务中（3） |
| `pending_confirm` | 待确认（4） |
| `done` / `completed` | 已完成（5） |
| `refunding` | 退款中(6) + 已退款(8) |

> 网页端把「待接单 + 服务中」都归进「服务中」这一个 Tab，
> App 端因为有三格独立展示，所以拆开统计，**两边数字可能对不上，这是有意为之**，不要把订单重复计数。
>
> 例外：`user/center` 的 `counts` 只有 `total` / `pending_pay` / `pending_accept` /
> `serving` / `pending_confirm` / `completed` / `unread_message`，**没有 `refunding`**
> （个人中心不做退款角标，退款进度去订单列表看）。

---

## 四、安全说明

| 项 | 实现 |
| --- | --- |
| 令牌存储 | 数据库只存 `SHA-256(token)`，拖库也无法反推出可用令牌 |
| 令牌有效期 | 30 天，剩余不足 15 天时自动滑动续期；改密/重置密码会踢掉相关设备 |
| 令牌数量 | 同一账号最多保留 10 个有效令牌，超出的自动作废最旧的 |
| 登录防爆破 | 同 IP / 同账号 15 分钟 10 次失败即锁定 |
| 验证码轰炸 | 同 IP 10 分钟 10 次 |
| 下单/发帖/上传 | 均有独立限流 |
| 越权 | 订单详情与聊天仅买卖双方 + 管理员可读；**工单归属校验在 API 层补齐**（服务层本身不校验） |
| 敏感字段 | `password` / `id_card` / `real_name` / 各类密钥一律不出现在响应里 |
| 上传路径 | 业务接口只接受 `uploads/` 下的相对路径，挡住任意外链注入 |
| 错误信息 | 只透出业务提示；数据库异常统一为「服务异常」，细节仅在 `app.debug=true` 时附带 |

---

## 五、安卓端注意事项

### 1. ⚠️ 明文 HTTP（必须先处理，否则请求直接失败）

站点目前是 `http://`（非 https），而 **Android 9（API 28）起默认禁止明文流量**，
本项目 `minSdk 34`，所以必须在 `AndroidManifest.xml` 里放行：

```xml
<application
    android:networkSecurityConfig="@xml/network_security_config"
    ... >
```

`res/xml/network_security_config.xml`：

```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">103.47.80.215</domain>
    </domain-config>
</network-security-config>
```

长期建议给站点配 HTTPS（宝塔可一键申请 Let's Encrypt），再把这个放行去掉。

### 2. 建议的本地存储

| 内容 | 位置 |
| --- | --- |
| `token` / `expire_at` | `EncryptedSharedPreferences` 或 DataStore |
| 当前用户信息 | DataStore（Room 可选，用于离线展示） |

### 3. 拦截器约定

- 统一加 `Authorization: Bearer <token>`
- 统一解包 `{code, msg, data}`：`code != 0` 时抛业务异常并带 `msg`
- 收到 **401** → 清 token → 跳登录页

### 4. 轮询建议

订单聊天没有 WebSocket，用 `order/messages?id=&since_id=<本地最大消息id>` 增量轮询即可，
建议间隔 3~5 秒，进入订单详情页时开启、离开时停止。
