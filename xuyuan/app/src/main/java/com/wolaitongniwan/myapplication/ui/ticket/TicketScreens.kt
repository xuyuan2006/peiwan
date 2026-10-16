package com.wolaitongniwan.myapplication.ui.ticket

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
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.data.remote.dto.TicketDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketReplyDto
import com.wolaitongniwan.myapplication.ui.components.ListStateBox
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.InfoRow
import com.wolaitongniwan.myapplication.ui.components.LoadMoreFooter
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.SubmitButton
import com.wolaitongniwan.myapplication.ui.components.TagChip
import com.wolaitongniwan.myapplication.ui.theme.StatusDanger
import com.wolaitongniwan.myapplication.ui.theme.StatusInfo
import com.wolaitongniwan.myapplication.ui.theme.StatusMuted
import com.wolaitongniwan.myapplication.ui.theme.StatusSuccess
import com.wolaitongniwan.myapplication.ui.theme.StatusWarning

// ---------------------------------------------------------------- 列表

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TicketListScreen(
    onBack: () -> Unit,
    onOpenDetail: (Long) -> Unit,
    onCreate: () -> Unit,
    vm: TicketListViewModel = viewModel(),
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

    AppScaffold(
        title = "我的工单",
        onBack = onBack,
        snackbarHostState = snackbar,
        floatingActionButton = {
            FloatingActionButton(onClick = onCreate) {
                Icon(Icons.Filled.Add, contentDescription = "提交工单")
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            PullToRefreshBox(
                isRefreshing = state.refreshing,
                onRefresh = vm::refresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                ListStateBox(
                    state = state,
                    emptyText = "还没有工单\n遇到问题可以点右下角提交",
                    onRetry = vm::refresh,
                ) { list ->
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 80.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp),
                    ) {
                        items(list, key = { it.id }) { ticket ->
                            TicketRow(ticket) { onOpenDetail(ticket.id) }
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
private fun TicketRow(ticket: TicketDto, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp)
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                TagChip(ticket.categoryText)
                Spacer(Modifier.width(6.dp))
                Spacer(Modifier.weight(1f))
                TagChip(
                    text = ticket.statusText,
                    background = ticketStatusColor(ticket.status).copy(alpha = 0.12f),
                    contentColor = ticketStatusColor(ticket.status),
                )
            }
            Spacer(Modifier.height(8.dp))
            Text(
                text = ticket.title,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Medium,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = ticket.content,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            Spacer(Modifier.height(8.dp))
            Row {
                Text(
                    text = ticket.ticketNo,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
                Spacer(Modifier.weight(1f))
                Text(
                    text = if (ticket.replyCount > 0) "${ticket.replyCount} 条对话" else ticket.createdAt,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
            }
        }
    }
}

// ---------------------------------------------------------------- 详情

@Composable
fun TicketDetailScreen(
    ticketId: Long,
    onBack: () -> Unit,
    vm: TicketDetailViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val sending by vm.sending.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val listState = rememberLazyListState()

    var input by remember { mutableStateOf("") }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(ticketId) { vm.load(ticketId) }

    LaunchedEffect(vm) {
        vm.sent.collect { input = "" }
    }

    AppScaffold(title = "工单详情", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            Box(Modifier.weight(1f)) {
                StateBox(state = state, onRetry = { vm.load(ticketId) }) { data ->
                    val ticket = data.ticket
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(12.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        item {
                            if (ticket != null) {
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                                    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                                ) {
                                    Column(Modifier.padding(14.dp)) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text(
                                                text = ticket.title,
                                                style = MaterialTheme.typography.titleSmall,
                                                fontWeight = FontWeight.SemiBold,
                                                modifier = Modifier.weight(1f),
                                            )
                                            TagChip(
                                                text = ticket.statusText,
                                                background = ticketStatusColor(ticket.status).copy(alpha = 0.12f),
                                                contentColor = ticketStatusColor(ticket.status),
                                            )
                                        }
                                        Spacer(Modifier.height(10.dp))
                                        InfoRow("工单号", ticket.ticketNo)
                                        InfoRow("类型", ticket.categoryText)
                                        if (ticket.orderId > 0) InfoRow("关联订单", "#${ticket.orderId}")
                                        InfoRow("提交时间", ticket.createdAt)

                                        // 状态 2（已解决）本身已经表示问题处理完了，不必再让用户点关闭
                                        if (ticket.status != 3 && ticket.status != 2) {
                                            Spacer(Modifier.height(10.dp))
                                            TextButton(onClick = vm::close, enabled = !sending) {
                                                Text("问题已解决，关闭工单")
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        item {
                            Text(
                                text = "对话记录",
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(top = 4.dp, bottom = 2.dp),
                            )
                        }

                        items(data.thread, key = { it.id }) { reply -> ReplyBubble(reply) }
                    }
                }
            }

            HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)

            val closed = (state as? UiState.Success<TicketDetailDto>)?.data?.ticket?.status == 3

            if (closed) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(MaterialTheme.colorScheme.surface)
                        .navigationBarsPadding()
                        .padding(16.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = "工单已关闭，如需继续沟通请提交新工单",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            } else {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(MaterialTheme.colorScheme.surface)
                        .navigationBarsPadding()
                        .imePadding()
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.Bottom,
                ) {
                    OutlinedTextField(
                        value = input,
                        onValueChange = { if (it.length <= 1000) input = it },
                        modifier = Modifier.weight(1f),
                        placeholder = { Text("补充说明…") },
                        maxLines = 4,
                        enabled = !sending,
                    )
                    Spacer(Modifier.width(6.dp))
                    IconButton(
                        onClick = { vm.reply(input) },
                        enabled = input.isNotBlank() && !sending,
                    ) {
                        if (sending) {
                            CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                        } else {
                            Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "发送")
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ReplyBubble(reply: TicketReplyDto) {
    val isAdmin = reply.senderType == "admin"
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = if (isAdmin) Arrangement.Start else Arrangement.End,
    ) {
        Column(
            horizontalAlignment = if (isAdmin) Alignment.Start else Alignment.End,
            modifier = Modifier.widthIn(max = 300.dp),
        ) {
            Text(
                text = reply.senderName,
                style = MaterialTheme.typography.labelSmall,
                color = if (isAdmin) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp),
            )
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(12.dp))
                    .background(
                        if (isAdmin) MaterialTheme.colorScheme.primaryContainer
                        else MaterialTheme.colorScheme.surfaceVariant
                    )
                    .padding(horizontal = 12.dp, vertical = 8.dp),
            ) {
                Text(
                    text = reply.content,
                    style = MaterialTheme.typography.bodyMedium,
                    color = if (isAdmin) MaterialTheme.colorScheme.onPrimaryContainer
                    else MaterialTheme.colorScheme.onSurface,
                )
            }
            Text(
                text = reply.createdAt,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
                modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp),
            )
        }
    }
}

// ---------------------------------------------------------------- 提交

@Composable
fun TicketCreateScreen(
    orderId: Long,
    onBack: () -> Unit,
    onCreated: (Long) -> Unit,
    vm: TicketCreateViewModel = viewModel(),
) {
    val categories by vm.categories.collectAsStateWithLifecycle()
    val form by vm.form.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.created.collect { newId -> onCreated(newId) }
    }

    AppScaffold(title = "提交工单", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp),
        ) {
            SectionSpacer(12.dp)

            if (orderId > 0) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                ) {
                    Text(
                        text = "本工单会关联订单 #$orderId，客服可直接查看订单情况",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(12.dp),
                    )
                }
                SectionSpacer(12.dp)
            }

            Text("问题类型", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(8.dp))
            categories.list.forEach { category ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { vm.setCategory(category.key) }
                        .padding(vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    FilterChip(
                        selected = form.category == category.key,
                        onClick = { vm.setCategory(category.key) },
                        label = { Text(category.name) },
                        enabled = !form.submitting,
                    )
                    if (category.hint.isNotBlank()) {
                        Spacer(Modifier.width(8.dp))
                        Text(
                            text = category.hint,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                }
            }

            SectionSpacer(12.dp)
            AppTextField(
                value = form.title,
                onValueChange = vm::setTitle,
                label = "标题",
                placeholder = "一句话说明问题，例如：订单支付后陪玩一直没接单",
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            OutlinedTextField(
                value = form.content,
                onValueChange = vm::setContent,
                label = { Text("详细描述") },
                placeholder = { Text("请说明订单号、发生时间、你期望的处理结果") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 6,
                enabled = !form.submitting,
                supportingText = { Text("${form.content.length}/1000，至少 5 个字") },
            )

            SectionSpacer(20.dp)
            SubmitButton(
                text = "提交工单",
                onClick = { vm.submit(orderId) },
                loading = form.submitting,
                enabled = form.title.isNotBlank() && form.content.trim().length >= 5,
            )
            Spacer(Modifier.height(32.dp))
        }
    }
}

private fun ticketStatusColor(status: Int) = when (status) {
    0 -> StatusWarning
    1 -> StatusInfo
    2 -> StatusSuccess
    else -> StatusMuted
}
