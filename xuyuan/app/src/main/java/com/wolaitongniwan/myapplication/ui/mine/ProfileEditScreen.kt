package com.wolaitongniwan.myapplication.ui.mine

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.common.uriToImagePart
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.PasswordField
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.SubmitButton
import kotlinx.coroutines.launch

@Composable
fun ProfileEditScreen(
    onBack: () -> Unit,
    onSaved: (String) -> Unit,
    vm: ProfileEditViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val form by vm.form.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val context = LocalContext.current

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.done.collect { msg -> onSaved(msg) }
    }

    val scope = rememberCoroutineScope()
    val picker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia()
    ) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        // 读图是 IO 操作，放进协程，别卡主线程
        scope.launch {
            val part = uriToImagePart(context, uri, "file")
            if (part == null) {
                Toast.makeText(context, "读取图片失败，请换一张", Toast.LENGTH_SHORT).show()
            } else {
                vm.uploadAvatar(part, uri.toString())
            }
        }
    }

    AppScaffold(title = "编辑资料", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = vm::load) {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState())
                        .padding(horizontal = 16.dp),
                ) {
                    SectionSpacer(16.dp)

                    // 头像
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box {
                            NetworkAvatar(
                                url = form.avatarPreview,
                                name = form.nickname,
                                size = 72.dp,
                            )
                            if (form.uploading) {
                                Box(Modifier.size(72.dp), contentAlignment = Alignment.Center) {
                                    CircularProgressIndicator(Modifier.size(24.dp), strokeWidth = 2.dp)
                                }
                            }
                        }
                        Spacer(Modifier.width(16.dp))
                        OutlinedButton(
                            onClick = {
                                picker.launch(
                                    PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)
                                )
                            },
                            enabled = !form.uploading && !form.submitting,
                        ) {
                            Text("更换头像")
                        }
                    }

                    SectionSpacer(16.dp)
                    AppTextField(
                        value = form.nickname,
                        onValueChange = { v -> vm.update { it.copy(nickname = v) } },
                        label = "昵称",
                        enabled = !form.submitting,
                    )
                    Spacer(Modifier.height(12.dp))
                    AppTextField(
                        value = form.phone,
                        onValueChange = { v -> vm.update { it.copy(phone = v) } },
                        label = "手机号",
                        keyboardType = KeyboardType.Phone,
                        enabled = !form.submitting,
                    )
                    Spacer(Modifier.height(12.dp))
                    AppTextField(
                        value = form.email,
                        onValueChange = { v -> vm.update { it.copy(email = v) } },
                        label = "邮箱",
                        keyboardType = KeyboardType.Email,
                        enabled = !form.submitting,
                    )

                    SectionSpacer(12.dp)
                    Text("性别", style = MaterialTheme.typography.bodyMedium)
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        listOf(0 to "保密", 1 to "男", 2 to "女").forEach { (value, label) ->
                            FilterChip(
                                selected = form.gender == value,
                                onClick = { vm.update { it.copy(gender = value) } },
                                label = { Text(label) },
                                enabled = !form.submitting,
                            )
                        }
                    }

                    SectionSpacer(20.dp)
                    Text("修改密码（不想改就留空）", style = MaterialTheme.typography.titleSmall)
                    Spacer(Modifier.height(10.dp))
                    PasswordField(
                        value = form.oldPassword,
                        onValueChange = { v -> vm.update { it.copy(oldPassword = v) } },
                        label = "当前密码",
                        enabled = !form.submitting,
                    )
                    Spacer(Modifier.height(12.dp))
                    PasswordField(
                        value = form.newPassword,
                        onValueChange = { v -> vm.update { it.copy(newPassword = v) } },
                        label = "新密码（至少 6 位）",
                        enabled = !form.submitting,
                    )
                    Spacer(Modifier.height(12.dp))
                    PasswordField(
                        value = form.confirmPassword,
                        onValueChange = { v -> vm.update { it.copy(confirmPassword = v) } },
                        label = "确认新密码",
                        imeAction = ImeAction.Done,
                        enabled = !form.submitting,
                    )

                    SectionSpacer(20.dp)
                    SubmitButton(
                        text = "保存",
                        onClick = vm::submit,
                        loading = form.submitting,
                        enabled = form.nickname.isNotBlank() && !form.uploading,
                    )

                    Spacer(Modifier.height(12.dp))
                    Text(
                        text = "改密码后其它设备会被强制下线，当前设备不受影响。",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                    Spacer(Modifier.height(32.dp))
                }
            }
        }
    }
}
