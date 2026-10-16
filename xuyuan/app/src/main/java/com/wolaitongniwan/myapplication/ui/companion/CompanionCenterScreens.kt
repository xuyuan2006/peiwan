package com.wolaitongniwan.myapplication.ui.companion

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.data.remote.dto.SkillDto
import com.wolaitongniwan.myapplication.ui.common.uriToImagePart
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.AppTextField
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.SectionSpacer
import com.wolaitongniwan.myapplication.ui.components.SectionTitle
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.SubmitButton
import com.wolaitongniwan.myapplication.ui.components.TagChip
import com.wolaitongniwan.myapplication.ui.theme.StatusDanger
import com.wolaitongniwan.myapplication.ui.theme.StatusSuccess
import com.wolaitongniwan.myapplication.ui.wallet.copyToClipboard
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

// ================================================================ 陪玩中心

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CompanionCenterScreen(
    onBack: () -> Unit,
    onOpenSkillEdit: (Long) -> Unit,
    onOpenApply: () -> Unit,
    onOpenMyOrders: () -> Unit,
    vm: CompanionCenterViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val busy by vm.busy.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    var deletingSkill by remember { mutableStateOf<SkillDto?>(null) }

    MessageEffect(vm.messages, snackbar)

    AppScaffold(
        title = "陪玩中心",
        onBack = onBack,
        snackbarHostState = snackbar,
        // 顶栏放一个「我的接单」：接单后这是最顺手的入口，
        // 不用退回「我的」再翻「更多」
        actions = {
            TextButton(onClick = onOpenMyOrders) { Text("我的接单") }
        },
        floatingActionButton = {
            FloatingActionButton(onClick = { onOpenSkillEdit(0L) }) {
                Icon(Icons.Filled.Add, contentDescription = "新增服务项目")
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = vm::load) { data ->
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = 88.dp),
                ) {
                    // 审核状态
                    if (data.audit.auditStatus != 1) {
                        item {
                            Card(
                                modifier = Modifier.fillMaxWidth().padding(12.dp),
                                colors = CardDefaults.cardColors(
                                    containerColor = MaterialTheme.colorScheme.errorContainer
                                ),
                                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                            ) {
                                Column(Modifier.padding(14.dp)) {
                                    Text(
                                        text = if (data.audit.applied) "入驻审核：${data.audit.auditText}" else "你还不是陪玩",
                                        style = MaterialTheme.typography.titleSmall,
                                        fontWeight = FontWeight.SemiBold,
                                    )
                                    if (data.audit.auditRemark.isNotBlank()) {
                                        Spacer(Modifier.height(4.dp))
                                        Text(
                                            text = "平台备注：${data.audit.auditRemark}",
                                            style = MaterialTheme.typography.labelSmall,
                                        )
                                    }
                                    Spacer(Modifier.height(10.dp))
                                    OutlinedButton(onClick = onOpenApply) {
                                        Text(if (data.audit.applied) "修改申请资料" else "去申请入驻")
                                    }
                                }
                            }
                        }
                    }

                    // 上下线
                    item {
                        Card(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth().padding(16.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Column(Modifier.weight(1f)) {
                                    Text("接单状态", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                                    Text(
                                        text = if (data.online) "接单中" else "休息中",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = if (data.online) StatusSuccess else MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                                Switch(
                                    checked = data.online,
                                    onCheckedChange = vm::toggleOnline,
                                    enabled = !busy && data.audit.auditStatus == 1,
                                )
                            }
                        }
                    }

                    // 收益
                    item {
                        Card(
                            modifier = Modifier.fillMaxWidth().padding(12.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primary),
                            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                        ) {
                            Column(Modifier.padding(16.dp)) {
                                Text(
                                    text = "今日收入（元）",
                                    style = MaterialTheme.typography.labelMedium,
                                    color = Color.White.copy(alpha = 0.85f),
                                )
                                Spacer(Modifier.height(4.dp))
                                Text(
                                    text = data.income.todayIncomeText,
                                    fontSize = 30.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White,
                                )
                                Spacer(Modifier.height(12.dp))
                                Row(horizontalArrangement = Arrangement.spacedBy(24.dp)) {
                                    IncomeCell("累计收入", data.income.totalIncomeText)
                                    IncomeCell("今日单数", "${data.income.todayOrders}")
                                    IncomeCell("服务中", "${data.counts.serving}")
                                }
                                Spacer(Modifier.height(12.dp))
                                Text(
                                    text = "余额 ¥${data.wallet.balanceText} · 冻结 ¥${data.wallet.frozenText}",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = Color.White.copy(alpha = 0.85f),
                                )
                            }
                        }
                    }

                    // 待办
                    item {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp),
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            TodoCell("待接单", data.counts.pendingAccept, Modifier.weight(1f))
                            TodoCell("大厅可抢", data.counts.hallTotal, Modifier.weight(1f))
                        }
                    }

                    // 服务项目
                    item {
                        SectionTitle(title = "服务项目（${data.skills.size}）")
                    }

                    if (data.skills.isEmpty()) {
                        item {
                            Text(
                                text = "还没有上架服务项目，点右下角「＋」添加一个吧",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                            )
                        }
                    } else {
                        items(data.skills, key = { it.id }) { skill ->
                            SkillManageRow(
                                skill = skill,
                                busy = busy,
                                onEdit = { onOpenSkillEdit(skill.id) },
                                onToggle = { vm.toggleSkill(skill.id) },
                                onDelete = { deletingSkill = skill },
                            )
                        }
                    }
                }
            }

            SnackbarHost(snackbar, Modifier.align(Alignment.BottomCenter))
        }
    }

    deletingSkill?.let { skill ->
        AlertDialog(
            onDismissRequest = { deletingSkill = null },
            title = { Text("删除服务项目") },
            text = { Text("确定删除「${skill.title}」吗？已被订单引用过的项目删不掉，只能先下架。") },
            confirmButton = {
                Button(onClick = {
                    deletingSkill = null
                    vm.deleteSkill(skill.id)
                }) { Text("确定删除") }
            },
            dismissButton = {
                TextButton(onClick = { deletingSkill = null }) { Text("取消") }
            },
        )
    }
}

