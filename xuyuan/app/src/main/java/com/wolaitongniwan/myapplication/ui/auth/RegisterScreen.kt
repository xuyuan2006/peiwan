package com.wolaitongniwan.myapplication.ui.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.FilterChip
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
fun RegisterScreen(
    onBack: () -> Unit,
    onRegistered: () -> Unit,
    vm: AuthViewModel = viewModel(),
) {
    val form by vm.registerForm.collectAsStateWithLifecycle()
    val verify by vm.verify.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.loadVerifyChannel()
        vm.registerSuccess.collect { onRegistered() }
    }

    // 后台可把注册验证方式配成 off / email / sms，表单要跟着变
    val channel = verify.register.channel
    val needCode = channel == "email" || channel == "sms"

    AppScaffold(
        title = "注册",
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

            // 账号不再让用户填：服务端会自动分配一个独一无二的。
            // 昵称就是他对外显示的名字，手机号则兼任登录账号。
            AppTextField(
                value = form.nickname,
                onValueChange = { v -> vm.onRegisterChange { it.copy(nickname = v) } },
                label = "昵称",
                placeholder = "例如：王者小王子",
                supportingText = "这是别人看到的名字；登录时用手机号",
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))

            PasswordField(
                value = form.password,
                onValueChange = { v -> vm.onRegisterChange { it.copy(password = v) } },
                label = "密码（至少 6 位）",
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))

            PasswordField(
                value = form.confirm,
                onValueChange = { v -> vm.onRegisterChange { it.copy(confirm = v) } },
                label = "确认密码",
                imeAction = ImeAction.Next,
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))

            AppTextField(
                value = form.phone,
                onValueChange = { v -> vm.onRegisterChange { it.copy(phone = v) } },
                label = "手机号",
                supportingText = "必填，登录时用它",
                keyboardType = KeyboardType.Phone,
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))

            AppTextField(
                value = form.email,
                onValueChange = { v -> vm.onRegisterChange { it.copy(email = v) } },
                label = "邮箱",
                supportingText = "必填",
                keyboardType = KeyboardType.Email,
                enabled = !form.submitting,
            )

            if (needCode) {
                Spacer(Modifier.height(12.dp))
                CodeField(
                    value = form.code,
                    onValueChange = { v -> vm.onRegisterChange { it.copy(code = v) } },
                    onSend = vm::sendRegisterCode,
                    countdown = form.countdown,
                    sending = form.sending,
                    label = if (channel == "email") "邮箱验证码" else "短信验证码",
                    enabled = !form.submitting,
                )
            }

            Spacer(Modifier.height(16.dp))
            Text("性别", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf(0 to "保密", 1 to "男", 2 to "女").forEach { (value, label) ->
                    FilterChip(
                        selected = form.gender == value,
                        onClick = { vm.onRegisterChange { it.copy(gender = value) } },
                        label = { Text(label) },
                        enabled = !form.submitting,
                    )
                }
            }

            Spacer(Modifier.height(28.dp))
            SubmitButton(
                text = "注册并登录",
                onClick = vm::register,
                loading = form.submitting,
            )
            Spacer(Modifier.height(40.dp))
        }
    }
}
