package com.wolaitongniwan.myapplication.ui.mine

import androidx.compose.foundation.background
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.clickable
import androidx.compose.foundation.combinedClickable
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.data.remote.dto.UserCenterDto
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.components.LoginRequiredView
import com.wolaitongniwan.myapplication.ui.components.AutoRefresh
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.NetworkAvatar
import com.wolaitongniwan.myapplication.ui.components.SectionTitle
import com.wolaitongniwan.myapplication.ui.components.StateBox
import com.wolaitongniwan.myapplication.ui.components.TagChip
import com.wolaitongniwan.myapplication.ui.wallet.copyToClipboard

@Composable
// PullToRefreshBox 目前仍标着实验 API
@OptIn(ExperimentalMaterial3Api::class)
fun MineScreen(
    loggedIn: Boolean,
    onOpenLogin: () -> Unit,
    onOpenProfileEdit: () -> Unit,
    onOpenWallet: () -> Unit,
    onOpenTransactions: () -> Unit,
    onOpenTickets: () -> Unit,
    onOpenCompanionCenter: () -> Unit,
    onOpenCompanionApply: () -> Unit,
    onOpenHall: () -> Unit,
    onOpenCompanionOrders: () -> Unit,
    onOpenMyReviews: () -> Unit,
    onGoOrders: () -> Unit,
    vm: MineViewModel = viewModel(),
) {
    if (!loggedIn) {
        LoginRequiredView(onLogin = onOpenLogin, text = "登录后查看你的资料与钱包")
        return
    }

    val state by vm.state.collectAsStateWithLifecycle()
    val toggling by vm.toggling.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    val refreshing by vm.refreshing.collectAsStateWithLifecycle()

    // 每次进入「我的」页都重拉一次数据。
    // 原因：App 内部切换 Tab 不会触发 ON_RESUME（压根没离开过 App），
    // 而 MineViewModel 只在首次创建时加载 —— 所以切来切去看到的还是旧数据。
    // 这个 LaunchedEffect 在每次重新进入组合时都会跑一次，正好补上这一环。
    LaunchedEffect(Unit) { vm.refresh() }

    // 停留在这个页面期间，每 5 秒自动向服务器同步一次（余额、陪玩审核状态等）；
    // 切到后台会自动暂停
    AutoRefresh { vm.refresh() }

    // 每次从后台切回前台时重拉一次个人中心数据。
    //
    // 为什么需要：这页的内容来自 MineViewModel 自己的缓存，只在页面首次创建时拉一次。
    // 管理员在后台批准陪玩申请 / 改账号状态后，App 里那份数据不会自己更新 ——
    // 用户必须退出软件再进才看得到。这个 effect 把那一步自动化。
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) vm.load()
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    // 退出登录后不需要额外跳转：Session 被清空 → MainScreen 的 loggedIn 变 false
    // → 这个页面自动切到 LoginRequiredView。之前这里会 push 一个登录页，属于多余动作。

    Box(Modifier.fillMaxSize()) {
        // 下拉刷新：管理员在后台改了状态后，用户不必退出软件，拉一下即可同步
        PullToRefreshBox(
            isRefreshing = refreshing,
            onRefresh = vm::refresh,
            modifier = Modifier.fillMaxSize(),
        ) {
        StateBox(state = state, onRetry = vm::load) { data ->
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(bottom = 24.dp),
            ) {
                item { ProfileHeader(data = data, onEdit = onOpenProfileEdit) }

                item {
                    WalletCard(
                        data = data,
                        onOpenWallet = onOpenWallet,
                        onOpenTransactions = onOpenTransactions,
                    )
                }

                item { OrderCountsRow(data = data, onGoOrders = onGoOrders) }

                // 陪玩工作台：已经过审的陪玩每天都要用它（切在线、去接单），所以保持靠前
                if (data.companion.applied && data.companion.auditStatus == 1) {
                    item {
                        CompanionWorkCard(
                            data = data,
                            toggling = toggling,
                            onToggle = vm::toggleOnline,
                            onOpenCenter = onOpenCompanionCenter,
                            onOpenHall = onOpenHall,
                        )
                    }
                }

                item { SectionTitle(title = "更多") }

                item {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
                    ) {
                        EntryRow("编辑资料", onOpenProfileEdit)
                        EntryRow("我的工单", onOpenTickets)
                        EntryRow("我的评价", onOpenMyReviews)
                        EntryRow("资金流水", onOpenTransactions)
                        if (data.companion.applied && data.companion.auditStatus == 1) {
                            // 「我的接单」必须放在最前面：陪玩接单后，只有这个页面能看到
                            // 自己接下的单、并进订单详情联系顾客
                            EntryRow("我的接单", onOpenCompanionOrders)
                            EntryRow("陪玩中心", onOpenCompanionCenter)
                            EntryRow("接单大厅", onOpenHall)
                        }
                        EntryRow("退出登录", vm::logout, danger = true, showDivider = false)
                    }
                }

                if (data.intro.isNotBlank()) {
                    item { CompanionIntro(text = data.intro) }
                }

                // 「成为陪玩」挪到最底部：
                // 对已入驻的人是噪音，对普通用户也不该霸占首屏 —— 想申请的人往下翻就能找到。
                if (!(data.companion.applied && data.companion.auditStatus == 1)) {
                    item {
                        ApplyCard(
                            auditText = data.companion.auditText,
                            auditRemark = data.companion.auditRemark,
                            onApply = onOpenCompanionApply,
                        )
                    }
                }
            }
        }
        }   // ← 关闭上面的 PullToRefreshBox

        SnackbarHost(
            hostState = snackbar,
            modifier = Modifier.align(Alignment.BottomCenter),
        )
    }
}

