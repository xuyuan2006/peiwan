package com.wolaitongniwan.myapplication.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.ui.theme.AvatarPalette

/**
 * 圆形头像。
 *
 * 后端头像为空时返回空串（不会给 SVG data URI），所以这里自己画首字母占位图：
 * 底色按昵称散列取，保证同一个人每次颜色都一样。
 * 实现上把字母放在底层、图片放上层 —— 图片加载失败时字母自然露出来，不需要额外处理错误态。
 */
@Composable
fun NetworkAvatar(
    url: String?,
    name: String,
    size: Dp = 44.dp,
    modifier: Modifier = Modifier,
) {
    val bg = remember(name) {
        if (name.isEmpty()) AvatarPalette.first()
        else AvatarPalette[(name.hashCode().let { if (it < 0) -it else it }) % AvatarPalette.size]
    }
    // 用 Density.toSp()（会乘系统的 fontScale），而不是把 dp 数值直接当 sp 用 ——
    // 后者在用户调大系统字号时，头像里的首字母不会跟着放大。
    val fontSize = with(LocalDensity.current) { (size.toPx() * 0.42f).toSp() }

    Box(
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(bg),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = name.firstOrNull()?.toString() ?: "?",
            color = Color.White,
            fontSize = fontSize,
            fontWeight = FontWeight.Medium,
        )
        if (!url.isNullOrBlank()) {
            AsyncImage(
                model = url,
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize(),
            )
        }
    }
}

/** 小标签（陪玩标签、评价标签、游戏分类） */
@Composable
fun TagChip(
    text: String,
    modifier: Modifier = Modifier,
    background: Color = MaterialTheme.colorScheme.surfaceVariant,
    contentColor: Color = MaterialTheme.colorScheme.onSurfaceVariant,
) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(6.dp))
            .background(background)
            .padding(horizontal = 8.dp, vertical = 3.dp),
    ) {
        Text(
            text = text,
            style = MaterialTheme.typography.labelSmall,
            color = contentColor,
        )
    }
}

/**
 * 订单状态标签。
 * [statusClass] 直接用后端返回的 warn/info/primary/success/muted，
 * 不要在客户端自己映射状态码 —— 规则只有服务端一份。
 */
@Composable
fun StatusPill(
    text: String,
    statusClass: String,
    modifier: Modifier = Modifier,
) {
    val color = when (statusClass) {
        "success" -> com.wolaitongniwan.myapplication.ui.theme.StatusSuccess
        "warn" -> com.wolaitongniwan.myapplication.ui.theme.StatusWarning
        "info" -> com.wolaitongniwan.myapplication.ui.theme.StatusInfo
        "primary" -> MaterialTheme.colorScheme.primary
        else -> MaterialTheme.colorScheme.onSurfaceVariant
    }
    TagChip(text = text, modifier = modifier, background = color.copy(alpha = 0.12f), contentColor = color)
}

/** 区块标题：左侧一条竖线 + 主标题 + 可选右侧动作 */
@Composable
fun SectionTitle(
    title: String,
    modifier: Modifier = Modifier,
    actionText: String? = null,
    onAction: (() -> Unit)? = null,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            Modifier
                .size(width = 3.dp, height = 16.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(MaterialTheme.colorScheme.primary),
        )
        Spacer(Modifier.width(8.dp))
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.SemiBold,
            modifier = Modifier.weight(1f),
        )
        if (actionText != null && onAction != null) {
            Text(
                text = actionText,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier
                    .clip(RoundedCornerShape(4.dp))
                    .clickable(onClick = onAction)
                    .padding(horizontal = 6.dp, vertical = 4.dp),
            )
        }
    }
}

/** 列表底部「加载更多 / 没有更多了」 */
@Composable
fun LoadMoreFooter(
    loadingMore: Boolean,
    hasMore: Boolean,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .padding(vertical = 16.dp),
        contentAlignment = Alignment.Center,
    ) {
        if (loadingMore) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                Spacer(Modifier.width(8.dp))
                Text("加载中…", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        } else if (!hasMore) {
            Text(
                text = "— 没有更多了 —",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.outline,
            )
        }
    }
}

/** 金额展示：默认品牌色加粗 */
@Composable
fun MoneyText(
    text: String,
    modifier: Modifier = Modifier,
    color: Color = MaterialTheme.colorScheme.primary,
    fontSize: androidx.compose.ui.unit.TextUnit = 16.sp,
) {
    Text(
        text = text,
        modifier = modifier,
        color = color,
        fontSize = fontSize,
        fontWeight = FontWeight.Bold,
    )
}

/** 统一的横向内边距，避免每个页面各写一套 */
val ScreenPadding = PaddingValues(horizontal = 16.dp)

/** 页面区块之间的间距 */
@Composable
fun SectionSpacer(height: Dp = 8.dp) {
    Spacer(Modifier.height(height))
}

/** 一行「左标题 右内容」，用于详情页信息展示 */
@Composable
fun InfoRow(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    valueColor: Color = MaterialTheme.colorScheme.onSurface,
) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.Top,
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.width(16.dp))
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            color = valueColor,
            modifier = Modifier.weight(1f, fill = false),
            textAlign = androidx.compose.ui.text.style.TextAlign.End,
        )
    }
}