@Composable
private fun IncomeCell(label: String, value: String) {
    Column(horizontalAlignment = Alignment.Start) {
        Text(text = value, style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold, color = Color.White)
        Text(text = label, style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.8f))
    }
}

@Composable
private fun TodoCell(label: String, value: Int, modifier: Modifier = Modifier) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(
            modifier = Modifier.fillMaxWidth().padding(vertical = 14.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(text = "$value", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun SkillManageRow(
    skill: SkillDto,
    busy: Boolean,
    onEdit: () -> Unit,
    onToggle: () -> Unit,
    onDelete: () -> Unit,
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 3.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = skill.title,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f),
                )
                TagChip(
                    text = if (skill.status == 1) "已上架" else "已下架",
                    background = (if (skill.status == 1) StatusSuccess else MaterialTheme.colorScheme.outline)
                        .copy(alpha = 0.12f),
                    contentColor = if (skill.status == 1) StatusSuccess else MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                MoneyText(text = skill.priceText, fontSize = 15.sp)
                Spacer(Modifier.width(4.dp))
                Text(
                    text = "/ ${skill.unit}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.width(8.dp))
                if (skill.gameName.isNotBlank()) TagChip(skill.gameName)
                if (skill.rankLevel.isNotBlank()) {
                    Spacer(Modifier.width(4.dp))
                    TagChip(skill.rankLevel)
                }
            }
            Spacer(Modifier.height(8.dp))
            HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
            Spacer(Modifier.height(4.dp))
            Row(horizontalArrangement = Arrangement.End, modifier = Modifier.fillMaxWidth()) {
                TextButton(onClick = onEdit, enabled = !busy) { Text("编辑") }
                TextButton(onClick = onToggle, enabled = !busy) {
                    Text(if (skill.status == 1) "下架" else "上架")
                }
                TextButton(onClick = onDelete, enabled = !busy) {
                    Text("删除", color = StatusDanger)
                }
            }
        }
    }
}

