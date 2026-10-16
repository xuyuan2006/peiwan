# 我来同你玩 · 部署文档

> 陪玩点单平台（PHP 后端 + Android 客户端）的完整部署说明。
> 按本文档从上到下走一遍，即可从零把项目跑起来。

---

## 一、项目构成

```
D:\xuyuan\
├── php\                          后端（原生 PHP 7.4+，无框架，无 Composer 依赖）
│   ├── app\                      业务代码（控制器 / 服务层 / 视图）
│   │   ├── Controllers\          前台与后台控制器
│   │   ├── Controllers\Api\      ★ App 接口层
│   │   ├── Api\                  ★ 接口基础设施（路由 / 鉴权 / 限流 / 序列化）
│   │   ├── views\                网页模板
│   │   ├── Mailer.php            自研 SMTP 邮件
│   │   └── Notify.php            通知（工单回复邮件）
│   ├── config\
│   │   └── config.php            数据库等配置（由安装向导生成，★ 含密码，勿泄露）
│   ├── public\                   ★★ 站点根目录必须指向这里，不是 php\
│   │   ├── index.php             前台入口
│   │   ├── admin\ 或自定义名\     后台入口（安装时可改名）
│   │   ├── api\index.php         ★ App 接口入口
│   │   ├── uploads\              上传文件（头像 / 封面 / 凭证 / 二维码）
│   │   ├── install.php           安装向导（装完必须删）
│   │   ├── check.php             系统自检（装完必须删）
│   │   ├── selftest.php          业务自检（装完必须删）
│   │   ├── demo.php              演示数据（装完必须删）
│   │   └── assets\               css / js
│   └── sql\
│       ├── schema.sql            全新建库用
│       └── api_upgrade.sql       ★ 已装站点升级用（幂等）
│
└── xuyuan\                       Android 客户端（Kotlin + Jetpack Compose）
    ├── app\build.gradle.kts      ★ 接口地址 / 版本号 / minSdk
    ├── app\src\main\res\xml\network_security_config.xml   ★ http 放行名单
    ├── MIGRATION.md              换服务器 / 换域名指南
    └── RELEASE.md                每次发新版的流程
```

**两部分的关系**：App 只通过 `php/public/api/index.php` 这一个入口和后端通信，接口格式是 `{code, msg, data}`。

---

## 二、环境要求

| 项 | 要求 | 检查方式 |
|---|---|---|
| PHP | **7.4 或更高**（实测 8.2 正常） | 后台自检 / `check.php` |
| 扩展 | `pdo_mysql`、`mbstring`、`json` | 同上 |
| 数据库 | MySQL 5.7+ / MariaDB 10.3+，字符集 **utf8mb4** | —— |
| Web 服务器 | Nginx 或 Apache，**根目录指向 `public/`** | 见第三节 |
| 目录权限 | `config/` 与 `public/uploads/` **可写** | `check.php` 会检测 |
| 安卓客户端 | Android Studio，`minSdk 26`（安卓 8.0+） | —— |

> **PHP 7.4 兼容性**：代码里没有用 `match` / `enum` / `readonly` / `?->` / 箭头函数等 8.0+ 语法，7.4 可跑。

---

## 三、后端部署 · 全新安装

### 1. 上传代码

把 `php/` 目录下**全部内容**上传到服务器，例如：

```
/www/wwwroot/你的域名/
```

### 2. 设置站点根目录 ← **最容易错的一步**

把站点的运行目录指向 **`public`**，**不是**项目根目录：

| 面板 | 怎么设 |
|---|---|
| 宝塔 | 网站 → 设置 → 网站目录 → 运行目录选 **`/public`** |
| Nginx | `root /www/wwwroot/你的域名/public;` |
| Apache | `DocumentRoot ".../public"` |

**为什么必须这样**：`app/`、`config/`、`sql/` 这些目录放在 web 根目录之外才不会被公网直接下载到 —— 尤其是 `config/config.php` 里存着数据库密码。

### 3. 建库

新建一个数据库（推荐 utf8mb4），记下：库名、用户名、密码。

### 4. 跑安装向导

浏览器访问：

```
http://你的域名/install.php
```

按提示填：数据库信息 → 管理员账号密码 → 站点名称 → **后台入口目录名**（建议改掉默认的 `admin`，防止被扫描）。

安装向导会自动建表 + 写入 `config/config.php`。

### 5. 执行增量升级脚本 ← **别跳过**

