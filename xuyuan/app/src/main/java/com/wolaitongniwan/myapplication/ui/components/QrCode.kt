package com.wolaitongniwan.myapplication.ui.components

import android.graphics.Bitmap
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.produceState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * 二维码渲染。
 *
 * 用于支付宝「当面付」：后端返回的 qr_code 其实是一串支付链接，需要画成二维码让用户扫。
 *
 * 生成放在 [Dispatchers.Default] 上：512×512 的位图要逐像素填 26 万次，
 * 在组合期同步做会明显掉帧。生成失败时退化为「提示复制链接」，至少让用户还能手动支付。
 */
@Composable
fun QrCode(
    content: String,
    modifier: Modifier = Modifier,
    size: Dp = 220.dp,
) {
    val bitmap by produceState<Bitmap?>(initialValue = null, content) {
        value = withContext(Dispatchers.Default) { generateQr(content, 512) }
    }

    val current = bitmap
    if (current == null) {
        Box(
            modifier = modifier.size(size),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                text = "二维码生成中…\n若长时间无响应，请复制下方链接到浏览器支付",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        return
    }

    Image(
        bitmap = current.asImageBitmap(),
        contentDescription = "支付二维码",
        modifier = modifier.size(size),
    )
}

private fun generateQr(content: String, pixels: Int): Bitmap? {
    if (content.isBlank()) return null
    return try {
        val hints = mapOf(
            EncodeHintType.CHARACTER_SET to "UTF-8",
            EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M,
            // 留一圈白边，否则部分扫码器识别不出来
            EncodeHintType.MARGIN to 1,
        )
        val matrix = QRCodeWriter().encode(content, BarcodeFormat.QR_CODE, pixels, pixels, hints)
        val width = matrix.width
        val height = matrix.height
        val pixelsArray = IntArray(width * height)
        for (y in 0 until height) {
            val offset = y * width
            for (x in 0 until width) {
                pixelsArray[offset + x] = if (matrix.get(x, y)) {
                    android.graphics.Color.BLACK
                } else {
                    android.graphics.Color.WHITE
                }
            }
        }
        Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888).apply {
            setPixels(pixelsArray, 0, width, 0, 0, width, height)
        }
    } catch (_: Throwable) {
        // 内容过长或 zxing 拒绝编码时不要崩，交给调用方降级展示
        null
    }
}