// ---------------------------------------------------------------- 子组件

// combinedClickable 在部分 foundation 版本里仍标着实验 API，补 OptIn 是零成本保险
@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun ProfileHeader(data: UserCenterDto, onEdit: () -> Unit) {
    val context = LocalContext.current
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onEdit)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        NetworkAvatar(url = data.user.avatarUrl, name = data.user.nickname, size = 64.dp)
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(
                text = data.user.nickname.ifBlank { data.user.username },
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
            )
            Spacer(Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                TagChip(
                    text = when {
                        data.user.isAdmin -> "管理员"
                        data.companion.applied && data.companion.auditStatus == 1 -> "陪玩"
                        data.companion.applied -> "入驻${data.companion.auditText}"
                        else -> "普通用户"
                    },
                    background = MaterialTheme.colorScheme.primaryContainer,
                    contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
                )
                TagChip(text = "信用分 ${data.user.creditScore}")
            }

            Spacer(Modifier.height(8.dp))
            // 用户 ID（随机 6 位）—— 报给客服、对账、互加好友都要用，必须让用户看得见。
            // 后端保证这个值始终有效（未执行升级脚本时会按公式现算），所以这里不做隐藏。
            // 做成浅色底 + 加粗数字，手机上要一眼看得清。
            // 用长按复制而不是点击：外面这一整行已经绑定了「点击进入编辑资料」。
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(MaterialTheme.colorScheme.surfaceVariant)
                    .combinedClickable(
                        onClick = {},
                        onLongClick = { copyToClipboard(context, data.user.no.toString()) },
                    )
                    .padding(horizontal = 10.dp, vertical = 5.dp),
            ) {
                Text(
                    text = "ID",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    text = data.user.no.toString(),
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = "长按复制",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null, tint = MaterialTheme.colorScheme.outline)
    }
}