`sql/schema.sql` 里已经包含了最新表结构，但**安装向导可能用的是旧结构**。为了确保 `api_tokens` 等表存在，再执行一次增量脚本：

宝塔 → 数据库 → phpMyAdmin → 选中库 → SQL 窗口 → 粘贴 `sql/api_upgrade.sql` 的**全部内容** → 执行。

> 这个脚本是**幂等**的（`CREATE TABLE IF NOT EXISTS`），重复执行安全。
> **唯一例外**是里面那条 `ALTER TABLE users ADD COLUMN no` —— MySQL 不支持 `IF NOT EXISTS`，
> 若报 `Duplicate column name 'no'` 说明已经加过，跳过那条即可。

### 6. 验证

```
http://你的域名/check.php
```

应看到：

```
PHP 语法检查（等价 php -l）
已解析 XXX 个文件
全部文件语法正确。
```

再访问接口握手：

```
http://你的域名/api/index.php?r=common/init
```

应返回：

```json
{"code":0,"msg":"ok","data":{"api_ready":true, ...}}
```

**`api_ready: true` 是决定性标志** —— 它说明 `api_tokens` 表存在、接口层可用。
如果是 `false` 或报错，回到第 5 步检查 SQL 有没有执行。

---

## 四、后端部署 · 已有站点升级

**不要重跑 `install.php`** —— 它的「重新安装」会重置管理员密码。

只需两步：

1. **覆盖上传**改动的 PHP 文件
2. 执行 `sql/api_upgrade.sql` 里**新增的**部分

然后访问 `check.php` 确认语法全绿。

> 升级后如果某个页面报「服务异常」，**第一件事就是看 `check.php`** ——
> 它会直接指出是哪个文件语法有错（这类错误框架拦不到，页面上看不到有意义的信息）。

---

## 五、后台初始配置清单

登录后台（`http://你的域名/你设置的后台名/`），按需配置：

| 位置 | 配什么 | 影响 |
|---|---|---|
| **平台设置 → 基础** | 站点名称、客服电话、公告 | 前台顶部、App 首页 |
| **平台设置 → 运营展示** | **网站 Logo**（上传） | 网页顶部 + App 登录页；留空用默认图标 |
| **平台设置 → 运营展示** | **App 最新版本号 / 强制更新 / 下载地址 / 更新说明** | App 的更新弹窗，详见 `xuyuan/RELEASE.md` |
| **平台设置 → 运营展示** | **陪玩考核标准** | 申请陪玩页必须停留 4 秒才能提交 |
| **平台设置 → 运营展示** | **客服二维码 / 客服联系方式** | 提交陪玩申请后展示 |
| **平台设置 → 验证方式** | 注册/找回密码是否需要邮箱或短信验证码 | ★ 这同时也是**工单回复邮件通知**的总开关：邮箱验证开启时才会发通知邮件 |
| **平台设置 → 邮箱 / SMTP** | SMTP 服务器、账号、授权码 | 验证码邮件、工单通知邮件 |
| **支付设置** | 支付渠道（人工转账 / 易支付 / 当面付） | 见下方说明 |
| **运营 → 游戏管理** | 游戏分类 | 前台筛选、接单大厅 |
| **运营 → 点单套餐** | 一口价套餐（可传封面图） | 首页「直接点单」 |

### 支付渠道说明

| 渠道 | App 端表现 |
|---|---|
| **人工转账** | 用户上传转账凭证 → 客服在后台「充值审核」确认到账 |
| **易支付** | 用户选**微信 / 支付宝** → 跳浏览器付款 → 返回 App 余额自动刷新 |
| **当面付（支付宝）** | App 内展示二维码，用户扫码支付 |

选易支付时，若「网关地址 / 商户ID / 商户密钥」没填全，系统会**自动退回人工转账**（不会报错）。

**换域名后必做**：到**易支付后台**和**支付宝开放平台**把异步通知地址改掉。
准确地址在后台「支付设置」页面上有只读显示：

```
异步通知：https://你的域名/index.php?r=pay/notify
同步跳转：https://你的域名/index.php?r=pay/back
```

漏改会导致**用户付款成功但余额不加**。

---

## 六、Android 客户端打包

### 1. 改两个配置

**① 接口地址** —— `xuyuan/app/build.gradle.kts`

```kotlin
buildConfigField("String", "API_BASE_URL", "\"http://你的域名/\"")
```

> 结尾的 `/` 不能少；填**站点根地址**（指向 `public/`），不是 `/api/`。

