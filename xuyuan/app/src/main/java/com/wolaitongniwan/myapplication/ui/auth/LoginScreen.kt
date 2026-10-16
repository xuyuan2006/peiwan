package com.wolaitongniwan.myapplication.ui.auth

import androidx.compose.foundation.background
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.R
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.PasswordField
import com.wolaitongniwan.myapplication.ui.components.SubmitButton

@Composable
fun LoginScreen(
    onLoggedIn: () -> Unit,
    onGoRegister: () -> Unit,
    onGoForgot: () -> Unit,
    vm: AuthViewModel = viewModel(),
) {
    val form by vm.loginForm.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    val siteConfig by vm.siteConfig.collectAsStateWithLifecycle()

    MessageEffect(vm.messages, snackbar)

    // 站点 Logo 由后台配置，进登录页时拉一次
    LaunchedEffect(vm) { vm.loadSiteConfig() }

    LaunchedEffect(vm) {
        vm.loginSuccess.collect { onLoggedIn() }
    }

    AppScaffold(
        snackbarHostState = snackbar,
        containerColor = MaterialTheme.colorScheme.surface,
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(72.dp))

            // 站点 Logo：后台传了就用它，没传就用默认的「陪」字方块
            Box(
                modifier = Modifier
                    .size(72.dp)
                    .clip(RoundedCornerShape(20.dp))
                    .background(MaterialTheme.colorScheme.primary),
                contentAlignment = Alignment.Center,
            ) {
                if (siteConfig.siteLogo.isNotBlank()) {
                    AsyncImage(
                        model = siteConfig.siteLogo,
                        contentDescription = null,
                        modifier = Modifier.fillMaxSize(),
                    )
                } else {
                    Text(
                        text = "陪",
                        color = Color.White,
                        fontSize = 34.sp,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }

            Spacer(Modifier.height(16.dp))
            Text(
                text = stringResource(R.string.app_name),
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = "找陪玩 · 一键点单 · 秒接单",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            Spacer(Modifier.height(40.dp))

            AppTextField(
                value = form.account,
                onValueChange = vm::onAccountChange,
                label = "账号 / 手机号 / 邮箱",
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))
            PasswordField(
                value = form.password,
                onValueChange = vm::onPasswordChange,
                label = "密码",
                imeAction = ImeAction.Done,
                enabled = !form.submitting,
            )

            Spacer(Modifier.height(24.dp))
            SubmitButton(
                text = "登录",
                onClick = vm::login,
                loading = form.submitting,
            )

            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center,
            ) {
                TextButton(onClick = onGoRegister, enabled = !form.submitting) {
                    Text("注册新账号")
                }
                TextButton(onClick = onGoForgot, enabled = !form.submitting) {
                    Text("忘记密码")
                }
            }

            Spacer(Modifier.height(40.dp))
        }
    }
}
