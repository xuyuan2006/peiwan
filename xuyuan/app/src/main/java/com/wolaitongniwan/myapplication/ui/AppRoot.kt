package com.wolaitongniwan.myapplication.ui

import android.widget.Toast
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.launch
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.AppEvents
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.ui.auth.ForgotScreen
import com.wolaitongniwan.myapplication.ui.auth.LoginScreen
import com.wolaitongniwan.myapplication.ui.auth.RegisterScreen
import com.wolaitongniwan.myapplication.ui.common.PlaceholderScreen
import com.wolaitongniwan.myapplication.ui.common.ServerNotReadyScreen
import com.wolaitongniwan.myapplication.ui.companion.CompanionApplyScreen
import com.wolaitongniwan.myapplication.ui.companion.CompanionCenterScreen
import com.wolaitongniwan.myapplication.ui.companion.CompanionDetailScreen
import com.wolaitongniwan.myapplication.ui.companion.SkillEditScreen
import com.wolaitongniwan.myapplication.BuildConfig
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppUpdate
import com.wolaitongniwan.myapplication.ui.components.AppUpdateDialog
import com.wolaitongniwan.myapplication.ui.components.checkUpdate
import com.wolaitongniwan.myapplication.ui.home.PackagesScreen
import com.wolaitongniwan.myapplication.ui.main.MainScreen
import com.wolaitongniwan.myapplication.ui.mine.MyReviewsScreen
import com.wolaitongniwan.myapplication.ui.mine.ProfileEditScreen
import com.wolaitongniwan.myapplication.ui.navigation.Routes
import com.wolaitongniwan.myapplication.ui.order.OrderChatScreen
import com.wolaitongniwan.myapplication.ui.order.OrderDetailScreen
import com.wolaitongniwan.myapplication.ui.order.OrdersScreen
import com.wolaitongniwan.myapplication.ui.order.ReviewCreateScreen
import com.wolaitongniwan.myapplication.ui.search.SearchScreen
import com.wolaitongniwan.myapplication.ui.ticket.TicketCreateScreen
import com.wolaitongniwan.myapplication.ui.ticket.TicketDetailScreen
import com.wolaitongniwan.myapplication.ui.ticket.TicketListScreen
import com.wolaitongniwan.myapplication.ui.wallet.RechargeDetailScreen
import com.wolaitongniwan.myapplication.ui.wallet.RechargeListScreen
import com.wolaitongniwan.myapplication.ui.wallet.RechargeScreen
import com.wolaitongniwan.myapplication.ui.wallet.TransactionsScreen
import com.wolaitongniwan.myapplication.ui.wallet.WalletScreen
import com.wolaitongniwan.myapplication.ui.wallet.WithdrawListScreen
import com.wolaitongniwan.myapplication.ui.wallet.WithdrawScreen

/**
 * 应用根：导航图 + 启动引导 + 全局 401 处理。
 *
 * 启动流程刻意不做「闪屏门」：
 * 首页、找陪玩、套餐这些接口本来就允许游客访问，直接进主界面体验更好；
 * 只有「订单 / 我的 / 钱包」等需要登录的页面在未登录时展示登录引导。
 */
