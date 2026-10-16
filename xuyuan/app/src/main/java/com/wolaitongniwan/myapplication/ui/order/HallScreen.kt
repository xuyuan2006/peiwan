package com.wolaitongniwan.myapplication.ui.order

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
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
 * 接单大厅（仅已通过审核的陪玩可见，入口在底部导航）。
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HallScreen(
    onOpenOrder: (Long) -> Unit,
    onOpenLogin: () -> Unit,
    onOpenApply: () -> Unit,
    canWork: Boolean = true,
    loggedIn: Boolean = true,
    vm: HallViewModel = viewModel(),
) {
    if (!loggedIn) {
        LoginRequiredView(onLogin = onOpenLogin, text = "登录后即可进入接单大厅")
        return
    }
    if (!canWork) {
        LoginRequiredView(
            onLogin = onOpenApply,
            text = "你还不是已通过审核的陪玩\n先提交入驻申请，通过后就能在这里抢单",
        )
        return
    }

    val state by vm.state.collectAsStateWithLifecycle()
    val games by vm.games.collectAsStateWithLifecycle()
    val selectedGame by vm.gameId.collectAsStateWithLifecycle()
    val grabbing by vm.grabbing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val listState = rememberLazyListState()

    MessageEffect(vm.messages, snackbar)

    // 每次进入本页（含点击底部 Tab）都重拉一次数据，保证看到的是最新状态
    LaunchedEffect(Unit) { vm.refresh() }

    // 停留在这个页面期间，每 5 秒自动向服务器同步一次；切到后台会自动暂停
    // 接单大厅尤其需要：新订单随时可能出现，等用户手动刷新就晚了
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
            item {
                FilterChip(
                    selected = selectedGame == 0L,
                    onClick = { vm.setGame(0) },
                    label = { Text("全部游戏") },
                )
            }
            items(games, key = { it.id }) { game ->
                FilterChip(
                    selected = selectedGame == game.id,
                    onClick = { vm.setGame(game.id) },
                    label = { Text(game.name) },
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
                    emptyText = "大厅暂时没有可接的订单\n有新单子会第一时间出现在这里",
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
                                if (grabbing == order.id) {
                                    CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                                } else if (order.canGrab) {
                                    Button(onClick = { vm.grab(order.id) }) {
                                        Text("立即接单")
                                    }
                                } else {
                                    Text(
                                        text = "已被接走",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = MaterialTheme.colorScheme.outline,
                                    )
                                }
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
