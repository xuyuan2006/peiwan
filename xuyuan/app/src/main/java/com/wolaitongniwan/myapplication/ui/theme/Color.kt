package com.wolaitongniwan.myapplication.ui.theme

import androidx.compose.ui.graphics.Color

/**
 * 品牌色板 —— 与网页端视觉保持一致（主色紫）。
 * 刻意不使用 Material You 动态取色，避免用户换壁纸后 App 失去品牌辨识度。
 */

val BrandPurple = Color(0xFF6C5CE7)
val BrandPurpleLight = Color(0xFFA29BFE)
val BrandPurpleDark = Color(0xFF4834D4)
val BrandPurpleSurface = Color(0xFFF0EDFF)

val BrandGreen = Color(0xFF00B894)
val BrandPink = Color(0xFFFD79A8)
val BrandOrange = Color(0xFFE17055)
val BrandYellow = Color(0xFFFDCB6E)

// 语义色（订单状态标签、金额正负等）
val StatusSuccess = Color(0xFF00B894)
val StatusWarning = Color(0xFFD68910)
val StatusDanger = Color(0xFFE74C3C)
val StatusInfo = Color(0xFF0984E3)
val StatusMuted = Color(0xFF95A5A6)

// 中性色
val TextPrimary = Color(0xFF2D3436)
val TextSecondary = Color(0xFF636E72)
val TextHint = Color(0xFFB2BEC3)
val DividerLight = Color(0xFFEDEFF2)
val BgLight = Color(0xFFF7F8FA)

// 深色模式
val DarkBackground = Color(0xFF15161A)
val DarkSurface = Color(0xFF1E2026)
val TextPrimaryDark = Color(0xFFECEDEE)
val TextSecondaryDark = Color(0xFF9BA1A6)

/** 头像占位图配色（按昵称散列取一个，保证同一个人颜色稳定） */
val AvatarPalette = listOf(
    Color(0xFF6C5CE7),
    Color(0xFF00B894),
    Color(0xFF0984E3),
    Color(0xFFE17055),
    Color(0xFFD63031),
    Color(0xFF00CEC9),
    Color(0xFFE84393),
    Color(0xFF6D4C41),
)
