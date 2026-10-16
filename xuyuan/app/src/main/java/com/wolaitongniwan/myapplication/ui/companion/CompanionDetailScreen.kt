package com.wolaitongniwan.myapplication.ui.companion

import androidx.compose.foundation.background
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.ReviewDto
import com.wolaitongniwan.myapplication.data.remote.dto.SkillDto
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.SectionTitle
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.TagChip
import com.wolaitongniwan.myapplication.ui.theme.StatusSuccess

@Composable
fun CompanionDetailScreen(
    companionId: Long,
    onBack: () -> Unit,
    onOpenOrder: (Long) -> Unit,
    onNeedLogin: () -> Unit,
    loggedIn: Boolean,
    vm: CompanionDetailViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val ordering by vm.ordering.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    // 待下单的服务项目（null 表示没打开下单弹窗）
    var orderingSkill by remember { mutableStateOf<SkillDto?>(null) }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(companionId) { vm.load(companionId) }

    LaunchedEffect(vm) {
        vm.orderCreated.collect { orderId -> onOpenOrder(orderId) }
    }

    AppScaffold(
        title = "陪玩主页",
        onBack = onBack,
        snackbarHostState = snackbar,
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = { vm.load(companionId) }) { data ->
                CompanionDetailContent(
                    data = data,
                    ordering = ordering,
                    onOrder = { skill ->
                        if (!loggedIn) onNeedLogin() else orderingSkill = skill
                    },
                )
            }
        }
    }

    orderingSkill?.let { skill ->
        OrderDialog(
            skill = skill,
            submitting = ordering,
            onDismiss = { orderingSkill = null },
            onConfirm = { quantity, remark ->
                orderingSkill = null
                vm.createOrder(skill.id, quantity, remark)
            },
        )
    }
}