// ================================================================ 入驻申请

@Composable
fun CompanionApplyScreen(
    onBack: () -> Unit,
    onDone: () -> Unit,
    vm: CompanionApplyViewModel = viewModel(),
) {
    val form by vm.form.collectAsStateWithLifecycle()
    val auditText by vm.auditText.collectAsStateWithLifecycle()
    val auditRemark by vm.auditRemark.collectAsStateWithLifecycle()
    val config by vm.config.collectAsStateWithLifecycle()
    val showContact by vm.showContact.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    // 考核标准：后台填了才拦截。必须真的把弹窗打开并停留满 4 秒才能继续提交。
    val assess = config.companionAssess
    var assessOpen by remember { mutableStateOf(false) }
    var assessPassed by rememberSaveable { mutableStateOf(false) }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(vm) {
        vm.done.collect { onDone() }
    }

    AppScaffold(title = "成为陪玩", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp),
        ) {
            SectionSpacer(12.dp)

            if (auditText != "未申请") {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                ) {
                    Column(Modifier.padding(12.dp)) {
                        Text("当前状态：$auditText", style = MaterialTheme.typography.bodyMedium)
                        if (auditRemark.isNotBlank()) {
                            Spacer(Modifier.height(4.dp))
                            Text(
                                text = "平台备注：$auditRemark",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.error,
                            )
                        }
                    }
                }
                SectionSpacer(12.dp)
            }

            Text(
                text = "请如实填写，平台审核通过后你就能上架服务并接单。",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            // ---------------- 考核标准（必须看完才能提交） ----------------
            if (assess.isNotBlank()) {
                SectionSpacer(12.dp)
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = if (assessPassed) {
                            MaterialTheme.colorScheme.surfaceVariant
                        } else {
                            MaterialTheme.colorScheme.primaryContainer
                        }
                    ),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                ) {
                    Column(Modifier.padding(14.dp)) {
                        Text(
                            text = if (assessPassed) "已阅读考核标准" else "申请前请先阅读考核标准",
                            style = MaterialTheme.typography.titleSmall,
                            fontWeight = FontWeight.SemiBold,
                        )
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = if (assessPassed) {
                                "提交后客服会联系你安排考核。"
                            } else {
                                "需要停留 ${ASSESS_SECONDS} 秒看完才能提交申请。"
                            },
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Spacer(Modifier.height(10.dp))
                        if (assessPassed) {
                            OutlinedButton(
                                onClick = { assessOpen = true },
                                modifier = Modifier.fillMaxWidth(),
                            ) { Text("回看考核标准") }
                        } else {
                            Button(
                                onClick = { assessOpen = true },
                                modifier = Modifier.fillMaxWidth(),
                            ) { Text("查看考核标准") }
                        }
                    }
                }
            }

            SectionSpacer(12.dp)
            AppTextField(
                value = form.realName,
                onValueChange = { v -> vm.update { it.copy(realName = v) } },
                label = "真实姓名",
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))
            AppTextField(
                value = form.phone,
                onValueChange = { v -> vm.update { it.copy(phone = v) } },
                label = "联系手机号",
                keyboardType = KeyboardType.Phone,
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))
            AppTextField(
                value = form.idCard,
                onValueChange = { v -> vm.update { it.copy(idCard = v) } },
                label = "身份证号（必填，仅平台审核可见）",
                enabled = !form.submitting,
            )
            Spacer(Modifier.height(12.dp))
            AppTextField(
                value = form.city,
                onValueChange = { v -> vm.update { it.copy(city = v) } },
                label = "所在城市（选填）",
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

            SectionSpacer(12.dp)
            AppTextField(
                value = form.tags,
                onValueChange = { v -> vm.update { it.copy(tags = v) } },
                label = "标签（用英文逗号分隔）",
                placeholder = "技术好,声音甜,有耐心",
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            OutlinedTextField(
                value = form.intro,
                onValueChange = { v -> vm.update { it.copy(intro = v.take(500)) } },
                label = { Text("个人介绍") },
                placeholder = { Text("擅长哪些游戏、什么段位、服务时间等") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 4,
                enabled = !form.submitting,
                supportingText = { Text("${form.intro.length}/500") },
            )

            SectionSpacer(20.dp)
            SubmitButton(
                text = if (auditText == "未申请") "提交申请" else "保存修改",
                onClick = vm::submit,
                loading = form.submitting,
                enabled = form.realName.isNotBlank() &&
                    form.phone.isNotBlank() &&
                    form.idCard.isNotBlank() &&
                    (assess.isBlank() || assessPassed),
            )
            Spacer(Modifier.height(32.dp))
        }
    }

    if (assessOpen) {
        AssessDialog(
            content = assess,
            onDismiss = { assessOpen = false },
            onAgree = {
                assessPassed = true
                assessOpen = false
            },
        )
    }

    if (showContact) {
        ContactDialog(
            qrUrl = config.companionContactQr,
            text = config.companionContactText,
            onDismiss = vm::dismissContact,
        )
    }
}

