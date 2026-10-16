package com.wolaitongniwan.myapplication.ui.wallet

import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
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
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeDto
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawDto
import com.wolaitongniwan.myapplication.ui.components.ListStateBox
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.InfoRow
import com.wolaitongniwan.myapplication.ui.components.LoadMoreFooter
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.TagChip
import com.wolaitongniwan.myapplication.ui.theme.StatusDanger
import com.wolaitongniwan.myapplication.ui.theme.StatusInfo
import com.wolaitongniwan.myapplication.ui.theme.StatusMuted
import com.wolaitongniwan.myapplication.ui.theme.StatusSuccess
import com.wolaitongniwan.myapplication.ui.theme.StatusWarning

// ---------------------------------------------------------------- 充值记录

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RechargeListScreen(
    onBack: () -> Unit,
    onOpenDetail: (Long) -> Unit,
    vm: RechargeListViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
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
    LaunchedEffect(shouldLoadMore) { if (shouldLoadMore) vm.loadMore() }

    AppScaffold(title = "充值记录", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            PullToRefreshBox(
                isRefreshing = state.refreshing,
                onRefresh = vm::refresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                ListStateBox(state = state, emptyText = "还没有充值记录", onRetry = vm::refresh) { list ->
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 16.dp),
                    ) {
                        items(list, key = { it.id }) { item ->
                            RechargeRow(item) { onOpenDetail(item.id) }
                        }
                        item { LoadMoreFooter(state.loadingMore, state.hasMore) }
                    }
                }
            }
            SnackbarHost(snackbar, Modifier.align(Alignment.BottomCenter))
        }
    }
}

@Composable
private fun RechargeRow(item: RechargeDto, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 3.dp)
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = item.orderNo,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
                Spacer(Modifier.height(4.dp))
                Text(
                    text = if (item.channel == "manual") "人工转账" else "在线支付",
                    style = MaterialTheme.typography.bodyMedium,
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = item.createdAt,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                MoneyText(text = item.amountText, fontSize = 16.sp)
                Spacer(Modifier.height(4.dp))
                TagChip(
                    text = item.statusText,
                    background = rechargeStatusColor(item.status).copy(alpha = 0.12f),
                    contentColor = rechargeStatusColor(item.status),
                )
            }
        }
    }
}

private fun rechargeStatusColor(status: Int) = when (status) {
    1 -> StatusSuccess
    3 -> StatusDanger
    2 -> StatusMuted
    else -> StatusWarning
}

// ---------------------------------------------------------------- 充值单详情

