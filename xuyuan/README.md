# 陪玩点单 App · Android 客户端

配套后端：[陪玩点单平台（原生 PHP + MySQL）](../php/README.md)，已部署在 `http://103.47.80.215/`

---

## 一、技术栈

| 项 | 选型 |
| --- | --- |
| 语言 / UI | Kotlin 2.2.10 + Jetpack Compose（Material 3） |
| 构建 | AGP 9.4.1 · Gradle Kotlin DSL · Version Catalog |
| 最低版本 | minSdk 34 / targetSdk 37 |
| 网络 | Retrofit 2.11 + OkHttp 4.12 + Gson |
| 图片 | Coil 2.7 |
| 导航 | Navigation Compose 2.8 |
| 本地存储 | DataStore Preferences（令牌） |
| 二维码 | zxing core 3.5（支付宝当面付扫码） |
| 依赖注入 | **手写容器**（`AppContainer`），没上 Hilt —— 这个规模用不上注解处理器 |

---

## 二、跑起来

### 1. 改后端地址

只有一个地方需要改：[`app/build.gradle.kts`](app/build.gradle.kts) 里的

```kotlin
buildConfigField("String", "API_BASE_URL", "\"http://你的域名或者ip/\"")
```

> ⚠️ **必须以 `/` 结尾**，否则 Retrofit 初始化时直接抛异常。

### 2. 用 Android Studio 打开

直接 `Open` 本目录（`D:\xuyuan\xuyuan`），等待 Gradle Sync 完成后点 Run。

命令行构建：

```bash
./gradlew :app:assembleDebug
```

产物在 `app/build/outputs/apk/debug/`。

### 3. 前提：后端接口已就绪

App 依赖后端新增的 JSON API。如果还没部署，先按 [`php/API.md`](../php/API.md) 走完三步，
并确认这个地址返回 `"api_ready":true`：

```
http://103.47.80.215/api/index.php?r=common/init
```

---

## 三、项目结构

```
app/src/main/java/com/wolaitongniwan/myapplication/
├── PeiwanApp.kt                   Application，启动时初始化网络层
├── MainActivity.kt                唯一 Activity，setContent { PeiwanTheme { AppRoot() } }
│
├── core/                          与 UI 无关的基础设施
│   ├── ApiResponse.kt             {code,msg,data} 信封 + 分页结构
│   ├── ApiException.kt            统一业务异常（带 code，401 有特殊语义）
│   ├── ApiCall.kt                 ★ 调用 + 解包 + 错误分级，全部收敛在这里
│   ├── AppContainer.kt            手写依赖容器
│   ├── AppEvents.kt               全局事件（目前只有「登录失效」）
│   ├── Session.kt                 当前登录用户状态（全局可观察）
│   └── AppConfig…                 （见 build.gradle.kts 的 BuildConfig）
│
├── data/
│   ├── local/TokenStore.kt        DataStore 持久化 + 内存缓存
│   └── remote/
│       ├── ApiService.kt          ★ 全部 62 个接口定义
│       ├── ApiClient.kt           OkHttp / Retrofit 组装
│       ├── AuthInterceptor.kt     带 Bearer 头 + 捕获 401
│       └── dto/                   7 个文件，与 API.md 严格对齐
│
└── ui/
    ├── AppRoot.kt                 ★ 导航图 + 启动引导 + 401 处理
    ├── theme/                     品牌色 / 主题 / 字体
    ├── navigation/Routes.kt       全部路由常量与构造函数
    ├── common/                    三态封装、占位页、上传工具
    ├── components/                通用组件（骨架、表单、头像、标签、二维码…）
    ├── main/MainScreen.kt         底部导航五 Tab 外壳
    ├── auth/                      登录 / 注册 / 找回密码
    ├── home/                      首页 + 套餐（含 PackagesViewModel）
    ├── companion/                 陪玩列表 / 详情 / 陪玩中心 / 入驻 / 技能
    ├── order/                     订单列表 / 详情 / 聊天 / 评价 / 接单大厅
    ├── wallet/                    钱包 / 流水 / 充值 / 提现 / 记录
    ├── ticket/                    工单列表 / 详情 / 发起
    ├── mine/                      我的 / 编辑资料 / 我的评价
    └── search/                    搜索
```

