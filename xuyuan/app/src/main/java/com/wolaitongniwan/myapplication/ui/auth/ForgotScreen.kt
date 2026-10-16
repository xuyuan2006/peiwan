package com.wolaitongniwan.myapplication.ui.auth

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.CodeField
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.PasswordField
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.SubmitButton

@Composable
fun ForgotScreen(
    onBack: () -> Unit,
    onDone: () -> Unit,
    vm: AuthViewModel = viewModel(),
) {
    val form by vm.forgotForm.collectAsStateWithLifecycle()
    val verify by vm.verify.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.loadVerifyChannel()
        vm.forgotSuccess.collect { onDone() }
    }

    val channel = verify.reset.channel
    val useEmail = channel != "sms"

    AppScaffold(
        title = "找回密码",
        onBack = onBack,
        snackbarHostState = snackbar,
        containerColor = MaterialTheme.colorScheme.surface,
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp),
        ) {
            SectionSpacer(12.dp)

            if (channel == "off") {
                Text(
                    text = "平台未开启验证码找回密码，请联系客服协助重置。",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                )
                Spacer(Modifier.height(24.dp))
            } else {
                Text(
                    text = "验证码将发送到你的${if (useEmail) "绑定邮箱" else "绑定手机号"}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(16.dp))

                if (useEmail) {
                    AppTextField(
                        value = form.email,
                        onValueChange = { v -> vm.onForgotChange { it.copy(email = v) } },
                        label = "邮箱",
                        keyboardType = KeyboardType.Email,
                        enabled = !form.submitting,
                    )
                } else {
                    AppTextField(
                        value = form.phone,
                        onValueChange = { v -> vm.onForgotChange { it.copy(phone = v) } },
                        label = "手机号",
                        keyboardType = KeyboardType.Phone,
                        enabled = !form.submitting,
                    )
                }

                Spacer(Modifier.height(12.dp))
                CodeField(
                    value = form.code,
                    onValueChange = { v -> vm.onForgotChange { it.copy(code = v) } },
                    onSend = vm::sendForgotCode,
                    countdown = form.countdown,
                    sending = form.sending,
                    enabled = !form.submitting,
                )

                Spacer(Modifier.height(12.dp))
                PasswordField(
                    value = form.password,
                    onValueChange = { v -> vm.onForgotChange { it.copy(password = v) } },
                    label = "新密码（至少 6 位）",
                    enabled = !form.submitting,
                )
                Spacer(Modifier.height(12.dp))
                PasswordField(
                    value = form.confirm,
                    onValueChange = { v -> vm.onForgotChange { it.copy(confirm = v) } },
                    label = "确认新密码",
                    imeAction = ImeAction.Done,
                    enabled = !form.submitting,
                )

                Spacer(Modifier.height(28.dp))
                SubmitButton(
                    text = "重置密码",
                    onClick = vm::resetPassword,
                    loading = form.submitting,
                )
            }

            Spacer(Modifier.height(40.dp))
        }
    }
}