/** 考核标准必须停留的秒数 */
private const val ASSESS_SECONDS = 4

/**
 * 考核标准弹窗。
 *
 * 「我已阅读并同意」在倒计时结束前是禁用的 —— 需求就是「必须在里面看 4 秒才可以」，
 * 所以这里不能只做个提示，得真的拦住。
 */
@Composable
private fun AssessDialog(
    content: String,
    onDismiss: () -> Unit,
    onAgree: () -> Unit,
) {
    var left by remember { mutableIntStateOf(ASSESS_SECONDS) }

    LaunchedEffect(Unit) {
        while (left > 0) {
            delay(1000)
            left -= 1
        }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("陪玩考核标准") },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState())) {
                Text(content, style = MaterialTheme.typography.bodySmall)
            }
        },
        confirmButton = {
            Button(onClick = onAgree, enabled = left <= 0) {
                Text(if (left > 0) "请阅读 ${left}s" else "我已阅读并同意")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("稍后再看") }
        },
    )
}

/** 申请提交后展示客服联系方式（二维码 / 文字，后台填了哪个显示哪个） */
@Composable
private fun ContactDialog(
    qrUrl: String,
    text: String,
    onDismiss: () -> Unit,
) {
    val context = LocalContext.current

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("申请已提交") },
        text = {
            Column(
                modifier = Modifier.verticalScroll(rememberScrollState()),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(
                    text = "请联系客服完成后续考核。添加时备注一下你的昵称，方便客服核对。",
                    style = MaterialTheme.typography.bodySmall,
                )

                if (qrUrl.isNotBlank()) {
                    Spacer(Modifier.height(12.dp))
                    AsyncImage(
                        model = qrUrl,
                        contentDescription = "客服二维码",
                        modifier = Modifier
                            .size(180.dp)
                            .clip(RoundedCornerShape(12.dp)),
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = "截图后到微信「扫一扫」→ 从相册选图",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.outline,
                    )
                }

                if (text.isNotBlank()) {
                    Spacer(Modifier.height(12.dp))
                    Text(
                        text = text,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold,
                    )
                    Spacer(Modifier.height(8.dp))
                    OutlinedButton(onClick = { copyToClipboard(context, text.trim()) }) {
                        Text("复制联系方式")
                    }
                }
            }
        },
        confirmButton = {
            Button(onClick = onDismiss) { Text("知道了") }
        },
    )
}