**② http 放行名单** —— `xuyuan/app/src/main/res/xml/network_security_config.xml`

```xml
<domain includeSubdomains="true">你的域名或IP</domain>
```

**必须改**：Android 9（API 28）起默认禁止明文 HTTP，名单里没有新域名，App 会报「网络错误」但查不出原因。

> 如果后端已经配好 HTTPS（宝塔可一键申请 Let's Encrypt），**把整个 `<domain-config>` 块删掉**即可，更安全也少一处维护。

### 2. 改版本号（每次发版都要）

```kotlin
versionCode = 2          // 每次 +1
versionName = "1.1"      // 每次往上涨
```

**忘了改的后果**：App 一直报旧版本 → 更新弹窗永远弹。详见 `xuyuan/RELEASE.md`。

### 3. 构建

```
Android Studio → Build → Build APK(s)
```

产物：`xuyuan/app/build/outputs/apk/debug/app-debug.apk`

### 4. 分发

把 APK 传到服务器（建议 `public/uploads/app/app-1.1.apk`），然后在后台「App 下载地址」填这个链接。

> 每次发版**换新文件名**（带版本号），否则用户可能下到浏览器缓存的旧包。

---

## 七、上线前安全检查

**装完之后，这四个文件必须删掉**（或至少改名）：

```
public/install.php      安装向导，留着可被重新安装 / 重置管理员密码
public/check.php        自检，会泄露服务器绝对路径与后台入口名
public/selftest.php     业务自检
public/demo.php         演示数据生成
```

删除方式：宝塔文件管理里直接删，或命令行 `rm`。

另外确认：

- [ ] 站点根目录是 `public/`，`config/` 和 `sql/` **不能**被公网访问
- [ ] `config/config.php` 权限设为 **644**（可读不可写），或至少不要 777
- [ ] 数据库账号只授权这一个库，不要用 root
- [ ] 后台入口改成了自定义名字（安装时设的）

---

## 八、常见问题

**Q：访问首页是空白 / 500？**
A：先看 `check.php`（如果已删，临时改名放回去）。它会指出语法错误和目录权限问题。

**Q：App 报「网络错误」但网页能打开？**
A：`network_security_config.xml` 没放行新域名。用 http 地址时必须同步改这个文件。

**Q：App 提示「服务端接口表尚未就绪」？**
A：`api_tokens` 表不存在 → 执行 `sql/api_upgrade.sql`。

**Q：图片全是裂的？**
A：`public/uploads/` 不可写，或者迁移时没把这个目录一起搬。

**Q：用户付款成功但余额不加？**
A：第三方平台（易支付/支付宝）的**异步通知地址**没改到新域名。

**Q：工单回复后收不到邮件？**
A：邮件通知的总开关是「注册/找回密码验证方式」—— 只有它设为**邮箱**、且 SMTP 配置完整时才会发。
另外收件人必须在注册时填了邮箱。

**Q：App 更新弹窗一直弹？**
A：`build.gradle.kts` 的 `versionName` 没跟着改。见 `xuyuan/RELEASE.md`。

**Q：用户看不到自己的 ID？**
A：ID 是后台/接口自动生成的 6 位用户号。若显示为 0，检查 `users` 表有没有 `no` 列（执行 `api_upgrade.sql`）。

---

## 九、相关文档

| 文档 | 什么时候看 |
|---|---|
| `xuyuan/MIGRATION.md` | **换服务器 / 换 IP / 换域名** —— App 端要改哪 2 个文件、要搬哪些数据、要改哪些第三方回调 |
| `xuyuan/RELEASE.md` | **每次发新版 App** —— 版本号规则、更新弹窗原理、完整发版流程 |
| `php/API.md` | 接口协议细节（所有端点、字段、订单状态机、错误码） |
| `php/README.md` | 后端代码结构与业务说明 |

---

## 十、一张图记住部署顺序

```
① 上传 php/ 全部内容
② 站点根目录指向 public/          ← 最容易错
③ 建库
④ 访问 /install.php 完成安装
⑤ 执行 sql/api_upgrade.sql        ← 确保接口表存在
⑥ 访问 /check.php       → 语法全绿
   访问 /api/index.php?r=common/init → api_ready: true
⑦ 后台配置：Logo / 支付 / 邮箱验证 / 版本号
⑧ 改 App 的 API_BASE_URL + network_security_config.xml
⑨ Android Studio 打包 APK → 传到 /uploads/app/
⑩ 删除 install.php / check.php / selftest.php / demo.php   ← 安全
```
