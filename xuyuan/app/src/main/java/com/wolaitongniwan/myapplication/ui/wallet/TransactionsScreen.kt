package com.wolaitongniwan.myapplication.ui.wallet

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.data.remote.dto.TransactionDto
import com.wolaitongniwan.myapplication.ui.components.ListStateBox
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.LoadMoreFooter
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.theme.StatusDanger
import com.wolaitongniwan.myapplication.ui.theme.StatusSuccess

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TransactionsScreen(
    onBack: () -> Unit,
    vm: TransactionsViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val wallet by vm.wallet.collectAsStateWithLifecycle()
    val typeOptions by vm.typeOptions.collectAsStateWithLifecycle()
    val type by vm.type.collectAsStateWithLifecycle()
    val direction by vm.direction.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val listState = rememberLazyListState()

    MessageEffect(vm.messages, snackbar)

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

    AppScaffold(
        title = "资金流水",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            // 余额小结
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("当前余额", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.weight(1f))
                Text(
                    text = "¥${wallet.balanceText}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                )
            }

            // 收支方向
            Row(
                modifier = Modifier.padding(horizontal = 12.dp),
                horizontalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                listOf(0 to "全部", 1 to "收入", 2 to "支出").forEach { (value, label) ->
                    FilterChip(
                        selected = direction == value,
                        onClick = { vm.setDirection(value) },
                        label = { Text(label) },
                    )
                }
            }

            // 类型筛选
            if (typeOptions.isNotEmpty()) {
                LazyRow(
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    item {
                        FilterChip(
                            selected = type.isEmpty(),
                            onClick = { vm.setType("") },
                            label = { Text("全部类型") },
                        )
                    }
                    items(typeOptions, key = { it.key }) { option ->
                        FilterChip(
                            selected = type == option.key,
                            onClick = { vm.setType(option.key) },
                            label = { Text(option.name) },
                        )
                    }
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
                        emptyText = "还没有资金流水",
                        onRetry = vm::refresh,
                    ) { list ->
                        LazyColumn(
                            state = listState,
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(bottom = 16.dp),
                        ) {
                            items(list, key = { it.id }) { tx -> TransactionRow(tx) }
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
}

@Composable
private fun TransactionRow(tx: TransactionDto) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 3.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = tx.typeText.ifBlank { tx.remark.ifBlank { "账户变动" } },
                    style = MaterialTheme.typography.bodyMedium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                if (tx.remark.isNotBlank() && tx.remark != tx.typeText) {
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = tx.remark,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    text = tx.createdAt,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
            }

            Column(horizontalAlignment = Alignment.End) {
                Text(
                    text = tx.amountText,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = if (tx.direction == 1) StatusSuccess else StatusDanger,
                )
                Text(
                    text = "余额 ${String.format("%.2f", tx.balanceAfter)}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
            }
        }
    }
    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
}
