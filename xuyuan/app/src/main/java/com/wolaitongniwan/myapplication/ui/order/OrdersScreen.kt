package com.wolaitongniwan.myapplication.ui.order

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.ui.components.AutoRefresh
import com.wolaitongniwan.myapplication.ui.components.ListStateBox
import com.wolaitongniwan.myapplication.ui.components.LoadMoreFooter
import com.wolaitongniwan.myapplication.ui.components.LoginRequiredView
import com.wolaitongniwan.myapplication.ui.components.MessageEffect

/**
 * 订单列表。
 *
 * @param asCompanion false = 我下的单（订单 Tab）；true = 我接的单（陪玩端入口）
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrdersScreen(
    onOpenOrder: (Long) -> Unit,
    onOpenLogin: () -> Unit,
    asCompanion: Boolean = false,
    loggedIn: Boolean = true,
    vm: OrdersViewModel = viewModel(key = "orders_$asCompanion") { OrdersViewModel(asCompanion) },
) {
    if (!loggedIn) {
        LoginRequiredView(
            onLogin = onOpenLogin,
            text = if (asCompanion) "登录后即可查看你接到的订单" else "登录后即可查看你的订单",
        )
        return
    }

    val state by vm.state.collectAsStateWithLifecycle()
    val status by vm.status.collectAsStateWithLifecycle()
    val acting by vm.acting.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val listState = rememberLazyListState()

    MessageEffect(vm.messages, snackbar)

    // 每次进入本页（含点击底部 Tab）都重拉一次数据，保证看到的是最新状态
    LaunchedEffect(Unit) { vm.refresh() }

    // 停留在这个页面期间，每 5 秒自动向服务器同步一次；切到后台会自动暂停
    // 订单状态变化最频繁（对方支付、接单、完成），所以这个页面最需要自动刷新
    AutoRefresh { vm.refresh() }

    val shouldLoadMore by remember {
        derivedStateOf {
            val info = listState.layoutInfo
            val last = info.visibleItemsInfo.lastOrNull()?.index ?: -1
            info.totalItemsCount > 0 && last >= info.totalItemsCount - 3
        }
    }
    LaunchedEffect(shouldLoadMore) {
        if (shouldLoadMore) vm.loadMore()
    }

    Column(Modifier.fillMaxSize()) {
        LazyRow(
            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            items(OrderStatusTabs, key = { it.first }) { (value, label) ->
                FilterChip(
                    selected = status == value,
                    onClick = { vm.setStatus(value) },
                    label = { Text(label) },
                )
            }
        }

        Box(Modifier.fillMaxSize()) {
            PullToRefreshBox(
                isRefreshing = state.refreshing,
                onRefresh = vm::refresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                ListStateBox(
                    state = state,
                    emptyText = if (asCompanion) "还没有接到订单，去接单大厅看看吧" else "还没有订单，去点一单试试～",
                    onRetry = vm::refresh,
                ) { list ->
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items(list, key = { it.id }) { order ->
                            OrderCard(
                                order = order,
                                onClick = { onOpenOrder(order.id) },
                                modifier = Modifier.padding(horizontal = 12.dp),
                            ) {
                                OrderQuickActions(
                                    order = order,
                                    busy = acting == order.id,
                                    onPay = { vm.pay(order.id) },
                                    onCancel = { vm.cancel(order.id) },
                                    onConfirm = { vm.confirm(order.id) },
                                    onAccept = { vm.accept(order.id) },
                                    onFinish = { vm.finish(order.id) },
                                )
                            }
                        }
                        item {
                            LoadMoreFooter(
                                loadingMore = state.loadingMore,
                                hasMore = state.hasMore,
                            )
                        }
                    }
                }
            }

            SnackbarHost(
                hostState = snackbar,
                modifier = Modifier.align(Alignment.BottomCenter),
            )
        }
    }
}

/**
 * 列表里的快捷操作。
 * 直接用后端算好的 can_* 控制显隐，客户端不推断状态机：
 * 买家看到「去支付 / 确认完成」，陪玩看到「接单 / 提交完成」。
 */
@Composable
fun OrderQuickActions(
    order: com.wolaitongniwan.myapplication.data.remote.dto.OrderDto,
    busy: Boolean,
    onPay: () -> Unit,
    onCancel: () -> Unit,
    onConfirm: () -> Unit,
    onAccept: () -> Unit,
    onFinish: () -> Unit,
) {
    if (busy) {
        // 后面直接 return，这里加间隔也看不到，所以不画 Spacer
        CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
        return
    }

    if (order.canPay) {
        TextButton(onClick = onPay) { Text("去支付") }
    }
    if (order.canConfirm) {
        TextButton(onClick = onConfirm) { Text("确认完成") }
    }
    if (order.canAccept) {
        TextButton(onClick = onAccept) { Text("接单") }
    }
    if (order.canFinish) {
        TextButton(onClick = onFinish) { Text("提交完成") }
    }
    if (order.canCancel) {
        TextButton(onClick = onCancel) {
            Text("取消", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
