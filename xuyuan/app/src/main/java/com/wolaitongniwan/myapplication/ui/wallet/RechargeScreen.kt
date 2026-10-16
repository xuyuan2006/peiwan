package com.wolaitongniwan.myapplication.ui.wallet

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.ui.common.uriToImagePart
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.SubmitButton
import kotlinx.coroutines.launch

private val QuickAmounts = listOf(50, 100, 200, 500, 1000, 2000)

@Composable
fun RechargeScreen(
    onBack: () -> Unit,
    onDone: () -> Unit,
    vm: RechargeViewModel = viewModel(),
) {
    val form by vm.form.collectAsStateWithLifecycle()
    val config by vm.config.collectAsStateWithLifecycle()
    val result by vm.result.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val context = LocalContext.current

    // 统一走 VM 的判断，避免界面与提交校验各写一套导致结论不一致
    // 必须从**已收集的 config** 推导，不能调 vm.isManualChannel()。
    // 那个函数内部读 _config.value，Compose 追踪不到这次读取 ——
    // 结果它只在首次组合时算一次，而那时配置还没加载（payChannel 还是默认的 manual），
    // 于是 isManual 永远为 true：界面一边显示「选择支付方式」，一边却按人工转账
    // 要求上传凭证，提交按钮就一直灰着点不动。
    val isManual = config.payChannel == "manual"

    MessageEffect(vm.messages, snackbar)

    // 易支付是「跳浏览器付款 → 付完切回 App」的流程：
    // 借 ON_RESUME 重新拉一次登录态，用户切回来第一眼就能看到最新余额，不用手动下拉。
    // （支付到账最终以服务端回调为准，这里只是让界面尽快跟上。）
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) vm.refreshAfterPay()
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    val scope = rememberCoroutineScope()
    // 相册选凭证
    val picker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia()
    ) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        scope.launch {
            val part = uriToImagePart(context, uri, "file")
            if (part == null) {
                Toast.makeText(context, "读取图片失败，请换一张试试", Toast.LENGTH_SHORT).show()
            } else {
                vm.uploadVoucher(part, uri.toString())
            }
        }
    }

    // 支付指引：跳转类直接开浏览器并返回；二维码交给弹窗；人工转账直接返回
    LaunchedEffect(result) {
        val res = result ?: return@LaunchedEffect
        if (handlePayRedirect(context, res)) {
            vm.consumeResult()
            onDone()
        }
    }

    AppScaffold(
        title = "账户充值",
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

            AppTextField(
                value = form.amount,
                onValueChange = vm::setAmount,
                label = "充值金额（元）",
                placeholder = "最低 1 元，单次最高 50000 元",
                keyboardType = KeyboardType.Decimal,
                enabled = !form.submitting,
            )

            Spacer(Modifier.height(10.dp))
            // 快捷金额
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                QuickAmounts.chunked(3).forEach { row ->
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        row.forEach { value ->
                            FilterChip(
                                selected = form.amount == value.toString(),
                                onClick = { vm.setAmount(value.toString()) },
                                label = { Text("$value") },
                                enabled = !form.submitting,
                            )
                        }
                    }
                }
            }

            SectionSpacer(16.dp)
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            ) {
                Column(Modifier.padding(12.dp)) {
                    Text(
                        text = if (isManual) "人工转账" else "在线支付",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = config.rechargeChannels
                            .firstOrNull { !it.online }
                            ?.hint
                            .orEmpty()
                            .ifBlank {
                                if (isManual) "转账后上传凭证，客服确认到账后为你入账"
                                else "将跳转到第三方收银台完成支付"
                            },
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    if (config.servicePhone.isNotBlank()) {
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = "客服电话：${config.servicePhone}",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }

            // 只有人工转账才需要凭证
            if (isManual) {
                SectionSpacer(16.dp)
                Text("转账凭证", style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(8.dp))

                if (form.voucherPreview.isBlank()) {
                    OutlinedButton(
                        onClick = {
                            picker.launch(
                                PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)
                            )
                        },
                        enabled = !form.uploading && !form.submitting,
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        if (form.uploading) {
                            CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                            Spacer(Modifier.width(8.dp))
                        }
                        Text(if (form.uploading) "上传中…" else "选择转账截图")
                    }
                } else {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(180.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(MaterialTheme.colorScheme.surfaceVariant),
                    ) {
                        AsyncImage(
                            model = form.voucherPreview,
                            contentDescription = "转账凭证",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier.fillMaxSize(),
                        )
                        IconButton(
                            onClick = vm::clearVoucher,
                            modifier = Modifier.align(Alignment.TopEnd),
                            enabled = !form.submitting,
                        ) {
                            Icon(Icons.Filled.Close, contentDescription = "移除")
                        }
                        if (form.uploading) {
                            Box(
                                Modifier
                                    .fillMaxSize()
                                    .background(MaterialTheme.colorScheme.surface.copy(alpha = 0.6f)),
                                contentAlignment = Alignment.Center,
                            ) {
                                CircularProgressIndicator()
                            }
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = if (form.voucherPath.isNotBlank()) "凭证已上传" else "正在上传…",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }

            SectionSpacer(16.dp)
            OutlinedTextField(
                value = form.remark,
                onValueChange = vm::setRemark,
                label = { Text("备注（选填）") },
                placeholder = { Text("例如：支付宝转账，尾号 1234") },
                modifier = Modifier.fillMaxWidth(),
                enabled = !form.submitting,
                minLines = 2,
            )

            // 支付方式选择：只有「易支付」收银台支持微信和支付宝两种，让用户挑；
            // 当面付固定走支付宝，不显示选择（后端也不会读 payType）。
            if (config.payChannel == "epay") {
                SectionSpacer(16.dp)
                Text("选择支付方式", style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("wxpay" to "微信支付", "alipay" to "支付宝").forEach { (key, label) ->
                        FilterChip(
                            selected = form.payType == key,
                            onClick = { vm.setPayType(key) },
                            label = { Text(label) },
                            enabled = !form.submitting,
                        )
                    }
                }
            }

            SectionSpacer(20.dp)
            SubmitButton(
                text = if (isManual) "提交充值申请" else "去支付",
                onClick = vm::submit,
                loading = form.submitting,
                enabled = form.amount.isNotBlank() &&
                    (!isManual || form.voucherPath.isNotBlank()) &&
                    !form.uploading,
            )

            Spacer(Modifier.height(12.dp))
            Text(
                text = "温馨提示：在线支付的到账结果以服务端回调为准，支付完成后余额会自动更新；" +
                    "人工转账需要客服核对，通常几分钟内处理。",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
            )
            Spacer(Modifier.height(32.dp))
        }
    }

    // 扫码支付弹窗（支付宝当面付）
    // 注意：result 是 by 委托属性，Kotlin 不做智能转换，所以不能写 result!!；
    // 先取局部变量做一次判空，顺便避免「同一帧内状态被别处清空」造成的 NPE。
    val qrResult = result?.takeIf { it.payMode == "qrcode" }
    if (qrResult != null) {
        QrPayDialog(
            result = qrResult,
            onDismiss = {
                vm.consumeResult()
                onDone()
            },
        )
    }
}
