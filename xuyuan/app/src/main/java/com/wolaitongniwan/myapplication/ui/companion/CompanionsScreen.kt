package com.wolaitongniwan.myapplication.ui.companion

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.text.input.ImeAction
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.ui.components.AutoRefresh
import com.wolaitongniwan.myapplication.ui.components.ListStateBox
import com.wolaitongniwan.myapplication.ui.components.LoadMoreFooter
import com.wolaitongniwan.myapplication.ui.components.MessageEffect

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CompanionsScreen(
    onOpenCompanion: (Long) -> Unit,
    vm: CompanionsViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val games by vm.games.collectAsStateWithLifecycle()
    val filter by vm.filter.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }
    val listState = rememberLazyListState()

    MessageEffect(vm.messages, snackbar)

    // 每次进入本页（含点击底部 Tab）都重拉一次数据，保证看到的是最新状态
    LaunchedEffect(Unit) { vm.refresh() }

    // 停留在这个页面期间，每 5 秒自动向服务器同步一次；切到后台会自动暂停
    AutoRefresh { vm.refresh() }

    // 关键词输入本地保存，回车/失焦时才真正请求，避免每敲一个字打一次接口
    var keywordInput by remember { mutableStateOf(filter.keyword) }

    // 滚到接近底部自动加载下一页
    val shouldLoadMore by remember {
        derivedStateOf {
            val info = listState.layoutInfo
            val last = info.visibleItemsInfo.lastOrNull()?.index ?: -1
            info.totalItemsCount > 0 && last >= info.totalItemsCount - 3
        }
    }
    LaunchedEffect(shouldLoadMore) {
        if (shouldLoadMore) vm.loadMore()
    }

    Column(Modifier.fillMaxSize()) {
        // ---------------- 搜索框 ----------------
        OutlinedTextField(
            value = keywordInput,
            onValueChange = { keywordInput = it },
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 8.dp),
            placeholder = { Text("搜索昵称 / 简介 / 标签 / 城市") },
            singleLine = true,
            leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
            trailingIcon = {
                TextButton(onClick = { vm.setKeyword(keywordInput.trim()) }) { Text("搜索") }
            },
            // 软键盘上也给一个「搜索」键，否则用户敲完只能去点右上角
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
            keyboardActions = KeyboardActions(onSearch = { vm.setKeyword(keywordInput.trim()) }),
        )

        // ---------------- 筛选栏 ----------------
        FilterBar(
            games = games,
            filter = filter,
            onGame = vm::setGame,
            onGender = vm::setGender,
            onOnlineOnly = vm::setOnlineOnly,
            onSort = vm::setSort,
            onReset = {
                keywordInput = ""
                vm.resetFilter()
            },
        )

        Box(Modifier.fillMaxSize()) {
            PullToRefreshBox(
                isRefreshing = state.refreshing,
                onRefresh = vm::refresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                ListStateBox(
                    state = state,
                    emptyText = "没有符合条件的陪玩，换个筛选条件试试",
                    onRetry = vm::refresh,
                ) { list ->
                    LazyColumn(
                        state = listState,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        items(list, key = { it.userId }) { companion ->
                            CompanionCard(
                                item = companion,
                                onClick = { onOpenCompanion(companion.userId) },
                                modifier = Modifier.padding(horizontal = 12.dp),
                            )
                        }
                        item {
                            LoadMoreFooter(
                                loadingMore = state.loadingMore,
                                hasMore = state.hasMore,
                            )
                        }
                    }
                }
            }

            SnackbarHost(
                hostState = snackbar,
                modifier = Modifier.align(Alignment.BottomCenter),
            )
        }
    }
}

@Composable
private fun FilterBar(
    games: List<com.wolaitongniwan.myapplication.data.remote.dto.GameDto>,
    filter: CompanionFilter,
    onGame: (Long) -> Unit,
    onGender: (Int) -> Unit,
    onOnlineOnly: (Boolean) -> Unit,
    onSort: (String) -> Unit,
    onReset: () -> Unit,
) {
    var sortExpanded by remember { mutableStateOf(false) }

    Column(Modifier.fillMaxWidth()) {
        // 游戏
        LazyRow(
            contentPadding = PaddingValues(horizontal = 12.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            item {
                FilterChip(
                    selected = filter.gameId == 0L,
                    onClick = { onGame(0) },
                    label = { Text("全部游戏") },
                )
            }
            items(games, key = { it.id }) { game ->
                FilterChip(
                    selected = filter.gameId == game.id,
                    onClick = { onGame(game.id) },
                    label = { Text(game.name) },
                )
            }
        }

        Spacer(Modifier.height(6.dp))

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            // 性别
            listOf(0 to "不限", 1 to "男", 2 to "女").forEach { (value, label) ->
                FilterChip(
                    selected = filter.gender == value,
                    onClick = { onGender(value) },
                    label = { Text(label) },
                )
            }

            // 只看接单中
            FilterChip(
                selected = filter.onlineOnly,
                onClick = { onOnlineOnly(!filter.onlineOnly) },
                label = { Text("接单中") },
            )

            Spacer(Modifier.weight(1f))

            // 排序
            Box {
                Text(
                    text = SortOptions.firstOrNull { it.first == filter.sort }?.second ?: "综合推荐",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier
                        .padding(horizontal = 4.dp)
                        .clickable { sortExpanded = true },
                )
                DropdownMenu(
                    expanded = sortExpanded,
                    onDismissRequest = { sortExpanded = false },
                ) {
                    SortOptions.forEach { (value, label) ->
                        DropdownMenuItem(
                            text = { Text(label) },
                            onClick = {
                                sortExpanded = false
                                onSort(value)
                            },
                        )
                    }
                    DropdownMenuItem(
                        text = { Text("重置全部筛选") },
                        onClick = {
                            sortExpanded = false
                            onReset()
                        },
                    )
                }
            }
        }

        Spacer(Modifier.height(4.dp))
    }
}
