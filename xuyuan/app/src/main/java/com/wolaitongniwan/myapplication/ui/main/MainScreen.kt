package com.wolaitongniwan.myapplication.ui.main

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.SportsEsports
import androidx.compose.material.icons.filled.Work
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.key
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.ui.companion.CompanionsScreen
import com.wolaitongniwan.myapplication.ui.home.HomeScreen
import com.wolaitongniwan.myapplication.ui.mine.MineScreen
import com.wolaitongniwan.myapplication.ui.order.HallScreen
import com.wolaitongniwan.myapplication.ui.order.OrdersScreen

/** 底部导航的五个 Tab（「接单」只在已通过审核的陪玩登录时出现） */
enum class MainTab(val label: String, val icon: ImageVector) {
    Home("首页", Icons.Filled.Home),
    Companions("陪玩", Icons.Filled.SportsEsports),
    Hall("接单", Icons.Filled.Work),
    Orders("订单", Icons.Filled.Receipt),
    Mine("我的", Icons.Filled.Person),
}

/**
 * 主界面外壳：底部导航 + 五个 Tab。
 *
 * Tab 内容直接渲染（不再套一层 NavHost），
 * 需要跳转到二级页面时通过回调交给外层的 AppNavHost 处理，
 * 这样能避免「导航套导航」带来的返回栈混乱。
 */
@Composable
fun MainScreen(
    onOpenCompanion: (Long) -> Unit,
    onOpenPackages: () -> Unit,
    onOpenSearch: () -> Unit,
    onOpenOrder: (Long) -> Unit,
    onOpenLogin: () -> Unit,
    onOpenProfileEdit: () -> Unit,
    onOpenWallet: () -> Unit,
    onOpenTransactions: () -> Unit,
    onOpenTickets: () -> Unit,
    onOpenMyReviews: () -> Unit,
    onOpenCompanionCenter: () -> Unit,
    onOpenCompanionApply: () -> Unit,
    onOpenCompanionOrders: () -> Unit,
) {
    val me by Session.me.collectAsStateWithLifecycle()
    val loggedIn = me != null
    val canWork = me?.companion?.let { it.applied && it.auditStatus == 1 } == true

    val tabs = remember(canWork) {
        buildList {
            add(MainTab.Home)
            add(MainTab.Companions)
            if (canWork) add(MainTab.Hall)
            add(MainTab.Orders)
            add(MainTab.Mine)
        }
    }

    // 用枚举名而不是下标保存选中项。
    // 登录态变化会让「接单」Tab 出现/消失，若用下标，用户停在某个 Tab 时会静默跳到别的 Tab。
    var selectedTabName by rememberSaveable { mutableStateOf(MainTab.Home.name) }
    // 每次点底部 Tab 都 +1。配合下面的 key() 让该页子树重建，
    // 页面里的 LaunchedEffect(Unit) 就会重跑 —— 于是「点哪个 Tab 哪个就刷新」，
    // 连「重复点当前 Tab」也能刷新。
    var tabRefreshKey by rememberSaveable { mutableStateOf(0) }
    val selectedIndex = tabs.indexOfFirst { it.name == selectedTabName }.coerceAtLeast(0)

    Scaffold(
        bottomBar = {
            NavigationBar(
                containerColor = MaterialTheme.colorScheme.surface,
            ) {
                tabs.forEachIndexed { index, tab ->
                    NavigationBarItem(
                        selected = selectedIndex == index,
                        onClick = { selectedTabName = tab.name; tabRefreshKey++ },
                        icon = { Icon(tab.icon, contentDescription = tab.label) },
                        label = { Text(tab.label, style = MaterialTheme.typography.labelSmall) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                        ),
                    )
                }
            }
        },
    ) { padding ->
        Box(
            Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            key(tabRefreshKey) {
            when (tabs.getOrNull(selectedIndex)) {
                MainTab.Home -> HomeScreen(
                    onOpenCompanion = onOpenCompanion,
                    onOpenPackages = onOpenPackages,
                    onOpenSearch = onOpenSearch,
                )

                MainTab.Companions -> CompanionsScreen(onOpenCompanion = onOpenCompanion)

                MainTab.Hall -> HallScreen(
                    onOpenOrder = onOpenOrder,
                    onOpenLogin = onOpenLogin,
                    onOpenApply = onOpenCompanionApply,
                    canWork = canWork,
                    loggedIn = loggedIn,
                )

                MainTab.Orders -> OrdersScreen(
                    onOpenOrder = onOpenOrder,
                    onOpenLogin = onOpenLogin,
                    asCompanion = false,
                    loggedIn = loggedIn,
                )

                MainTab.Mine -> MineScreen(
                    loggedIn = loggedIn,
                    onOpenLogin = onOpenLogin,
                    onOpenProfileEdit = onOpenProfileEdit,
                    onOpenWallet = onOpenWallet,
                    onOpenTransactions = onOpenTransactions,
                    onOpenTickets = onOpenTickets,
                    onOpenCompanionCenter = onOpenCompanionCenter,
                    onOpenCompanionApply = onOpenCompanionApply,
                    onOpenHall = {
                        if (tabs.contains(MainTab.Hall)) {
                            selectedTabName = MainTab.Hall.name
                        } else {
                            onOpenCompanionApply()
                        }
                    },
                    onOpenCompanionOrders = onOpenCompanionOrders,
                    onOpenMyReviews = onOpenMyReviews,
                    onGoOrders = {
                        selectedTabName = MainTab.Orders.name
                    },
                )

                null -> Unit
            }
            }   // ← 关闭 key(tabRefreshKey)
        }
    }
}
