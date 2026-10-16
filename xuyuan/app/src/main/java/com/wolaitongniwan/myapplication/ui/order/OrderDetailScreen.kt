package com.wolaitongniwan.myapplication.ui.order

import androidx.compose.foundation.background
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.data.remote.dto.OrderDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderLogDto
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.InfoRow
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.StateBox

@Composable
fun OrderDetailScreen(
    orderId: Long,
    onBack: () -> Unit,
    onOpenChat: (Long) -> Unit,
    onOpenCompanion: (Long) -> Unit,
    onOpenReview: (Long) -> Unit,
    onOpenTicket: (Long) -> Unit,
    vm: OrderDetailViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val acting by vm.acting.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    // 弹窗状态
    var refunding by remember { mutableStateOf(false) }
    var cancelling by remember { mutableStateOf(false) }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(orderId) { vm.load(orderId) }

    AppScaffold(
        title = "订单详情",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = { vm.load(orderId) }) { data ->
                OrderDetailContent(
                    data = data,
                    acting = acting,
                    onOpenChat = { onOpenChat(orderId) },
                    onOpenCompanion = onOpenCompanion,
                    onOpenTicket = { onOpenTicket(orderId) },
                    onOpenReview = { onOpenReview(orderId) },
                    onPay = vm::pay,
                    onConfirm = vm::confirm,
                    onAccept = vm::accept,
                    onFinish = vm::finish,
                    onCancel = { cancelling = true },
                    onRefund = { refunding = true },
                )
            }
        }
    }

    if (cancelling) {
        ReasonDialog(
            title = "取消订单",
            label = "取消原因（选填）",
            placeholder = "例如：临时有事，不需要了",
            required = false,
            submitting = acting,
            onDismiss = { cancelling = false },
            onConfirm = { reason ->
                cancelling = false
                vm.cancel(reason)
            },
        )
    }

    if (refunding) {
        ReasonDialog(
            title = "申请退款",
            label = "退款原因",
            placeholder = "请说明遇到的问题，便于平台判断",
            required = true,
            submitting = acting,
            onDismiss = { refunding = false },
            onConfirm = { reason ->
                refunding = false
                vm.refund(reason)
            },
        )
    }
}

// ---------------------------------------------------------------- 内容

