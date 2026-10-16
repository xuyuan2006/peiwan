package com.wolaitongniwan.myapplication.ui.order

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.GameDto
import com.wolaitongniwan.myapplication.data.remote.dto.IdRequest
import com.wolaitongniwan.myapplication.data.remote.dto.OrderDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.ListState
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/**
 * 接单大厅。
 *
 * 只有「已付款 + 公开 + 还没人接」的订单会出现在这里；
 * 抢单成功后台会把订单归属给我并直接进入「服务中」。
 */
class HallViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<OrderDto>())
    val state: StateFlow<ListState<OrderDto>> = _state.asStateFlow()

    private val _games = MutableStateFlow<List<GameDto>>(emptyList())
    val games: StateFlow<List<GameDto>> = _games.asStateFlow()

    private val _gameId = MutableStateFlow(0L)
    val gameId: StateFlow<Long> = _gameId.asStateFlow()

    private val _grabbing = MutableStateFlow(0L)
    val grabbing: StateFlow<Long> = _grabbing.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 12

    init {
        refresh()
    }

    fun setGame(id: Long) {
        if (_gameId.value == id) return
        _gameId.value = id
        refresh()
    }

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

    /** 抢单；成功/失败都刷新，因为「手慢了」意味着列表已经变了 */
    fun grab(orderId: Long) {
        if (_grabbing.value != 0L) return
        _grabbing.value = orderId
        viewModelScope.launch {
            try {
                val (_, msg) = ApiCall.callWithMessage { api.grabOrder(IdRequest(orderId)) }
                notify(msg.ifBlank { "抢单成功" })
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _grabbing.value = 0L
                refresh()
            }
        }
    }

    private suspend fun fetch(page: Int, append: Boolean) {
        try {
            val result = ApiCall.call {
                api.hall(
                    gameId = _gameId.value.takeIf { it > 0 },
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
                    error = if (append || it.items.isNotEmpty()) null else e.message,
                )
            }
            notify(e.message)
        }
    }
}