@Composable
fun AppRoot() {
    val nav = rememberNavController()
    val context = LocalContext.current
    val me by Session.me.collectAsStateWithLifecycle()
    val loggedIn = me != null

    // 服务端接口是否就绪（部署漏执行 sql/api_upgrade.sql 时会拦住）
    var bootBlocked by remember { mutableStateOf<String?>(null) }
    var retryKey by remember { mutableStateOf(0) }

    // 版本更新：后台把「App 最新版本号」填得比本机大，这里就有值 → 弹更新窗
    var appUpdate by remember { mutableStateOf<AppUpdate?>(null) }

    LaunchedEffect(retryKey) {
        bootBlocked = null

        // 1) 握手：确认后端接口层可用
        try {
            val init = ApiCall.call { AppContainer.api.commonInit() }

            // 顺便做版本检查：服务端版本号比本机 BuildConfig.VERSION_NAME 大就弹窗
            appUpdate = checkUpdate(
                serverVersion = init.minAppVersion,
                note = init.updateNote,
                url = init.updateUrl,
                force = init.forceUpdate,
                currentVersion = BuildConfig.VERSION_NAME,
            )

            if (!init.apiReady) {
                bootBlocked = "服务端接口表尚未就绪（缺少 api_tokens）。\n" +
                    "请在数据库中执行 sql/api_upgrade.sql 后重试。"
            }
        } catch (e: ApiException) {
            // 只有服务端明确说「没装好」才拦人；
            // 没网、超时这类情况放行，用户仍能浏览首页等公开内容。
            if (e.code == 503) {
                bootBlocked = e.message
            }
        }

        // 2) 本地有令牌就静默拉一次 auth/me，补齐昵称/头像/钱包/陪玩身份
        if (bootBlocked == null && AppContainer.isLoggedIn()) {
            try {
                Session.update(ApiCall.call { AppContainer.api.me() })
            } catch (e: ApiException) {
                if (e.isUnauthorized) {
                    AppContainer.tokenStore.clear()
                    Session.update(null)
                }
            }
        }
    }

    // App 回到前台时静默刷新一次登录态。
    //
    // 为什么需要：Session 只是内存缓存，只在「登录 / 注册 / 自己主动操作」后才更新。
    // 管理员在后台批准陪玩申请、改账号状态、或钱包有变动时，App 这边完全不知情 ——
    // 用户必须杀掉进程重进才能看到新状态（这就是「退出软件再进才显示」的原因）。
    // 这个 effect 把那一步自动化：每次从后台切回来都重新拉一次。
    val lifecycleOwner = LocalLifecycleOwner.current
    val refreshScope = rememberCoroutineScope()
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME && AppContainer.isLoggedIn()) {
                refreshScope.launch {
                    try {
                        Session.update(ApiCall.call { AppContainer.api.me() })
                    } catch (e: ApiException) {
                        if (e.isUnauthorized) {
                            AppContainer.tokenStore.clear()
                            Session.update(null)
                        }
                        // 其它错误（没网等）静默忽略：切回前台不该弹报错
                    }
                }
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    // 版本更新弹窗（服务端版本更大时才出现；强制更新时关不掉）
    appUpdate?.let { info ->
        AppUpdateDialog(info = info, onDismiss = { appUpdate = null })
    }

    // 任何接口返回 401 → 清登录态并回登录页
    LaunchedEffect(Unit) {
        AppEvents.sessionExpired.collect { message ->
            AppContainer.tokenStore.clear()
            Session.update(null)
            Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
            nav.navigate(Routes.LOGIN) {
                popUpTo(Routes.MAIN)
                launchSingleTop = true
            }
        }
    }

    /**
     * 登录/注册成功后回主界面。
     *
     * 用 popUpTo(MAIN, inclusive) + launchSingleTop 重建到主界面，
     * 而不是 popBackStack() —— 后者在返回栈里还有其它页面时会「退回上一页」，
     * 与「登录成功后回首页」的预期不符。
     */
    val backToMain: () -> Unit = {
        nav.navigate(Routes.MAIN) {
            popUpTo(Routes.MAIN) { inclusive = true }
            launchSingleTop = true
        }
    }

    // 服务端没就绪就直接拦下，别让用户在里面到处点都报错。
    // 用直接的 if + return，不要写在 let 里 —— Compose 编译器对「inline lambda 内提前返回」
    // 的 group 管理有坑，直接 return 才是被支持的形式。
    val blockedReason = bootBlocked
    if (blockedReason != null) {
        ServerNotReadyScreen(message = blockedReason, onRetry = { retryKey++ })
        return
    }

    NavHost(navController = nav, startDestination = Routes.MAIN) {

        // ---------------- 主界面 ----------------
        composable(Routes.MAIN) {
            MainScreen(
                onOpenCompanion = { nav.navigate(Routes.companionDetail(it)) },
                onOpenPackages = { nav.navigate(Routes.PACKAGES) },
                onOpenSearch = { nav.navigate(Routes.SEARCH) },
                onOpenOrder = { nav.navigate(Routes.orderDetail(it)) },
                onOpenLogin = { nav.navigate(Routes.LOGIN) },
                onOpenProfileEdit = { nav.navigate(Routes.PROFILE_EDIT) },
                onOpenWallet = { nav.navigate(Routes.WALLET) },
                onOpenTransactions = { nav.navigate(Routes.TRANSACTIONS) },
                onOpenTickets = { nav.navigate(Routes.TICKET_LIST) },
                onOpenMyReviews = { nav.navigate(Routes.MY_REVIEWS) },
                onOpenCompanionCenter = { nav.navigate(Routes.COMPANION_CENTER) },
                onOpenCompanionApply = { nav.navigate(Routes.COMPANION_APPLY) },
                // 「我的接单」：页面上早就注册好了，但一直没有任何入口能跳过去 ——
                // 陪玩接单后就再也看不到自己的单、也进不了订单详情联系顾客。
                onOpenCompanionOrders = { nav.navigate(Routes.COMPANION_ORDERS) },
            )
        }

        // ---------------- 鉴权 ----------------
        composable(Routes.LOGIN) {
            LoginScreen(
                onLoggedIn = backToMain,
                onGoRegister = { nav.navigate(Routes.REGISTER) },
                onGoForgot = { nav.navigate(Routes.FORGOT) },
            )
        }
        composable(Routes.REGISTER) {
            RegisterScreen(
                onBack = { nav.popBackStack() },
                onRegistered = backToMain,
            )
        }
        composable(Routes.FORGOT) {
            ForgotScreen(
                onBack = { nav.popBackStack() },
                onDone = { nav.popBackStack() },
            )
        }

        // ---------------- 内容浏览 ----------------
        composable(Routes.PACKAGES) {
            PackagesScreen(
                onBack = { nav.popBackStack() },
                onOpenOrder = { nav.navigate(Routes.orderDetail(it)) },
                onNeedLogin = { nav.navigate(Routes.LOGIN) },
                loggedIn = loggedIn,
            )
        }
        composable(
            route = Routes.COMPANION_DETAIL,
            arguments = listOf(navArgument("id") { type = NavType.LongType }),
        ) { entry ->
            CompanionDetailScreen(
                companionId = entry.arguments?.getLong("id") ?: 0L,
                onBack = { nav.popBackStack() },
                onOpenOrder = { nav.navigate(Routes.orderDetail(it)) },
                onNeedLogin = { nav.navigate(Routes.LOGIN) },
                loggedIn = loggedIn,
            )
        }

        // ================================================================
        // 二级页面
        //
        // 唯一的例外是 Routes.SKILL_LIST：陪玩中心已经把技能列表 + 增删改查
        // 都做在同一个页面里了，所以这个独立路由暂时没有入口，保留占位页待用。
        // ================================================================
        composable(Routes.SEARCH) {
            SearchScreen(
                onBack = { nav.popBackStack() },
                onOpenCompanion = { nav.navigate(Routes.companionDetail(it)) },
                onOpenPackages = { nav.navigate(Routes.PACKAGES) },
            )
        }
        composable(
            route = Routes.ORDER_DETAIL,
            arguments = listOf(navArgument(Routes.ORDER_DETAIL_ARG) { type = NavType.LongType }),
        ) { entry ->
            val orderId = entry.arguments?.getLong(Routes.ORDER_DETAIL_ARG) ?: 0L
            OrderDetailScreen(
                orderId = orderId,
                onBack = { nav.popBackStack() },
                onOpenChat = { nav.navigate(Routes.orderChat(it)) },
                onOpenCompanion = { nav.navigate(Routes.companionDetail(it)) },
                onOpenReview = { nav.navigate(Routes.reviewCreate(it)) },
                onOpenTicket = { nav.navigate(Routes.ticketCreate(it)) },
            )
        }
        composable(
            route = Routes.ORDER_CHAT,
            arguments = listOf(navArgument(Routes.ORDER_DETAIL_ARG) { type = NavType.LongType }),
        ) { entry ->
            OrderChatScreen(
                orderId = entry.arguments?.getLong(Routes.ORDER_DETAIL_ARG) ?: 0L,
                onBack = { nav.popBackStack() },
            )
        }
        composable(
            route = Routes.REVIEW_CREATE,
            arguments = listOf(navArgument(Routes.REVIEW_CREATE_ARG) { type = NavType.LongType }),
        ) { entry ->
            ReviewCreateScreen(
                orderId = entry.arguments?.getLong(Routes.REVIEW_CREATE_ARG) ?: 0L,
                onBack = { nav.popBackStack() },
                onDone = { nav.popBackStack() },
            )
        }
        composable(Routes.COMPANION_ORDERS) {
            // OrdersScreen 本身是「作为 Tab 使用」的无标题栏版本，
            // 这里套一层 AppScaffold 给它补上返回栏，就能当二级页面用。
            AppScaffold(title = "我的接单", onBack = { nav.popBackStack() }) { inner ->
                Box(
                    Modifier
                        .fillMaxSize()
                        .padding(inner),
                ) {
                    OrdersScreen(
                        onOpenOrder = { nav.navigate(Routes.orderDetail(it)) },
                        onOpenLogin = { nav.navigate(Routes.LOGIN) },
                        asCompanion = true,
                        loggedIn = loggedIn,
                    )
                }
            }
        }

        composable(Routes.WALLET) {
            WalletScreen(
                onBack = { nav.popBackStack() },
                onOpenTransactions = { nav.navigate(Routes.TRANSACTIONS) },
                onOpenRecharge = { nav.navigate(Routes.RECHARGE) },
                onOpenWithdraw = { nav.navigate(Routes.WITHDRAW) },
                onOpenRechargeList = { nav.navigate(Routes.RECHARGE_LIST) },
                onOpenWithdrawList = { nav.navigate(Routes.WITHDRAW_LIST) },
            )
        }
        composable(Routes.TRANSACTIONS) {
            TransactionsScreen(onBack = { nav.popBackStack() })
        }
        composable(Routes.RECHARGE) {
            RechargeScreen(
                onBack = { nav.popBackStack() },
                onDone = { nav.popBackStack() },
            )
        }
        composable(
            route = Routes.RECHARGE_DETAIL,
            arguments = listOf(navArgument("id") { type = NavType.LongType }),
        ) { entry ->
            RechargeDetailScreen(
                rechargeId = entry.arguments?.getLong("id") ?: 0L,
                onBack = { nav.popBackStack() },
            )
        }
        composable(Routes.RECHARGE_LIST) {
            RechargeListScreen(
                onBack = { nav.popBackStack() },
                onOpenDetail = { nav.navigate(Routes.rechargeDetail(it)) },
            )
        }
        composable(Routes.WITHDRAW) {
            WithdrawScreen(
                onBack = { nav.popBackStack() },
                onDone = { nav.popBackStack() },
            )
        }
        composable(Routes.WITHDRAW_LIST) {
            WithdrawListScreen(onBack = { nav.popBackStack() })
        }

        composable(Routes.PROFILE_EDIT) {
            ProfileEditScreen(
                onBack = { nav.popBackStack() },
                onSaved = { nav.popBackStack() },
            )
        }
        composable(Routes.COMPANION_CENTER) {
            CompanionCenterScreen(
                onBack = { nav.popBackStack() },
                onOpenSkillEdit = { nav.navigate(Routes.skillEdit(it)) },
                onOpenApply = { nav.navigate(Routes.COMPANION_APPLY) },
                onOpenMyOrders = { nav.navigate(Routes.COMPANION_ORDERS) },
            )
        }
        composable(Routes.COMPANION_APPLY) {
            CompanionApplyScreen(
                onBack = { nav.popBackStack() },
                onDone = { nav.popBackStack() },
            )
        }
        composable(Routes.SKILL_LIST) { PlaceholderScreen("服务项目", { nav.popBackStack() }) }
        composable(
            route = Routes.SKILL_EDIT,
            arguments = listOf(
                navArgument(Routes.SKILL_EDIT_ARG) {
                    type = NavType.LongType
                    defaultValue = 0L
                }
            ),
        ) { entry ->
            SkillEditScreen(
                skillId = entry.arguments?.getLong(Routes.SKILL_EDIT_ARG) ?: 0L,
                onBack = { nav.popBackStack() },
                onDone = { nav.popBackStack() },
            )
        }

        composable(Routes.TICKET_LIST) {
            TicketListScreen(
                onBack = { nav.popBackStack() },
                onOpenDetail = { nav.navigate(Routes.ticketDetail(it)) },
                onCreate = { nav.navigate(Routes.ticketCreate(0)) },
            )
        }
        composable(
            route = Routes.TICKET_DETAIL,
            arguments = listOf(navArgument("id") { type = NavType.LongType }),
        ) { entry ->
            TicketDetailScreen(
                ticketId = entry.arguments?.getLong("id") ?: 0L,
                onBack = { nav.popBackStack() },
            )
        }
        composable(
            route = Routes.TICKET_CREATE,
            arguments = listOf(
                navArgument(Routes.TICKET_CREATE_ARG) {
                    type = NavType.LongType
                    defaultValue = 0L
                }
            ),
        ) { entry ->
            val orderId = entry.arguments?.getLong(Routes.TICKET_CREATE_ARG) ?: 0L
            TicketCreateScreen(
                orderId = orderId,
                onBack = { nav.popBackStack() },
                onCreated = { newId ->
                    // 提交成功直接进详情页，把创建页从返回栈里弹掉
                    nav.popBackStack()
                    if (newId > 0) nav.navigate(Routes.ticketDetail(newId))
                },
            )
        }
        composable(Routes.MY_REVIEWS) { MyReviewsScreen(onBack = { nav.popBackStack() }) }
    }
}