@Composable
private fun CompanionDetailContent(
    data: CompanionDetailDto,
    ordering: Boolean,
    onOrder: (SkillDto) -> Unit,
) {
    val profile = data.profile

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 24.dp),
    ) {
        // ---------------- 头部 ----------------
        item {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(140.dp)
                    .background(MaterialTheme.colorScheme.primaryContainer),
            ) {
                if (!profile?.coverUrl.isNullOrBlank()) {
                    AsyncImage(
                        model = profile?.coverUrl,
                        contentDescription = null,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize(),
                    )
                }
            }
        }

        item {
            Column(Modifier.padding(horizontal = 16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    NetworkAvatar(
                        url = profile?.avatarUrl,
                        name = profile?.nickname.orEmpty(),
                        size = 64.dp,
                        modifier = Modifier.padding(top = 0.dp),
                    )
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = profile?.nickname.orEmpty().ifBlank { "陪玩" },
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                            Spacer(Modifier.width(6.dp))
                            if (profile?.online == true) {
                                TagChip(
                                    text = "接单中",
                                    background = StatusSuccess.copy(alpha = 0.14f),
                                    contentColor = StatusSuccess,
                                )
                            }
                        }
                        Spacer(Modifier.height(4.dp))
                        ScoreRow(
                            score = profile?.score ?: 5.0,
                            orderCount = profile?.orderCount ?: 0,
                            reviewCount = profile?.reviewCount ?: 0,
                        )
                        if (!profile?.city.isNullOrBlank()) {
                            Spacer(Modifier.height(4.dp))
                            Text(
                                text = profile?.city.orEmpty(),
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }

                if (!profile?.tags.isNullOrEmpty()) {
                    Spacer(Modifier.height(10.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        profile?.tags?.take(5)?.forEach { TagChip(it) }
                    }
                }

                if (!profile?.intro.isNullOrBlank()) {
                    Spacer(Modifier.height(10.dp))
                    Text(
                        text = profile?.intro.orEmpty(),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }

                Spacer(Modifier.height(12.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(20.dp)) {
                    Text(
                        text = "接单 ${data.stats.completed}/${data.stats.total}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(
                        text = "好评率 ${String.format("%.0f", profile?.goodRate ?: 100.0)}%",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }

        // ---------------- 服务项目 ----------------
        item { SectionTitle(title = "服务项目（${data.skills.size}）") }

        if (data.skills.isEmpty()) {
            item {
                Text(
                    text = "这位陪玩还没有上架服务项目",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
        } else {
            items(data.skills, key = { it.id }) { skill ->
                SkillRow(
                    skill = skill,
                    busy = ordering,
                    onOrder = { onOrder(skill) },
                )
            }
        }

        // ---------------- 评价 ----------------
        item { SectionTitle(title = "用户评价（${data.reviews.size}）") }

        if (data.reviews.isEmpty()) {
            item {
                Text(
                    text = "还没有评价",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                )
            }
        } else {
            items(data.reviews, key = { it.id }) { review -> ReviewRow(review) }
        }
    }
}

@Composable
private fun SkillRow(skill: SkillDto, busy: Boolean, onOrder: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 4.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = skill.title,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Spacer(Modifier.height(4.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    if (skill.gameName.isNotBlank()) TagChip(skill.gameName)
                    TagChip(skill.serviceTypeText)
                    if (skill.rankLevel.isNotBlank()) TagChip(skill.rankLevel)
                }
                if (skill.description.isNotBlank()) {
                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = skill.description,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.Bottom) {
                    MoneyText(text = skill.priceText, fontSize = 16.sp)
                    Spacer(Modifier.width(3.dp))
                    Text(
                        text = "/ ${skill.unit}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }

            Spacer(Modifier.width(10.dp))
            Button(onClick = onOrder, enabled = !busy) { Text("立即下单") }
        }
    }
}

@Composable
private fun ReviewRow(review: ReviewDto) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            NetworkAvatar(url = review.avatarUrl, name = review.nickname, size = 32.dp)
            Spacer(Modifier.width(8.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    text = review.nickname.ifBlank { "匿名用户" },
                    style = MaterialTheme.typography.labelMedium,
                )
                Text(
                    text = review.createdAt,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                )
            }
            Text(
                text = "★".repeat(review.score.coerceIn(0, 5)),
                color = androidx.compose.ui.graphics.Color(0xFFFFB300),
                style = MaterialTheme.typography.labelMedium,
            )
        }
        if (review.content.isNotBlank()) {
            Spacer(Modifier.height(6.dp))
            Text(text = review.content, style = MaterialTheme.typography.bodySmall)
        }
        if (review.reply.isNotBlank()) {
            Spacer(Modifier.height(6.dp))
            Box(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(8.dp))
                    .background(MaterialTheme.colorScheme.surfaceVariant)
                    .padding(10.dp),
            ) {
                Text(
                    text = "陪玩回复：${review.reply}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        Spacer(Modifier.height(8.dp))
        HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
    }
}

/**
 * 下单弹窗：数量 + 备注。
 * 一口价（custom）服务数量固定为 1，与后端行为保持一致。
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun OrderDialog(
    skill: SkillDto,
    submitting: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (quantity: Int, remark: String) -> Unit,
) {
    val isCustom = skill.serviceType == "custom"
    var quantity by remember { mutableIntStateOf(1) }
    var remark by remember { mutableStateOf("") }

    val total = skill.price * if (isCustom) 1 else quantity

    AlertDialog(
        onDismissRequest = { if (!submitting) onDismiss() },
        title = { Text(skill.title, maxLines = 2, overflow = TextOverflow.Ellipsis) },
        text = {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("单价", style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
                    Text("${skill.priceText} / ${skill.unit}", style = MaterialTheme.typography.bodyMedium)
                }

                if (!isCustom) {
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("数量", style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
                        IconButton(
                            onClick = { if (quantity > 1) quantity-- },
                            enabled = quantity > 1 && !submitting,
                        ) { Text("－") }
                        Text(
                            text = "$quantity",
                            style = MaterialTheme.typography.titleMedium,
                            modifier = Modifier.padding(horizontal = 8.dp),
                        )
                        IconButton(
                            onClick = { if (quantity < 99) quantity++ },
                            enabled = quantity < 99 && !submitting,
                        ) { Text("＋") }
                    }
                }

                Spacer(Modifier.height(12.dp))
                OutlinedTextField(
                    value = remark,
                    onValueChange = { if (it.length <= 200) remark = it },
                    label = { Text("备注（选填）") },
                    placeholder = { Text("期望时间、位置、英雄等") },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 2,
                    enabled = !submitting,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text),
                )

                Spacer(Modifier.height(12.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("合计", style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
                    MoneyText(text = "¥" + String.format("%.2f", total), fontSize = 18.sp)
                }
            }
        },
        confirmButton = {
            Button(
                onClick = { onConfirm(if (isCustom) 1 else quantity, remark.trim()) },
                enabled = !submitting,
            ) { Text(if (submitting) "提交中…" else "确认下单") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !submitting) { Text("取消") }
        },
    )
}
