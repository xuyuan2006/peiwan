package com.wolaitongniwan.myapplication.ui.components

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.wolaitongniwan.myapplication.ui.wallet.copyToClipboard

/**
 * 版本更新信息（服务端 [AppUpdateDto.minAppVersion] 比本机版本大时才有值）。
 */
data class AppUpdate(
    /** 服务端要求的最新版本号，如 1.1 */
    val version: String,
    /** 更新说明，弹窗里显示，可一键复制 */
    val note: String,
    /** 下载地址；留空则「立即更新」按钮不显示 */
    val url: String,
    /** 强制更新：不能关闭，必须去更新 */
    val force: Boolean,
)

/**
 * 比较版本号，服务端更大就返回更新信息，否则返回 null。
 *
 * 用「按点拆分、逐段比数字」的方式，而不是字符串比较 ——
 * 字符串比较会把 "1.10" 判成小于 "1.9"（因为 '1' < '9'），那是错的。
 * 段数不一致时短的按 0 补齐，所以 1.1 与 1.1.0 视为相同。
 */
fun checkUpdate(
    serverVersion: String,
    note: String,
    url: String,
    force: Boolean,
    currentVersion: String,
): AppUpdate? {
    val server = serverVersion.trim()
    if (server.isEmpty()) return null

    if (compareVersion(server, currentVersion) <= 0) return null

    return AppUpdate(version = server, note = note.trim(), url = url.trim(), force = force)
}

/** a > b 返回正数，a < b 返回负数，相等返回 0 */
private fun compareVersion(a: String, b: String): Int {
    val pa = a.split('.', '-', '+')
    val pb = b.split('.', '-', '+')
    val size = maxOf(pa.size, pb.size)

    for (i in 0 until size) {
        val na = pa.getOrNull(i)?.toIntOrNull() ?: 0
        val nb = pb.getOrNull(i)?.toIntOrNull() ?: 0
        if (na != nb) return na - nb
    }
    return 0
}

/**
 * 版本更新弹窗。
 *
 * - 有下载地址才显示「立即更新」，点了交给系统浏览器打开
 * - 更新说明可一键复制（方便用户复制到电脑上打开）
 * - 强制更新时隐藏「稍后再说」，按返回键也关不掉
 */
@Composable
fun AppUpdateDialog(info: AppUpdate, onDismiss: () -> Unit) {
    val context = LocalContext.current

    AlertDialog(
        // 强制更新时点外部/返回键都不关闭
        onDismissRequest = { if (!info.force) onDismiss() },
        title = {
            Text(
                text = "发现新版本 ${info.version}",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
            )
        },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState())) {
                Text(
                    text = if (info.force) {
                        "此版本需要更新后才能继续使用。"
                    } else {
                        "有新版本可用，建议更新后使用。"
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )

                if (info.note.isNotBlank()) {
                    Spacer(Modifier.height(10.dp))
                    Text(
                        text = "更新内容",
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(text = info.note, style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(8.dp))
                    OutlinedButton(
                        onClick = { copyToClipboard(context, info.note) },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("复制更新说明") }
                }

                if (info.url.isBlank()) {
                    Spacer(Modifier.height(10.dp))
                    Text(
                        text = "（管理员还没填下载地址，请联系客服获取新版本）",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                }
            }
        },
        confirmButton = {
            if (info.url.isNotBlank()) {
                Button(onClick = {
                    // 打开系统浏览器下载；打不开就静默忽略（更新说明仍可复制）
                    runCatching {
                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(info.url)))
                    }
                }) { Text("立即更新") }
            } else {
                Button(onClick = { if (!info.force) onDismiss() }) { Text("知道了") }
            }
        },
        dismissButton = {
            if (!info.force) {
                TextButton(onClick = onDismiss) { Text("稍后再说") }
            }
        },
    )
}
