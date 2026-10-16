package com.wolaitongniwan.myapplication.ui.companion

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.background
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionDto
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.TagChip
import com.wolaitongniwan.myapplication.ui.theme.StatusSuccess

/** 在线小绿点 */
@Composable
fun OnlineDot(size: Dp = 8.dp) {
    Spacer(
        Modifier
            .size(size)
            .clip(CircleShape)
            .background(StatusSuccess),
    )
}

/** 评分：一颗星 + 数值 + 接单数 */
@Composable
fun ScoreRow(
    score: Double,
    orderCount: Int,
    reviewCount: Int = 0,
    modifier: Modifier = Modifier,
) {
    Row(modifier = modifier, verticalAlignment = Alignment.CenterVertically) {
        Icon(
            imageVector = Icons.Filled.Star,
            contentDescription = null,
            tint = Color(0xFFFFB300),
            modifier = Modifier.size(14.dp),
        )
        Spacer(Modifier.width(2.dp))
        Text(
            text = String.format("%.1f", score),
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.Medium,
        )
        Spacer(Modifier.width(8.dp))
        Text(
            text = "接单 $orderCount",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        if (reviewCount > 0) {
            Spacer(Modifier.width(8.dp))
            Text(
                text = "评价 $reviewCount",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

/**
 * 陪玩卡片（首页推荐位与列表页共用）。
 * 注意用 Card + clickable 而不是 Card(onClick=)，后者在部分 Material3 版本上还是实验 API。
 */
@Composable
fun CompanionCard(
    item: CompanionDto,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(Modifier.padding(12.dp)) {
            NetworkAvatar(url = item.avatarUrl, name = item.nickname, size = 56.dp)

            Spacer(Modifier.width(12.dp))

            Column(Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = item.nickname.ifBlank { "陪玩" },
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f, fill = false),
                    )
                    Spacer(Modifier.width(6.dp))
                    if (item.online) {
                        OnlineDot()
                    } else {
                        Text(
                            text = "休息中",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.outline,
                        )
                    }
                }

                Spacer(Modifier.height(4.dp))
                ScoreRow(score = item.score, orderCount = item.orderCount, reviewCount = item.reviewCount)

                Spacer(Modifier.height(6.dp))
                Text(
                    text = item.intro.ifBlank { "这位陪玩还没有填写个人介绍" },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )

                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Row(
                        modifier = Modifier.weight(1f),
                        horizontalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        item.tags.take(2).forEach { TagChip(it) }
                        if (item.city.isNotBlank()) {
                            TagChip(item.city)
                        }
                    }
                    MoneyText(text = item.minPriceText, fontSize = 15.sp)
                }
            }
        }
    }
}
