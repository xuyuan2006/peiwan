package com.wolaitongniwan.myapplication.ui.home

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.data.remote.dto.PackageDto
import com.wolaitongniwan.myapplication.ui.common.UiState
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.StateBox
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.BuyPackageRequest
import com.wolaitongniwan.myapplication.data.remote.dto.GameDto
import com.wolaitongniwan.myapplication.data.remote.dto.PackagePageDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel

// ---------------------------------------------------------------- ViewModel

data class PackagesState(
    val games: List<GameDto> = emptyList(),
    val list: List<PackageDto> = emptyList(),
    val gameId: Long = 0,
)

class PackagesViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<PackagesState>>(UiState.Loading)
    val state: StateFlow<UiState<PackagesState>> = _state.asStateFlow()

    private val _buying = MutableStateFlow(0L)
    val buying: StateFlow<Long> = _buying.asStateFlow()

    /** 下单成功 → 跳订单详情 */
    private val _orderCreated = MutableStateFlow<Long?>(null)
    val orderCreated: StateFlow<Long?> = _orderCreated.asStateFlow()

    init {
        load(0)
    }

    fun load(gameId: Long) {
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.packages(gameId.takeIf { it > 0 }) }
                _state.value = UiState.Success(
                    PackagesState(games = data.games, list = data.list, gameId = gameId)
                )
            } catch (e: ApiException) {
                _state.value = UiState.Failure(e.message)
            }
        }
    }

    /**
     * 一口价下单。
     * 后端在余额够时会自动扣款并进入接单大厅；余额不足则订单留在「待支付」。
     */
    fun buy(pkg: PackageDto) {
        if (_buying.value != 0L) return
        if (!Session.isLoggedIn()) {
            notify("请先登录后再下单")
            return
        }
        _buying.value = pkg.id
        viewModelScope.launch {
            try {
                val (result, msg) = ApiCall.callWithMessage {
                    api.buyPackage(BuyPackageRequest(packageId = pkg.id))
                }
                notify(msg.ifBlank { if (result.paid) "下单成功" else "下单成功，请先充值" })
                result.order?.let { _orderCreated.value = it.id }
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _buying.value = 0L
            }
        }
    }

    fun consumeOrderCreated() {
        _orderCreated.value = null
    }
}

// ---------------------------------------------------------------- Screen

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PackagesScreen(
    onBack: () -> Unit,
    onOpenOrder: (Long) -> Unit,
    onNeedLogin: () -> Unit,
    loggedIn: Boolean,
    vm: PackagesViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val buying by vm.buying.collectAsStateWithLifecycle()
    val created by vm.orderCreated.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    LaunchedEffect(created) {
        created?.let {
            vm.consumeOrderCreated()
            onOpenOrder(it)
        }
    }

    // 未登录时点「立即下单」要引导登录
    var pendingLogin by remember { mutableStateOf(false) }
    LaunchedEffect(pendingLogin) {
        if (pendingLogin) {
            pendingLogin = false
            onNeedLogin()
        }
    }

    AppScaffold(title = "直接点单", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            StateBox(state = state, onRetry = { vm.load(0) }) { data ->
                Column(Modifier.fillMaxSize()) {
                    LazyRow(
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                    ) {
                        item {
                            FilterChip(
                                selected = data.gameId == 0L,
                                onClick = { vm.load(0) },
                                label = { Text("全部") },
                            )
                        }
                        items(data.games, key = { it.id }) { game ->
                            FilterChip(
                                selected = data.gameId == game.id,
                                onClick = { vm.load(game.id) },
                                label = { Text(game.name) },
                            )
                        }
                    }

                    if (data.list.isEmpty()) {
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            Text(
                                text = "暂时没有可点的一口价套餐",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    } else {
                        // 两列网格。
                        // 这里刻意用 items(count = , key = )（LazyGridScope 的成员函数），
                        // 而不是 items(list)：后者是 lazy.grid 包的扩展函数，和上面 LazyRow 用的
                        // lazy.items 同名，两个都 import 会冲突。
                        LazyVerticalGrid(
                            columns = GridCells.Fixed(2),
                            contentPadding = PaddingValues(start = 12.dp, end = 12.dp, bottom = 16.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            items(
                                count = data.list.size,
                                key = { index -> data.list[index].id },
                            ) { index ->
                                val pkg = data.list[index]
                                PackageCard(
                                    pkg = pkg,
                                    busy = buying == pkg.id,
                                    onBuy = {
                                        if (!loggedIn) pendingLogin = true else vm.buy(pkg)
                                    },
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * 网格里的套餐卡片（半屏宽）。
 *
 * 改成两列后横向空间只剩一半，所以做了三处适配：
 *  1. 价格与按钮从「左右并排」改为「上下排列」—— 并排会被挤到换行或截断
 *  2. 封面图从 140dp 压到 104dp，描述只留 2 行
 *  3. 外面不再自己加左右 padding：外边距交给 LazyVerticalGrid 的 contentPadding，
 *     两边都加的话会被缩进两次
 */
@Composable
private fun PackageCard(pkg: PackageDto, busy: Boolean, onBuy: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(Modifier.padding(10.dp)) {
            if (pkg.coverUrl.isNotBlank()) {
                AsyncImage(
                    model = pkg.coverUrl,
                    contentDescription = pkg.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(104.dp)
                        .clip(RoundedCornerShape(10.dp)),
                )
                Spacer(Modifier.height(8.dp))
            }

            Text(
                text = pkg.title,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.SemiBold,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )

            if (pkg.description.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = pkg.description,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }

            Spacer(Modifier.height(10.dp))
            MoneyText(text = pkg.priceText, fontSize = 18.sp)
            Spacer(Modifier.height(6.dp))
            Button(
                onClick = onBuy,
                enabled = !busy,
                modifier = Modifier.fillMaxWidth(),
                contentPadding = PaddingValues(vertical = 6.dp),
            ) {
                Text(
                    text = if (busy) "提交中…" else "立即下单",
                    style = MaterialTheme.typography.labelMedium,
                )
            }
        }
    }
}
