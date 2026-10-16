package com.wolaitongniwan.myapplication.ui.order

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.wolaitongniwan.myapplication.data.remote.dto.OrderDto
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.StatusPill

/**
 * 订单卡片。
 *
 * 对方是谁取决于我在本单里的角色：
 *  - 我是买家 → 显示陪玩
 *  - 我是陪玩 → 显示买家
 *  - 大厅里还没接单的单子 → 显示发布者，并展示「预计收入」
 */
@Composable
fun OrderCard(
    order: OrderDto,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    actions: @Composable RowScope.() -> Unit = {},
) {
    val showAsWork = order.isCompanion || order.canGrab
    val peerName = if (showAsWork) order.userNickname else order.companionNickname
    val peerAvatar = if (showAsWork) order.userAvatarUrl else order.companionAvatarUrl

    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(14.dp)) {
            // 顶行：订单号 + 状态
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = order.orderNo,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.outline,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
                StatusPill(text = order.statusText, statusClass = order.statusClass)
            }

            Spacer(Modifier.height(10.dp))

            // 中间：标题 + 对方信息
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (peerName.isNotBlank() || peerAvatar.isNotBlank()) {
                    NetworkAvatar(url = peerAvatar, name = peerName, size = 40.dp)
                    Spacer(Modifier.width(10.dp))
                }

                Column(Modifier.weight(1f)) {
                    Text(
                        text = order.skillTitle.ifBlank { order.gameName.ifBlank { "陪玩服务" } },
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Medium,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                    if (peerName.isNotBlank()) {
                        Spacer(Modifier.height(2.dp))
                        Text(
                            text = if (showAsWork) "下单人：$peerName" else "陪玩：$peerName",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }

                Spacer(Modifier.width(10.dp))

                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = order.amountText,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.primary,
                    )
                    if (order.quantity > 1) {
                        Text(
                            text = "×${order.quantity}",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }

            if (order.gameName.isNotBlank() || order.createdAt.isNotBlank()) {
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    if (order.gameName.isNotBlank()) {
                        Text(
                            text = order.gameName,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    if (order.createdAt.isNotBlank()) {
                        Text(
                            text = order.createdAt,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.outline,
                        )
                    }
                }
            }

            // 底行：操作按钮（由各页面按 canXxx 决定放哪些）；传空内容时这一行高度为 0
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically,
                content = actions,
            )
        }
    }
}

/** 列表分组标题（按状态分段时用） */
@Composable
fun OrderGroupHeader(text: String) {
    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
    Text(
        text = text,
        style = MaterialTheme.typography.labelSmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
    )
}
