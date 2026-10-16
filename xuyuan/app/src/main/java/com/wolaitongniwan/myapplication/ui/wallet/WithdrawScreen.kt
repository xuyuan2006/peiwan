package com.wolaitongniwan.myapplication.ui.wallet

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.InfoRow
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.SubmitButton

private val AccountTypes = listOf(
    "alipay" to "支付宝",
    "wechat" to "微信",
    "bank" to "银行卡",
)

@Composable
fun WithdrawScreen(
    onBack: () -> Unit,
    onDone: () -> Unit,
    vm: WithdrawViewModel = viewModel(),
) {
    val form by vm.form.collectAsStateWithLifecycle()
    val wallet by vm.wallet.collectAsStateWithLifecycle()
    val config by vm.config.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.done.collect { onDone() }
    }

    val amount = form.amount.toDoubleOrNull() ?: 0.0
    // 用界面已经 collect 到的值调纯函数，不依赖 VM 内部再读一次 StateFlow
    val fee = WithdrawViewModel.feeOf(amount, config.feeRate)
    val real = WithdrawViewModel.realOf(amount, config.feeRate)

    AppScaffold(
        title = "申请提现",
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

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column(Modifier.weight(1f)) {
                        Text(
                            text = "可用余额",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Text(
                            text = "¥${wallet.balanceText}",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                        )
                    }
                    TextButton(onClick = vm::fillAll, enabled = !form.submitting) {
                        Text("全部提现")
                    }
                }
            }

            SectionSpacer(16.dp)
            AppTextField(
                value = form.amount,
                onValueChange = vm::setAmount,
                label = "提现金额（元）",
                placeholder = "最低 ${String.format("%.0f", config.min)} 元",
                keyboardType = KeyboardType.Decimal,
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            // 手续费试算，避免用户提交后才发现到账变少
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
            ) {
                Column(Modifier.padding(14.dp)) {
                    InfoRow("提现金额", String.format("¥%.2f", amount))
                    InfoRow(
                        label = "手续费（${String.format("%.2f", config.feeRate)}%）",
                        value = String.format("-¥%.2f", fee),
                        valueColor = MaterialTheme.colorScheme.error,
                    )
                    InfoRow("预计到账", String.format("¥%.2f", if (real > 0) real else 0.0))
                }
            }

            SectionSpacer(16.dp)
            Text("收款方式", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                AccountTypes.forEach { (key, label) ->
                    FilterChip(
                        selected = form.accountType == key,
                        onClick = { vm.setAccountType(key) },
                        label = { Text(label) },
                        enabled = !form.submitting,
                    )
                }
            }

            SectionSpacer(12.dp)
            AppTextField(
                value = form.accountName,
                onValueChange = vm::setAccountName,
                label = "收款人姓名",
                placeholder = "需与账号实名一致",
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))
            AppTextField(
                value = form.accountNo,
                onValueChange = vm::setAccountNo,
                label = when (form.accountType) {
                    "alipay" -> "支付宝账号"
                    "wechat" -> "微信号 / 手机号"
                    else -> "银行卡号"
                },
                keyboardType = if (form.accountType == "bank") KeyboardType.Number else KeyboardType.Text,
                imeAction = ImeAction.Done,
                enabled = !form.submitting,
            )

            SectionSpacer(20.dp)
            SubmitButton(
                text = "提交提现申请",
                onClick = vm::submit,
                loading = form.submitting,
                enabled = form.amount.isNotBlank() &&
                    form.accountName.isNotBlank() &&
                    form.accountNo.isNotBlank(),
            )

            Spacer(Modifier.height(12.dp))
            Text(
                text = "提交后金额会被冻结，平台审核通过后打款；若被驳回，冻结金额会自动解冻回余额。",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
            )
            Spacer(Modifier.height(32.dp))
        }
    }
}