---

## 四、几个关键设计（改代码前建议先看）

### 1. 所有网络调用都走 `ApiCall`

```kotlin
val data = ApiCall.call { api.homeIndex() }          // 要 data
val msg  = ApiCall.message { api.logout() }          // 只要提示文案
val (d, m) = ApiCall.callWithMessage { api.payOrder(...) }  // 两者都要
```

它统一处理三件事：

1. `code != 0` → 抛 `ApiException`，`message` 已经是可以直接展示给用户的中文
2. HTTP 4xx/5xx（Retrofit 抛 `HttpException`）→ 尽量从响应体里捞出服务端的 `msg`
3. 网络异常 → 转成「无法连接服务器，请检查网络」这类可读文案

**所以 ViewModel 里不需要写任何 `code` 判断**，只要 `catch (e: ApiException) { notify(e.message) }`。

### 2. 401 是「登录失效」的唯一信号

后端约定：只有 401 表示登录态失效（密码错误用 400）。
`AuthInterceptor` 捕获 401 → `AppEvents.emitSessionExpired()` → `AppRoot` 收到后清 token、回登录页。

### 3. 一次性提示用 SharedFlow，不用 State

`BaseViewModel` 提供 `messages: SharedFlow<String>`。
如果把它放进 `State`，屏幕旋转后会**再弹一次**同一个错误。

### 4. 未登录也能浏览

首页、找陪玩、套餐都是公开接口，所以 App **不做闪屏门禁**，直接进主界面；
只有「订单 / 我的 / 钱包」等页面在未登录时显示登录引导。

### 5. `Presenter` 返回的 `can_*` 字段直接用

订单的 `canPay` / `canCancel` / `canRefund` / `canConfirm` / `canReview` / `canChat` 等
是**服务端按网页端规则算好的**，客户端不要自己推断状态机。

两个反直觉点（服务端已兜住，但改代码时要心里有数）：

- 状态 3（服务中）起**不能「取消」，只能「申请退款」**
- 取消一张「待接单」的订单，结果状态是 **8 已退款**，不是 7 已关闭

### 6. Gson 会绕过 Kotlin 的构造默认值

DTO 用非空类型，是**依赖「后端保证每个字段都返回」**这个契约
（`php/app/Api/Presenter.php` 里每个返回体都显式写了所有键）。
如果后端以后漏了某个字段，App 侧会在读取时 NPE —— 改后端 Presenter 时请保持「键齐全」。

### 7. 明文 HTTP

后端目前是 `http://`，而 minSdk 34 默认禁止明文流量，所以
[`res/xml/network_security_config.xml`](app/src/main/res/xml/network_security_config.xml)
**只放行了 `103.47.80.215`**（不是全局 `usesCleartextTraffic`）。

> 后端配好 HTTPS 后，把那个 `domain-config` 整块删掉即可。

---

## 五、已知限制 / 后续可做

| 项 | 说明 |
| --- | --- |
| 包名 | `com.wolaitongniwan.myapplication` 是 Android Studio 模板默认值，**上架前必须改** |
| 推送 | 未接入。后端已有 `api_tokens` 表结构可平滑加设备表 |
| 订单聊天 | 3 秒轮询，没有 WebSocket |
| 收藏 / 关注 | 后端暂无对应表，未做 |
| 应用图标 | 还是模板默认图标 |
| 应用名 | 已改为「陪玩点单」（`res/values/strings.xml`） |

---

## 六、接口文档

完整接口清单、参数、返回结构、错误码、部署步骤见 **[`../php/API.md`](../php/API.md)**。
