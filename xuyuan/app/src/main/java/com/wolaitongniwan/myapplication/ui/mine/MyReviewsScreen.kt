package com.wolaitongniwan.myapplication.ui.mine

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.MyReviewsDto
import com.wolaitongniwan.myapplication.data.remote.dto.ReplyReviewRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ReviewDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.TagChip
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class MyReviewsViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<MyReviewsDto>>(UiState.Loading)
    val state: StateFlow<UiState<MyReviewsDto>> = _state.asStateFlow()

    /** 正在回复的评价 ID（0 表示空闲），用于按钮转圈与防重复提交 */
    private val _replying = MutableStateFlow(0L)
    val replying: StateFlow<Long> = _replying.asStateFlow()

    init { load() }

    fun load() {
        viewModelScope.launch {
            try {
                _state.value = UiState.Success(ApiCall.call { api.myReviews(perPage = 50) })
            } catch (e: ApiException) {
                _state.value = UiState.Failure(e.message)
            }
        }
    }

    /** 陪玩回复评价；重复回复会覆盖原回复（与网页端一致） */
    fun reply(reviewId: Long, content: String) {
        val text = content.trim()
        if (text.isEmpty() || _replying.value != 0L) return

        _replying.value = reviewId
        viewModelScope.launch {
            try {
                val msg = ApiCall.message { api.replyReview(ReplyReviewRequest(reviewId, text)) }
                notify(msg.ifBlank { "回复成功" })
                load()
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _replying.value = 0L
            }
        }
    }
}

@Composable
fun MyReviewsScreen(
    onBack: () -> Unit,
    vm: MyReviewsViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val replying by vm.replying.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    var tab by rememberSaveable { mutableStateOf(0) }
    var replyTarget by remember { mutableStateOf<ReviewDto?>(null) }

    MessageEffect(vm.messages, snackbar)

    AppScaffold(title = "我的评价", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = vm::load) { data ->
                Column(Modifier.fillMaxSize()) {
                    Row(
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                    ) {
                        FilterChip(
                            selected = tab == 0,
                            onClick = { tab = 0 },
                            label = { Text("我收到的（${data.received.total}）") },
                        )
                        FilterChip(
                            selected = tab == 1,
                            onClick = { tab = 1 },
                            label = { Text("我发出的（${data.given.total}）") },
                        )
                    }

                    val list = if (tab == 0) data.received.list else data.given.list

                    if (list.isEmpty()) {
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            Text(
                                text = if (tab == 0) "还没有收到评价" else "还没有发出过评价",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    } else {
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(bottom = 16.dp),
                            verticalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            items(list, key = { it.id }) { review ->
                                // 只有「我收到的」才需要回复；
                                // 显式标注类型，避免嵌套 lambda 的解析歧义
                                val replyAction: (() -> Unit)? = if (tab == 0) {
                                    { replyTarget = review }
                                } else {
                                    null
                                }
                                ReviewCard(
                                    review = review,
                                    showReply = tab == 0,
                                    onReply = replyAction,
                                )
                            }
                        }
                    }
                }
            }
            SnackbarHost(snackbar, Modifier.align(Alignment.BottomCenter))
        }
    }

    replyTarget?.let { target ->
        ReplyDialog(
            review = target,
            submitting = replying == target.id,
            onDismiss = { replyTarget = null },
            onConfirm = { content ->
                replyTarget = null
                vm.reply(target.id, content)
            },
        )
    }
}

/** 陪玩回复评价的弹窗 */
@Composable
private fun ReplyDialog(
    review: ReviewDto,
    submitting: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit,
) {
    var content by remember { mutableStateOf(review.reply) }

    AlertDialog(
        onDismissRequest = { if (!submitting) onDismiss() },
        title = { Text("回复评价") },
        text = {
            Column {
                Text(
                    text = review.content.ifBlank { "（对方没有填写评价内容）" },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = content,
                    onValueChange = { if (it.length <= 500) content = it },
                    label = { Text("我的回复") },
                    placeholder = { Text("感谢评价，或者解释一下当时的情况") },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3,
                    enabled = !submitting,
                )
            }
        },
        confirmButton = {
            Button(
                onClick = { onConfirm(content) },
                enabled = content.isNotBlank() && !submitting,
            ) { Text(if (submitting) "提交中…" else "发送") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !submitting) { Text("取消") }
        },
    )
}

@Composable
private fun ReviewCard(
    review: ReviewDto,
    showReply: Boolean,
    onReply: (() -> Unit)?,
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                NetworkAvatar(url = review.avatarUrl, name = review.nickname, size = 32.dp)
                Spacer(Modifier.width(8.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        text = review.nickname.ifBlank { "匿名用户" },
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = FontWeight.Medium,
                    )
                    Text(
                        text = review.createdAt,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                }
                Text(
                    text = "★".repeat(review.score.coerceIn(0, 5)),
                    color = Color(0xFFFFB300),
                    style = MaterialTheme.typography.labelMedium,
                )
            }

            if (review.content.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(text = review.content, style = MaterialTheme.typography.bodySmall)
            }

            if (review.tags.isNotEmpty()) {
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    review.tags.take(5).forEach { TagChip(it) }
                }
            }

            if (showReply && review.reply.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(
                    text = "我的回复：${review.reply}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.primary,
                )
            }

            if (onReply != null) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End,
                ) {
                    TextButton(onClick = onReply) {
                        Text(if (review.reply.isBlank()) "回复" else "修改回复")
                    }
                }
            }
        }
    }
}