// ================================================================ 服务项目编辑

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SkillEditScreen(
    skillId: Long,
    onBack: () -> Unit,
    onDone: () -> Unit,
    vm: SkillEditViewModel = viewModel(),
) {
    val form by vm.form.collectAsStateWithLifecycle()
    val games by vm.games.collectAsStateWithLifecycle()
    val serviceTypes by vm.serviceTypes.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val context = LocalContext.current

    var gameMenu by remember { mutableStateOf(false) }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(skillId) { vm.load(skillId) }

    LaunchedEffect(vm) {
        vm.done.collect { onDone() }
    }

    val scope = rememberCoroutineScope()
    val picker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia()
    ) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        scope.launch {
            val part = uriToImagePart(context, uri, "file")
            if (part == null) {
                Toast.makeText(context, "读取图片失败，请换一张", Toast.LENGTH_SHORT).show()
            } else {
                vm.uploadCover(part, uri.toString())
            }
        }
    }

    val currentGameName = games.firstOrNull { it.id == form.gameId }?.name ?: "请选择游戏"

    AppScaffold(
        title = if (skillId > 0) "编辑服务项目" else "新增服务项目",
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

            // 游戏选择
            Text("游戏", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(6.dp))
            Box {
                OutlinedButton(
                    onClick = { gameMenu = true },
                    enabled = !form.submitting,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text(currentGameName)
                }
                DropdownMenu(expanded = gameMenu, onDismissRequest = { gameMenu = false }) {
                    games.forEach { game ->
                        DropdownMenuItem(
                            text = { Text(game.name) },
                            onClick = {
                                gameMenu = false
                                vm.update { it.copy(gameId = game.id) }
                            },
                        )
                    }
                }
            }

            SectionSpacer(12.dp)
            AppTextField(
                value = form.title,
                onValueChange = { v -> vm.update { it.copy(title = v) } },
                label = "服务标题",
                placeholder = "例如：王者荣耀 上分陪练",
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            Text("计费方式", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                val options = if (serviceTypes.isNotEmpty()) {
                    serviceTypes.map { it.key to it.name }
                } else {
                    listOf("hour" to "按小时", "round" to "按局", "day" to "按天", "custom" to "一口价")
                }
                options.forEach { (key, label) ->
                    FilterChip(
                        selected = form.serviceType == key,
                        onClick = { vm.update { it.copy(serviceType = key) } },
                        label = { Text(label) },
                        enabled = !form.submitting,
                    )
                }
            }

            SectionSpacer(12.dp)
            AppTextField(
                value = form.price,
                onValueChange = vm::setPrice,
                label = "价格（元）",
                placeholder = "例如：30",
                keyboardType = KeyboardType.Decimal,
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            AppTextField(
                value = form.rankLevel,
                onValueChange = { v -> vm.update { it.copy(rankLevel = v) } },
                label = "段位 / 水平（选填）",
                placeholder = "例如：王者 50 星",
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            OutlinedTextField(
                value = form.description,
                onValueChange = { v -> vm.update { it.copy(description = v.take(500)) } },
                label = { Text("服务说明（选填）") },
                placeholder = { Text("包含哪些内容、注意事项等") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 4,
                enabled = !form.submitting,
            )

            SectionSpacer(12.dp)
            Text("项目配图（选填）", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(8.dp))
            if (form.coverPreview.isBlank()) {
                OutlinedButton(
                    onClick = {
                        picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
                    },
                    enabled = !form.uploading && !form.submitting,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    if (form.uploading) {
                        CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                        Spacer(Modifier.width(8.dp))
                    }
                    Text(if (form.uploading) "上传中…" else "选择配图")
                }
            } else {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(160.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(MaterialTheme.colorScheme.surfaceVariant),
                ) {
                    AsyncImage(
                        model = form.coverPreview,
                        contentDescription = "项目配图",
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxSize(),
                    )
                    if (form.uploading) {
                        Box(
                            Modifier
                                .fillMaxSize()
                                .background(MaterialTheme.colorScheme.surface.copy(alpha = 0.6f)),
                            contentAlignment = Alignment.Center,
                        ) { CircularProgressIndicator() }
                    }
                }
            }

            SectionSpacer(20.dp)
            SubmitButton(
                text = if (skillId > 0) "保存修改" else "上架服务",
                onClick = vm::submit,
                loading = form.submitting,
                enabled = form.gameId > 0 && form.title.isNotBlank() && form.price.isNotBlank() && !form.uploading,
            )

            Spacer(Modifier.height(12.dp))
            Text(
                text = "提示：配图留空表示保持原图；已被订单引用的项目只能下架，不能删除。",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.outline,
            )
            Spacer(Modifier.height(32.dp))
        }
    }
}
