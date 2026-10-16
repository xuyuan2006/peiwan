package com.wolaitongniwan.myapplication.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val LightColors = lightColorScheme(
    primary = BrandPurple,
    onPrimary = Color.White,
    primaryContainer = BrandPurpleSurface,
    onPrimaryContainer = BrandPurpleDark,
    secondary = BrandGreen,
    onSecondary = Color.White,
    tertiary = BrandPink,
    onTertiary = Color.White,
    background = BgLight,
    onBackground = TextPrimary,
    surface = Color.White,
    onSurface = TextPrimary,
    surfaceVariant = Color(0xFFF1F3F5),
    onSurfaceVariant = TextSecondary,
    outline = DividerLight,
    outlineVariant = DividerLight,
    error = StatusDanger,
    onError = Color.White,
)

private val DarkColors = darkColorScheme(
    primary = BrandPurpleLight,
    onPrimary = Color(0xFF1A1033),
    primaryContainer = Color(0xFF2C2340),
    onPrimaryContainer = Color(0xFFE7E1FF),
    secondary = BrandGreen,
    onSecondary = Color(0xFF00201A),
    tertiary = BrandPink,
    background = DarkBackground,
    onBackground = TextPrimaryDark,
    surface = DarkSurface,
    onSurface = TextPrimaryDark,
    surfaceVariant = Color(0xFF26282F),
    onSurfaceVariant = TextSecondaryDark,
    outline = Color(0xFF3A3D45),
    outlineVariant = Color(0xFF2A2D34),
    error = Color(0xFFFF6B6B),
    onError = Color(0xFF2A0A0A),
)

/**
 * 全局主题。
 *
 * 注意：这里没有开启动态取色（Material You），
 * 因为陪玩平台需要在任何机型上保持一致的品牌紫；
 * 深色模式仍然跟随系统。
 */
@Composable
fun PeiwanTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = Typography,
        content = content,
    )
}