@Composable
private fun OrderDetailContent(
    data: OrderDetailDto,
    acting: Boolean,
    onOpenChat: () -> Unit,
    onOpenCompanion: (Long) -> Unit,
    onOpenTicket: () -> Unit,
    onOpenReview: () -> Unit,
    onPay: () -> Unit,
    onConfirm: () -> Unit,
    onAccept: () -> Unit,
    onFinish: () -> Unit,
    onCancel: () -> Unit,
    onRefund: () -> Unit,
) {
    val order = data.order ?: return

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 24.dp),
    ) {
        // ---------------- 状态 + 金额 ----------------
        item {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primary),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = order.statusText,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = androidx.compose.ui.graphics.Color.White,
                            modifier = Modifier.weight(1f),
                        )
                        Text(
                            text = order.orderNo,
                            style = MaterialTheme.typography.labelSmall,
                            color = androidx.compose.ui.graphics.Color.White.copy(alpha = 0.8f),
                        )
                    }
                    Spacer(Modifier.height(10.dp))
                    Text(
                        text = order.amountText,
                        fontSize = 30.sp,
                        fontWeight = FontWeight.Bold,
                        color = androidx.compose.ui.graphics.Color.White,
                    )
                    if (order.status == 1 && order.expireAt.isNotBlank()) {
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = "请在 ${order.expireAt} 前完成支付，超时自动关闭",
                            style = MaterialTheme.typography.labelSmall,
                            color = androidx.compose.ui.graphics.Color.White.copy(alpha = 0.85f),
                        )
                    }
                }
            }
        }

        // ---------------- 操作按钮 ----------------
        item {
            OrderActionBar(
                data = data,
                acting = acting,
                onPay = onPay,
                onConfirm = onConfirm,
                onAccept = onAccept,
                onFinish = onFinish,
                onCancel = onCancel,
                onRefund = onRefund,
                onOpenChat = onOpenChat,
                onOpenReview = onOpenReview,
                onOpenTicket = onOpenTicket,
            )
        }

        // ---------------- 服务信息 ----------------
        item {
            SectionCard(title = "服务信息") {
                InfoRow("游戏", order.gameName.ifBlank { "—" })
                InfoRow("服务项目", order.skillTitle.ifBlank { "—" })
                InfoRow("单价", "${order.unitPriceText} × ${order.quantity}")
                InfoRow("订单金额", order.amountText)
                if (order.platformFee > 0) {
                    InfoRow("平台服务费", String.format("¥%.2f", order.platformFee))
                    InfoRow("陪玩实收", String.format("¥%.2f", order.companionIncome))
                }
                if (order.userRemark.isNotBlank()) {
                    InfoRow("我的备注", order.userRemark)
                }
                InfoRow("下单时间", order.createdAt.ifBlank { "—" })
                if (order.payTime.isNotBlank()) InfoRow("支付时间", order.payTime)
                if (order.finishTime.isNotBlank()) InfoRow("完成时间", order.finishTime)
                if (order.cancelReason.isNotBlank()) InfoRow("关闭原因", order.cancelReason)
                if (order.refundReason.isNotBlank()) InfoRow("退款原因", order.refundReason)
                if (order.refundRemark.isNotBlank()) InfoRow("平台备注", order.refundRemark)
            }
        }

        // ---------------- 对方信息 ----------------
        item {
            val isWork = order.isCompanion || order.canGrab
            val peerId = if (isWork) order.userId else order.companionId
            val peerName = if (isWork) order.userNickname else order.companionNickname
            val peerAvatar = if (isWork) order.userAvatarUrl else order.companionAvatarUrl

            if (peerId > 0) {
                SectionCard(title = if (isWork) "下单人" else "接单陪玩") {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable(enabled = !isWork) { onOpenCompanion(peerId) }
                            .padding(vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        NetworkAvatar(url = peerAvatar, name = peerName, size = 44.dp)
                        Spacer(Modifier.width(10.dp))
                        Text(
                            text = peerName.ifBlank { "用户$peerId" },
                            style = MaterialTheme.typography.titleSmall,
                            modifier = Modifier.weight(1f),
                        )
                        if (!isWork) {
                            Text(
                                text = "查看主页 ›",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.primary,
                            )
                        }
                    }
                }
            }
        }

        // ---------------- 状态时间线 ----------------
        if (data.logs.isNotEmpty()) {
            item {
                SectionCard(title = "订单进度") {
                    data.logs.forEachIndexed { index, log ->
                        TimelineRow(log = log, isLast = index == data.logs.lastIndex)
                    }
                }
            }
        }
    }
}

