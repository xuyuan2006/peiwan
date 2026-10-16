# 服务器更换指南（换 IP / 换域名时看这一份）

> 适用场景：把后端从 `http://103.47.80.215/` 搬到别的服务器、或者从 IP 换成域名、或者从 http 换成 https。
>
> **核心结论：App 端只有 2 个文件需要改，而且必须同时改** —— 只改一个的话 App 会连不上后端。

---

## 一、App 端：只有 2 个地方

### 1️⃣ 接口地址（App 请求后端用的）

**文件**：`xuyuan/app/build.gradle.kts`

```kotlin
defaultConfig {
    ...
    buildConfigField("String", "API_BASE_URL", "\"http://103.47.80.215/\"")   // ← 改这里
}
```

改成新地址，**结尾的 `/` 不能少**：

| 情况 | 应该写成 |
|---|---|
| 换成新 IP | `"http://1.2.3.4/"` |
| 换成域名 | `"https://api.example.com/"` |
| 带子目录 | `"https://example.com/peiwan/"` |

> ⚠️ 必须是**完整的根地址**（指向 PHP 的 `public/` 目录），
> 不是 `http://xxx/api/` —— 代码里会自己拼 `/api/index.php?r=...`。

**改完这个，全 App 所有请求都会走新地址**（包括图片、二维码），因为只有这一个来源。

---

### 2️⃣ 明文流量白名单（http 地址必须改，https 可以删）

**文件**：`xuyuan/app/src/main/res/xml/network_security_config.xml`

```xml
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">103.47.80.215</domain>   <!-- ← 改这里 -->
    </domain-config>
</network-security-config>
```

**为什么必须改**：Android 9（API 28）起默认**禁止明文 HTTP 流量**。
放行名单里只写了旧地址，新地址不在名单里 → **所有请求直接失败**，而且报的是「网络错误」，很难查。

**两种改法**：

- **新地址还是 http** → 把 `<domain>` 换成新的（IP 或域名，**不带** `http://`、**不带**端口和路径）
  ```xml
  <domain includeSubdomains="true">1.2.3.4</domain>
  ```
- **新地址已经是 https**（推荐）→ **把整个 `<domain-config>` 块删掉**，只留：
  ```xml
  <network-security-config>
  </network-security-config>
  ```
  或者干脆把 `AndroidManifest.xml` 里的 `android:networkSecurityConfig` 那一行也删掉。

> 宝塔面板可以一键申请 Let's Encrypt 免费证书，配好 https 之后 App 端会更安全，也省掉这个文件的维护。

---

### 3️⃣ 重新打包

`build.gradle.kts` 的改动**必须重新构建**才生效（不是增量）：

```
Build → Build APK(s)
```

产物：`xuyuan/app/build/outputs/apk/debug/app-debug.apk`

---

## 二、PHP 端：通常不用改

后端的「自己是什么地址」是**自动推导**的（`app/api_bootstrap.php`），
它会按客户端实际请求的域名来生成图片地址和支付回调地址，所以换域名后**一般不需要动代码**。

**唯一需要留意的是**：如果 `config/config.php` 里**显式写了** `app.url`，那它优先级最高，必须手动改：

```php
return [
    'app' => [
        'url' => 'http://你自己的ip或者域名',   // ← 有这行就必须改；没这行就别加
    ],
];
```

> 建议：**保持不写 `app.url`**，让它自动推导，换域名时零改动。

---

## 三、外部平台（最容易漏，必须手动改）

这些地址**不在你的代码里**，而在第三方的后台，换域名后**必须去改**，否则支付回调收不到、钱到不了账：

| 平台 | 要改什么 | 在哪改 |
|---|---|---|
| **易支付** | 异步通知地址、同步跳转地址 | 易支付商户后台 |
| **支付宝当面付** | 授权回调地址 / 网关白名单 | 支付宝开放平台 |
| **短信服务** | 一般不用改 | —— |

**准确地址在这里查**：后台 →「系统 → **支付设置**」页面，那里有**只读**的两个地址：

```
异步通知：https://你的域名/index.php?r=pay/notify
同步跳转：https://你的域名/index.php?r=pay/back
```

把这两个（以及支付宝的那些）**原样填到第三方平台的后台**。

---

## 四、换服务器完整清单（按顺序做）

### 搬数据

- [ ] 导出旧库：`mysqldump -u 用户 -p 库名 > backup.sql`
- [ ] 打包整个项目目录（含 `config/config.php`）
- [ ] **`public/uploads/` 一定要一起搬** —— 用户头像、陪玩封面、充值凭证、二维码都在里面，漏了就是一片裂图
- [ ] 新服务器上建库、导入 `backup.sql`
- [ ] 上传项目文件、确认 `config` 目录和 `public/uploads` 可写

### 配环境

- [ ] 新服务器装 PHP 7.4+、MySQL，开启 `pdo_mysql / mbstring / json`
- [ ] 站点根目录指向 `public/`（**不是**项目根目录）
- [ ] 如果换域名，把域名解析到新 IP

### 验证后端（**先做这步，再做 App**）

- [ ] 浏览器访问 `https://新地址/check.php` → **语法检查应全绿**
- [ ] 访问 `https://新地址/api/index.php?r=common/init` → 应返回 `{"code":0,...,"api_ready":true}`
- [ ] 前台能正常打开、能登录

### 改 App 并重新打包

- [ ] 改 `build.gradle.kts` 的 `API_BASE_URL`
- [ ] 改（或删）`network_security_config.xml`
- [ ] 重新 `Build APK(s)`
- [ ] 手机上覆盖安装，登录一次确认能通

### 改第三方回调

- [ ] 易支付后台改通知/跳转地址
- [ ] 支付宝开放平台改回调地址
- [ ] 用一笔小额充值实测到账

### 收尾（安全）

- [ ] **删除 `install.php` / `check.php` / `selftest.php` / `demo.php`** ← 这四个是部署期工具，留在线上有风险
- [ ] 确认 `sql/` 目录不可被公网访问

---

## 五、常见问题

**Q：只改了 `API_BASE_URL`，App 报「网络错误 / 连接失败」？**
A：八成是忘了改 `network_security_config.xml`。用 http 地址时，新域名不在放行名单里，Android 会直接掐断请求。

**Q：登录能用，但图片全是裂的？**
A：`public/uploads/` 没搬过去，或者新服务器上这个目录不可写。

**Q：支付成功了但余额没加？**
A：第三方平台的**异步通知地址**没改到新域名，回调发到旧服务器去了。

**Q：换成了 https，App 还需要 `network_security_config.xml` 吗？**
A：不需要，建议直接删掉那个 `<domain-config>` 块。

**Q：App 名字（桌面图标下的文字）在哪改？**
A：`xuyuan/app/src/main/res/values/strings.xml` 里的 `app_name`。
登录页标题也读同一个值，**只改这一处就够**。注意它编译进 APK，改完要重新打包。

**Q：后端的站点名称（网页顶部显示的那个）在哪改？**
A：**不用改代码** —— 后台「平台设置 → 站点名称」直接改。

---

## 附：一张图记住

```
                    ┌─ build.gradle.kts 的 API_BASE_URL     ← 改
   换服务器 ────────┤
                    └─ network_security_config.xml 的 domain ← 改（或删）

    PHP 端 ─────────── 不用改（自动推导）；除非 config.php 里写了 app.url

    第三方平台 ─────── 必须改（易支付 / 支付宝 的回调地址）

    数据 ───────────── 数据库 + public/uploads/ 一起搬
```
