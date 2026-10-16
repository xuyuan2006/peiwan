package com.wolaitongniwan.myapplication.ui.companion

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionDto
import com.wolaitongniwan.myapplication.data.remote.dto.GameDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.ListState
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** 陪玩列表的筛选条件（与后端 home/companions 的参数一一对应） */
data class CompanionFilter(
    val gameId: Long = 0,
    val gender: Int = 0,
    val sort: String = "default",
    val onlineOnly: Boolean = false,
    val keyword: String = "",
)

/** 排序选项（值必须与后端约定一致） */
val SortOptions = listOf(
    "default" to "综合推荐",
    "hot" to "接单最多",
    "score" to "评分最高",
    "price_asc" to "价格从低到高",
    "price_desc" to "价格从高到低",
    "new" to "最新入驻",
)

class CompanionsViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<CompanionDto>())
    val state: StateFlow<ListState<CompanionDto>> = _state.asStateFlow()

    private val _games = MutableStateFlow<List<GameDto>>(emptyList())
    val games: StateFlow<List<GameDto>> = _games.asStateFlow()

    private val _filter = MutableStateFlow(CompanionFilter())
    val filter: StateFlow<CompanionFilter> = _filter.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 12

    init {
        refresh()
    }

    // ---------------------------------------------------------------- 筛选

    fun setGame(id: Long) = applyFilter { it.copy(gameId = id) }
    fun setGender(gender: Int) = applyFilter { it.copy(gender = gender) }
    fun setSort(sort: String) = applyFilter { it.copy(sort = sort) }
    fun setOnlineOnly(value: Boolean) = applyFilter { it.copy(onlineOnly = value) }
    fun setKeyword(value: String) = applyFilter { it.copy(keyword = value) }

    fun resetFilter() = applyFilter { CompanionFilter() }

    private fun applyFilter(transform: (CompanionFilter) -> CompanionFilter) {
        _filter.update(transform)
        refresh()
    }

    // ---------------------------------------------------------------- 加载

    fun refresh() {
        loadJob?.cancel()
        _state.update { it.copy(loading = it.items.isEmpty(), refreshing = it.items.isNotEmpty(), error = null) }
        loadJob = viewModelScope.launch { fetch(page = 1, append = false) }
    }

    fun loadMore() {
        val current = _state.value
        if (!current.canLoadMore) return
        _state.update { it.copy(loadingMore = true) }
        loadJob = viewModelScope.launch { fetch(page = current.page + 1, append = true) }
    }

    private suspend fun fetch(page: Int, append: Boolean) {
        val f = _filter.value
        try {
            val result = ApiCall.call {
                api.companions(
                    gameId = f.gameId.takeIf { it > 0 },
                    gender = f.gender.takeIf { it > 0 },
                    keyword = f.keyword.trim().takeIf { it.isNotEmpty() },
                    sort = f.sort,
                    onlineOnly = if (f.onlineOnly) 1 else 0,
                    page = page,
                    perPage = perPage,
                )
            }
            if (_games.value.isEmpty() && result.games.isNotEmpty()) {
                _games.value = result.games
            }
            _state.update { prev ->
                prev.copy(
                    items = if (append) prev.items + result.list else result.list,
                    loading = false,
                    refreshing = false,
                    loadingMore = false,
                    error = null,
                    page = result.page,
                    hasMore = result.hasMore,
                    total = result.total,
                )
            }
        } catch (e: ApiException) {
            _state.update {
                it.copy(
                    loading = false,
                    refreshing = false,
                    loadingMore = false,
                    // 已经有数据时不要把列表清空换成错误页，只提示
                    error = if (append || it.items.isNotEmpty()) null else e.message,
                )
            }
            notify(e.message)
        }
    }
}
