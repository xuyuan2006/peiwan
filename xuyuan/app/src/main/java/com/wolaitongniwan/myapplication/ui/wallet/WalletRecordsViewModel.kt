package com.wolaitongniwan.myapplication.ui.wallet

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeDto
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.ListState
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** 充值记录 */
class RechargeListViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<RechargeDto>())
    val state: StateFlow<ListState<RechargeDto>> = _state.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 20

    init { refresh() }

    fun refresh() {
        loadJob?.cancel()
        _state.update { it.copy(loading = it.items.isEmpty(), refreshing = it.items.isNotEmpty(), error = null) }
        loadJob = viewModelScope.launch { fetch(1, false) }
    }

    fun loadMore() {
        val current = _state.value
        if (!current.canLoadMore) return
        _state.update { it.copy(loadingMore = true) }
        loadJob = viewModelScope.launch { fetch(current.page + 1, true) }
    }

    private suspend fun fetch(page: Int, append: Boolean) {
        try {
            val result = ApiCall.call { api.rechargeList(page = page, perPage = perPage) }
            _state.update { prev ->
                prev.copy(
                    items = if (append) prev.items + result.list else result.list,
                    loading = false, refreshing = false, loadingMore = false,
                    error = null, page = result.page, hasMore = result.hasMore, total = result.total,
                )
            }
        } catch (e: ApiException) {
            _state.update {
                it.copy(
                    loading = false, refreshing = false, loadingMore = false,
                    error = if (append || it.items.isNotEmpty()) null else e.message,
                )
            }
            notify(e.message)
        }
    }
}

/** 提现记录 */
class WithdrawListViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<WithdrawDto>())
    val state: StateFlow<ListState<WithdrawDto>> = _state.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 20

    init { refresh() }

    fun refresh() {
        loadJob?.cancel()
        _state.update { it.copy(loading = it.items.isEmpty(), refreshing = it.items.isNotEmpty(), error = null) }
        loadJob = viewModelScope.launch { fetch(1, false) }
    }

    fun loadMore() {
        val current = _state.value
        if (!current.canLoadMore) return
        _state.update { it.copy(loadingMore = true) }
        loadJob = viewModelScope.launch { fetch(current.page + 1, true) }
    }

    private suspend fun fetch(page: Int, append: Boolean) {
        try {
            val result = ApiCall.call { api.withdrawList(page = page, perPage = perPage) }
            _state.update { prev ->
                prev.copy(
                    items = if (append) prev.items + result.list else result.list,
                    loading = false, refreshing = false, loadingMore = false,
                    error = null, page = result.page, hasMore = result.hasMore, total = result.total,
                )
            }
        } catch (e: ApiException) {
            _state.update {
                it.copy(
                    loading = false, refreshing = false, loadingMore = false,
                    error = if (append || it.items.isNotEmpty()) null else e.message,
                )
            }
            notify(e.message)
        }
    }
}

/** 充值单详情（待支付的在线单可以在这里继续支付） */
class RechargeDetailViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<RechargeDto>>(UiState.Loading)
    val state: StateFlow<UiState<RechargeDto>> = _state.asStateFlow()

    private val _payResult = MutableStateFlow<RechargeResultDto?>(null)
    val payResult: StateFlow<RechargeResultDto?> = _payResult.asStateFlow()

    private val _paying = MutableStateFlow(false)
    val paying: StateFlow<Boolean> = _paying.asStateFlow()

    private var rechargeId: Long = 0

    fun load(id: Long) {
        rechargeId = id
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.rechargeDetail(id) }
                val recharge = data.recharge
                // 不要写成 data.recharge?.let { ... } ?: run { ... }：
                // let 返回的是 Unit（非空），elvis 永远不会触发，既难看又容易被后人改错。
                _state.value = if (recharge != null) {
                    UiState.Success(recharge)
                } else {
                    UiState.Failure("充值单不存在")
                }
            } catch (e: ApiException) {
                _state.value = UiState.Failure(e.message)
            }
        }
    }

    /** 继续支付：重新向后端要一次支付链接 / 二维码 */
    fun continuePay() {        if (_paying.value || rechargeId <= 0) return
        _paying.value = true
        viewModelScope.launch {
            try {
                _payResult.value = ApiCall.call { api.rechargePay(rechargeId) }
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _paying.value = false
            }
        }
    }

    /** 取消未支付的充值单。成功后重新拉一次，状态会变成「已关闭」。 */
    fun cancel() {
        if (_paying.value || rechargeId <= 0) return
        _paying.value = true
        viewModelScope.launch {
            try {
                ApiCall.callUnit { api.rechargeCancel(rechargeId) }
                notify("充值单已取消")
                load(rechargeId)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _paying.value = false
            }
        }
    }

    fun consumePayResult() {
        _payResult.value = null
    }
}
