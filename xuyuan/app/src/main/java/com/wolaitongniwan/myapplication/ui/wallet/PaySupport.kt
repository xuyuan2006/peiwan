package com.wolaitongniwan.myapplication.ui.wallet

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeResultDto
import com.wolaitongniwan.myapplication.ui.components.QrCode

/**
 * 充值结果统一处理：
 *  - redirect → 用系统浏览器打开支付页
 *  - manual   → 无需额外动作（等客服核对到账）
 *  - qrcode   → 交给 [QrPayDialog] 展示二维码
 *
 * @return true 表示调用方可以直接关掉当前页面了
 */
fun handlePayRedirect(context: Context, result: RechargeResultDto): Boolean {
    return when (result.payMode) {
        "redirect" -> {
            openInBrowser(context, result.payUrl)
            true
        }
        "manual" -> true
        else -> false
    }
}

fun openInBrowser(context: Context, url: String) {
    if (url.isBlank()) {
        Toast.makeText(context, "没有拿到支付链接，请稍后在充值记录里重试", Toast.LENGTH_LONG).show()
        return
    }
    try {
        context.startActivity(
            Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        )
    } catch (_: Throwable) {
        Toast.makeText(context, "无法打开支付页面，请复制链接到浏览器", Toast.LENGTH_LONG).show()
    }
}

/**
 * 复制到剪贴板并给出提示。
 *
 * 付款链接、用户 ID 等都会用到，所以剪贴板条目的标签保持中性。
 */
fun copyToClipboard(context: Context, text: String) {
    try {
        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        clipboard.setPrimaryClip(ClipData.newPlainText("我来同你玩", text))
        Toast.makeText(context, "已复制", Toast.LENGTH_SHORT).show()
    } catch (_: Throwable) {
        // 剪贴板不可用就算了，不打断当前流程
    }
}

/** 支付宝「当面付」扫码弹窗 */
@Composable
fun QrPayDialog(
    result: RechargeResultDto,
    onDismiss: () -> Unit,
) {
    val context = LocalContext.current

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("扫码支付") },
        text = {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                QrCode(content = result.qrCode, size = 220.dp)
                Spacer(Modifier.height(12.dp))
                result.recharge?.let { recharge ->
                    Text(
                        text = "应付 ${recharge.amountText}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.primary,
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = "充值单号 ${recharge.orderNo}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                }
                Spacer(Modifier.height(10.dp))
                TextButton(onClick = { copyToClipboard(context, result.qrCode) }) {
                    Text("复制支付链接")
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) { Text("我已完成支付") }
        },
    )
}