@Composable
private fun WalletCard(data: UserCenterDto, onOpenWallet: () -> Unit, onOpenTransactions: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primary),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("账户余额（元）", style = MaterialTheme.typography.labelMedium, color = Color.White.copy(alpha = 0.85f))
            Spacer(Modifier.height(4.dp))
            Text(
                text = data.wallet.balanceText,
                fontSize = 30.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White,
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = "冻结 ${data.wallet.frozenText}",
                style = MaterialTheme.typography.labelSmall,
                color = Color.White.copy(alpha = 0.8f),
            )
            Spacer(Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedButton(onClick = onOpenWallet, modifier = Modifier.weight(1f)) {
                    Text("充值 / 提现", color = Color.White)
                }
                OutlinedButton(onClick = onOpenTransactions, modifier = Modifier.weight(1f)) {
                    Text("资金流水", color = Color.White)
                }
            }
        }
    }
}

@Composable
private fun OrderCountsRow(data: UserCenterDto, onGoOrders: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 12.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(MaterialTheme.colorScheme.surface)
            .clickable(onClick = onGoOrders)
            .padding(vertical = 14.dp),
        horizontalArrangement = Arrangement.SpaceEvenly,
    ) {
        CountCell("待支付", data.counts.pendingPay)
        CountCell("待接单", data.counts.pendingAccept)
        CountCell("服务中", data.counts.serving)
        CountCell("已完成", data.counts.completed)
    }
}

@Composable
private fun CountCell(label: String, value: Int) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            text = "$value",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun CompanionWorkCard(
    data: UserCenterDto,
    toggling: Boolean,
    onToggle: (Boolean) -> Unit,
    onOpenCenter: () -> Unit,
    onOpenHall: () -> Unit,
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("接单状态", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
                    Text(
                        text = if (data.companion.online) "接单中，用户可以看到你" else "休息中，暂不展示在列表前排",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Switch(
                    checked = data.companion.online,
                    onCheckedChange = onToggle,
                    enabled = !toggling,
                )
            }

            if (data.compStat != null) {
                Spacer(Modifier.height(12.dp))
                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
                Spacer(Modifier.height(12.dp))
                Row(horizontalArrangement = Arrangement.SpaceEvenly, modifier = Modifier.fillMaxWidth()) {
                    IncomeCell("今日收入", data.compStat.todayIncome)
                    IncomeCell("累计收入", data.compStat.totalIncome)
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = "${data.compStat.completedOrders}",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                        )
                        Text("完成单数", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }

            Spacer(Modifier.height(12.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedButton(onClick = onOpenCenter, modifier = Modifier.weight(1f)) { Text("陪玩中心") }
                OutlinedButton(onClick = onOpenHall, modifier = Modifier.weight(1f)) { Text("接单大厅") }
            }
        }
    }
}

@Composable
private fun IncomeCell(label: String, value: Double) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        MoneyText(text = String.format("%.2f", value), fontSize = 17.sp)
        Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun ApplyCard(auditText: String, auditRemark: String, onApply: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text("成为陪玩", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(4.dp))
            Text(
                text = when {
                    auditText == "未申请" -> "上架你的服务项目，接单赚钱"
                    else -> "当前状态：$auditText"
                },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            if (auditRemark.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = "平台备注：$auditRemark",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.error,
                )
            }
            Spacer(Modifier.height(12.dp))
            OutlinedButton(onClick = onApply, modifier = Modifier.fillMaxWidth()) {
                Text(if (auditText == "未申请") "立即申请入驻" else "查看 / 修改申请")
            }
        }
    }
}

@Composable
private fun EntryRow(
    text: String,
    onClick: () -> Unit,
    danger: Boolean = false,
    showDivider: Boolean = true,
) {
    Column {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable(onClick = onClick)
                .padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = text,
                style = MaterialTheme.typography.bodyMedium,
                color = if (danger) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            Icon(
                imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.outline,
                modifier = Modifier.size(18.dp),
            )
        }
        if (showDivider) {
            HorizontalDivider(
                modifier = Modifier.padding(start = 16.dp),
                color = MaterialTheme.colorScheme.outlineVariant,
            )
        }
    }
}

@Composable
private fun CompanionIntro(text: String) {
    Column(Modifier.padding(16.dp)) {
        Text("成为陪玩能做什么", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(6.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}