@Composable
fun RechargeDetailScreen(
    rechargeId: Long,
    onBack: () -> Unit,
    vm: RechargeDetailViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val payResult by vm.payResult.collectAsStateWithLifecycle()
    val paying by vm.paying.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val context = LocalContext.current

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(rechargeId) { vm.load(rechargeId) }

    LaunchedEffect(payResult) {
        val res = payResult ?: return@LaunchedEffect
        if (handlePayRedirect(context, res)) {
            vm.consumePayResult()
            vm.load(rechargeId)
        }
    }

    AppScaffold(title = "充值单详情", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = { vm.load(rechargeId) }) { item ->
                Column(Modifier.fillMaxSize().padding(12.dp)) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                    ) {
                        Column(Modifier.padding(16.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    text = item.statusText,
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold,
                                    color = rechargeStatusColor(item.status),
                                    modifier = Modifier.weight(1f),
                                )
                                MoneyText(text = item.amountText, fontSize = 22.sp)
                            }
                            Spacer(Modifier.height(12.dp))
                            InfoRow("充值单号", item.orderNo)
                            InfoRow("充值渠道", if (item.channel == "manual") "人工转账" else "在线支付")
                            InfoRow("提交时间", item.createdAt.ifBlank { "—" })
                            if (item.paidAt.isNotBlank()) InfoRow("到账时间", item.paidAt)
                            if (item.tradeNo.isNotBlank()) InfoRow("第三方流水号", item.tradeNo)
                            if (item.remark.isNotBlank()) InfoRow("备注", item.remark)
                            if (item.auditRemark.isNotBlank()) InfoRow("平台备注", item.auditRemark)
                        }
                    }

                    // 待支付且是在线渠道 → 允许继续支付
                    if (item.status == 0 && item.channel != "manual") {
                        Spacer(Modifier.height(16.dp))
                        Button(
                            onClick = vm::continuePay,
                            enabled = !paying,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text(if (paying) "获取支付链接中…" else "继续支付")
                        }
                    }

                    // 待支付时允许取消（人工转账也走这里：用户可能反悔不想充了）。
                    // 口径和后端一致 —— 后端只允许取消 status=0 且属于自己的单，
                    // 已到账/已关闭的点了会被拒绝并提示。
                    if (item.status == 0) {
                        Spacer(Modifier.height(10.dp))
                        Button(
                            onClick = vm::cancel,
                            enabled = !paying,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text("取消订单")
                        }
                    }

                    if (item.payVoucherUrl.isNotBlank()) {
                        Spacer(Modifier.height(16.dp))
                        Text("转账凭证", style = MaterialTheme.typography.bodyMedium)
                        Spacer(Modifier.height(8.dp))
                        AsyncImage(
                            model = item.payVoucherUrl,
                            contentDescription = "转账凭证",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(240.dp),
                        )
                    }

                    Spacer(Modifier.height(12.dp))
                    Text(
                        text = "在线支付以服务端回调为准；人工转账需客服核对到账后入账。",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                }
            }
            SnackbarHost(snackbar, Modifier.align(Alignment.BottomCenter))
        }
    }

    val qrResult = payResult?.takeIf { it.payMode == "qrcode" }
    if (qrResult != null) {
        QrPayDialog(
            result = qrResult,
            onDismiss = {
                vm.consumePayResult()
                vm.load(rechargeId)
            },
        )
    }
}

// ---------------------------------------------------------------- 提现记录

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WithdrawListScreen(
    onBack: () -> Unit,
    vm: WithdrawListViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
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
    LaunchedEffect(shouldLoadMore) { if (shouldLoadMore) vm.loadMore() }

    AppScaffold(title = "提现记录", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            PullToRefreshBox(
                isRefreshing = state.refreshing,
                onRefresh = vm::refresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                ListStateBox(state = state, emptyText = "还没有提现记录", onRetry = vm::refresh) { list ->
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 16.dp),
                    ) {
                        items(list, key = { it.id }) { item -> WithdrawRow(item) }
                        item { LoadMoreFooter(state.loadingMore, state.hasMore) }
                    }
                }
            }
            SnackbarHost(snackbar, Modifier.align(Alignment.BottomCenter))
        }
    }
}

@Composable
private fun WithdrawRow(item: WithdrawDto) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 3.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = when (item.accountType) {
                        "alipay" -> "支付宝"
                        "wechat" -> "微信"
                        else -> "银行卡"
                    },
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.weight(1f),
                )
                MoneyText(text = item.amountText, fontSize = 16.sp)
                Spacer(Modifier.width(8.dp))
                TagChip(
                    text = item.statusText,
                    background = withdrawStatusColor(item.status).copy(alpha = 0.12f),
                    contentColor = withdrawStatusColor(item.status),
                )
            }
            Spacer(Modifier.height(6.dp))
            Text(
                text = "收款人 ${item.accountName} · ${maskAccount(item.accountNo)}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = "手续费 ${String.format("%.2f", item.fee)} · 实际到账 ${String.format("%.2f", item.realAmount)}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = item.createdAt,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
            )
            if (item.auditRemark.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = "平台备注：${item.auditRemark}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.error,
                )
            }
        }
    }
}

private fun withdrawStatusColor(status: Int) = when (status) {
    1 -> StatusSuccess
    2 -> StatusDanger
    else -> StatusInfo
}

/** 账号脱敏展示 */
private fun maskAccount(account: String): String {
    if (account.length <= 4) return account
    return account.take(3) + "****" + account.takeLast(4)
}