@Composable
private fun OrderActionBar(
    data: OrderDetailDto,
    acting: Boolean,
    onPay: () -> Unit,
    onConfirm: () -> Unit,
    onAccept: () -> Unit,
    onFinish: () -> Unit,
    onCancel: () -> Unit,
    onRefund: () -> Unit,
    onOpenChat: () -> Unit,
    onOpenReview: () -> Unit,
    onOpenTicket: () -> Unit,
) {
    val order = data.order ?: return

    // 把所有可用操作收集起来，一个都没有就整块不显示
    Column(Modifier.padding(horizontal = 12.dp)) {
        if (acting) {
            Row(
                modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp)
                Spacer(Modifier.width(8.dp))
                Text("处理中…", style = MaterialTheme.typography.bodySmall)
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            // 买家侧
            if (order.canPay) {
                Button(onClick = onPay, enabled = !acting, modifier = Modifier.weight(1f)) { Text("立即支付") }
            }
            if (order.canConfirm) {
                Button(onClick = onConfirm, enabled = !acting, modifier = Modifier.weight(1f)) { Text("确认完成") }
            }
            if (order.canReview) {
                Button(onClick = onOpenReview, enabled = !acting, modifier = Modifier.weight(1f)) { Text("评价") }
            }

            // 陪玩侧
            if (order.canAccept) {
                Button(onClick = onAccept, enabled = !acting, modifier = Modifier.weight(1f)) { Text("接单") }
            }
            if (order.canFinish) {
                Button(onClick = onFinish, enabled = !acting, modifier = Modifier.weight(1f)) { Text("提交完成") }
            }

            if (order.canChat) {
                OutlinedButton(onClick = onOpenChat, modifier = Modifier.weight(1f)) {
                    Text(if (data.unread > 0) "沟通 (${data.unread})" else "联系对方")
                }
            }
        }

        // 第二行：退款 / 取消 / 工单。
        // 「提交工单」必须无条件显示 —— 它是订单页里唯一的求助入口，
        // 一旦放进 canRefund/canCancel/canChat 的条件里，订单完成后用户就找不到它了。
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            if (order.canRefund) {
                OutlinedButton(onClick = onRefund, enabled = !acting, modifier = Modifier.weight(1f)) {
                    Text("申请退款")
                }
            }
            if (order.canCancel) {
                OutlinedButton(onClick = onCancel, enabled = !acting, modifier = Modifier.weight(1f)) {
                    Text("取消订单")
                }
            }
            OutlinedButton(onClick = onOpenTicket, modifier = Modifier.weight(1f)) {
                Text("提交工单")
            }
        }
    }
}

@Composable
private fun SectionCard(title: String, content: @Composable () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 6.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(14.dp)) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
            )
            Spacer(Modifier.height(8.dp))
            content()
        }
    }
}

/** 一条状态流转记录 */
@Composable
private fun TimelineRow(log: OrderLogDto, isLast: Boolean) {
    Row(modifier = Modifier.fillMaxWidth()) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Box(
                Modifier
                    .size(9.dp)
                    .clip(CircleShape)
                    .background(
                        if (isLast) MaterialTheme.colorScheme.primary
                        else MaterialTheme.colorScheme.outline
                    ),
            )
            if (!isLast) {
                Box(
                    Modifier
                        .width(1.dp)
                        .height(34.dp)
                        .background(MaterialTheme.colorScheme.outlineVariant),
                )
            }
        }
        Spacer(Modifier.width(10.dp))
        Column(Modifier.padding(bottom = if (isLast) 0.dp else 6.dp)) {
            Text(
                text = log.remark.ifBlank { log.toText },
                style = MaterialTheme.typography.bodySmall,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = log.createdAt,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
            )
        }
    }
}

/** 需要填写原因的弹窗（取消订单 / 申请退款共用） */
@Composable
private fun ReasonDialog(
    title: String,
    label: String,
    placeholder: String,
    required: Boolean,
    submitting: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit,
) {
    var reason by remember { mutableStateOf("") }
    val valid = !required || reason.isNotBlank()

    AlertDialog(
        onDismissRequest = { if (!submitting) onDismiss() },
        title = { Text(title) },
        text = {
            Column {
                if (required) {
                    Text(
                        text = "退款需要说明原因，平台会据此审核",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Spacer(Modifier.height(8.dp))
                }
                OutlinedTextField(
                    value = reason,
                    onValueChange = { if (it.length <= 200) reason = it },
                    label = { Text(label) },
                    placeholder = { Text(placeholder) },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3,
                    enabled = !submitting,
                )
            }
        },
        confirmButton = {
            Button(
                onClick = { onConfirm(reason.trim()) },
                enabled = valid && !submitting,
            ) { Text(if (submitting) "提交中…" else "确定") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !submitting) { Text("再想想") }
        },
    )
}
