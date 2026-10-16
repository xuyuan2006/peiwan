package com.wolaitongniwan.myapplication.ui.order

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.StarBorder
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
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
import com.wolaitongniwan.myapplication.data.remote.dto.CreateReviewRequest
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.SubmitButton
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** 评分快捷标签（与网页端评价页保持同一批词） */
private val ReviewTags = listOf(
    "技术好", "声音甜", "有耐心", "准时",
    "态度好", "幽默风趣", "配合度高", "会聊天",
)

/** 后端一次最多收 10 个标签 */
private const val MAX_TAGS = 10

data class ReviewFormState(
    val score: Int = 5,
    val content: String = "",
    val selectedTags: Set<String> = emptySet(),
    val anonymous: Boolean = false,
    val submitting: Boolean = false,
)

class ReviewCreateViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _form = MutableStateFlow(ReviewFormState())
    val form: StateFlow<ReviewFormState> = _form.asStateFlow()

    private val _done = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val done: SharedFlow<Unit> = _done

    fun update(transform: (ReviewFormState) -> ReviewFormState) = _form.update(transform)

    fun toggleTag(tag: String) {
        val current = _form.value
        val next = current.selectedTags.toMutableSet()

        if (!next.add(tag)) {
            // 再点一次 = 取消选择
            next.remove(tag)
        } else if (next.size > MAX_TAGS) {
            // 超限就明确拒绝。不能写 next.take(10)：HashSet 迭代顺序不可控，
            // 那样会随机丢掉一个用户已经选好的标签，用户完全摸不着头脑。
            notify("最多选择 $MAX_TAGS 个标签")
            return
        }

        _form.update { it.copy(selectedTags = next) }
    }

    fun submit(orderId: Long) {
        val form = _form.value
        if (form.submitting) return
        if (form.score !in 1..5) {
            notify("请先选择评分")
            return
        }

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (_, msg) = ApiCall.callWithMessage {
                    api.createReview(
                        CreateReviewRequest(
                            orderId = orderId,
                            score = form.score,
                            content = form.content.trim(),
                            tags = form.selectedTags.toList(),
                            isAnonymous = if (form.anonymous) 1 else 0,
                        )
                    )
                }
                notify(msg.ifBlank { "评价已提交，感谢你的反馈" })
                _done.tryEmit(Unit)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }
}

@Composable
fun ReviewCreateScreen(
    orderId: Long,
    onBack: () -> Unit,
    onDone: () -> Unit,
    vm: ReviewCreateViewModel = viewModel(),
) {
    val form by vm.form.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.done.collect { onDone() }
    }

    AppScaffold(
        title = "评价订单",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp),
        ) {
            SectionSpacer(12.dp)

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 18.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text(
                        text = scoreText(form.score),
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Spacer(Modifier.height(10.dp))
                    Row {
                        (1..5).forEach { star ->
                            IconButton(
                                onClick = { vm.update { it.copy(score = star) } },
                                enabled = !form.submitting,
                            ) {
                                Icon(
                                    imageVector = if (star <= form.score) {
                                        Icons.Filled.Star
                                    } else {
                                        // 用 filled 变体：outlined.StarBorder 在部分 icons 版本里不存在，
                                        // filled 的是肯定有的
                                        Icons.Filled.StarBorder
                                    },
                                    contentDescription = "$star 星",
                                    tint = if (star <= form.score) {
                                        Color(0xFFFFB300)
                                    } else {
                                        MaterialTheme.colorScheme.outline
                                    },
                                    modifier = Modifier.size(32.dp),
                                )
                            }
                        }
                    }
                }
            }

            SectionSpacer(16.dp)
            Text("添加标签（可多选）", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(8.dp))
            // 简单的两行流式排布：FilterChip 本身带内边距，用两个 Row 手动分行即可
            ReviewTags.chunked(4).forEach { rowTags ->
                Row(
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier.padding(bottom = 6.dp),
                ) {
                    rowTags.forEach { tag ->
                        FilterChip(
                            selected = tag in form.selectedTags,
                            onClick = { vm.toggleTag(tag) },
                            label = { Text(tag) },
                            enabled = !form.submitting,
                        )
                    }
                }
            }

            SectionSpacer(10.dp)
            OutlinedTextField(
                value = form.content,
                onValueChange = { v -> vm.update { it.copy(content = v.take(500)) } },
                label = { Text("评价内容（选填）") },
                placeholder = { Text("说说这次服务体验怎么样") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 4,
                enabled = !form.submitting,
                supportingText = { Text("${form.content.length}/500") },
            )

            SectionSpacer(12.dp)
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text("匿名评价", style = MaterialTheme.typography.bodyMedium)
                    Text(
                        text = "开启后不会展示你的昵称和头像",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Switch(
                    checked = form.anonymous,
                    onCheckedChange = { v -> vm.update { it.copy(anonymous = v) } },
                    enabled = !form.submitting,
                )
            }

            SectionSpacer(20.dp)
            SubmitButton(
                text = "提交评价",
                onClick = { vm.submit(orderId) },
                loading = form.submitting,
            )
            Spacer(Modifier.height(32.dp))
        }
    }
}

private fun scoreText(score: Int): String = when (score) {
    1 -> "很不满意"
    2 -> "不太满意"
    3 -> "一般"
    4 -> "满意"
    else -> "非常满意"
}
