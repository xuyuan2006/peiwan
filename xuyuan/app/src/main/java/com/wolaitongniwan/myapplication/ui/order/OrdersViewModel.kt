package com.wolaitongniwan.myapplication.ui.order

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.IdReasonRequest
import com.wolaitongniwan.myapplication.data.remote.dto.IdRequest
import com.wolaitongniwan.myapplication.data.remote.dto.OrderCountsDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderWrapDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.ListState
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** 订单状态筛选标签（值必须与后端 order/index 的 status 参数一致） */
val OrderStatusTabs = listOf(
    "all" to "全部",
    "1" to "待支付",
    "2" to "待接单",
    "3" to "服务中",
    "4" to "待确认",
    "5" to "已完成",
    "refund" to "退款",
)

/**
 * 「我的订单」（下单方视角）。
 * @param asCompanion true 时改为请求 companion/orders（接单方视角），复用同一套 UI。
 */
class OrdersViewModel(
    private val asCompanion: Boolean = false,
) : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<OrderDto>())
    val state: StateFlow<ListState<OrderDto>> = _state.asStateFlow()

    private val _counts = MutableStateFlow(OrderCountsDto())
    val counts: StateFlow<OrderCountsDto> = _counts.asStateFlow()

    private val _status = MutableStateFlow("all")
    val status: StateFlow<String> = _status.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 10

    init {
        refresh()
    }

    fun setStatus(value: String) {
        if (_status.value == value) return
        _status.value = value
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

    private suspend fun fetch(page: Int, append: Boolean) {
        try {
            if (asCompanion) {
                val result = ApiCall.call {
                    api.companionOrders(status = _status.value, page = page, perPage = perPage)
                }
                _counts.value = result.counts
                applyResult(result.list, result.page, result.hasMore, result.total, append)
            } else {
                val result = ApiCall.call {
                    api.orderList(status = _status.value, page = page, perPage = perPage)
                }
                _counts.value = result.counts
                applyResult(result.list, result.page, result.hasMore, result.total, append)
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

    private fun applyResult(
        list: List<OrderDto>,
        page: Int,
        hasMore: Boolean,
        total: Int,
        append: Boolean,
    ) {
        _state.update { prev ->
            prev.copy(
                items = if (append) prev.items + list else list,
                loading = false,
                refreshing = false,
                loadingMore = false,
                error = null,
                page = page,
                hasMore = hasMore,
                total = total,
            )
        }
    }

    // ---------------------------------------------------------------- 快捷操作

    /** 正在操作的订单 ID（按钮转圈用），0 表示空闲 */
    private val _acting = MutableStateFlow(0L)
    val acting: StateFlow<Long> = _acting.asStateFlow()

    fun pay(orderId: Long) = act(orderId, "支付成功") { api.payOrder(IdRequest(orderId)) }

    fun cancel(orderId: Long) = act(orderId, "订单已取消") {
        api.cancelOrder(IdReasonRequest(orderId, "用户取消订单"))
    }

    fun confirm(orderId: Long) = act(orderId, "已确认完成") { api.confirmOrder(IdRequest(orderId)) }

    /** 陪玩侧：确认接单 */
    fun accept(orderId: Long) = act(orderId, "已接单") { api.acceptOrder(IdRequest(orderId)) }

    /** 陪玩侧：提交完成 */
    fun finish(orderId: Long) = act(orderId, "已提交完成") { api.finishOrder(IdRequest(orderId)) }

    private fun act(
        orderId: Long,
        fallbackMessage: String,
        block: suspend () -> com.wolaitongniwan.myapplication.core.ApiResponse<OrderWrapDto>,
    ) {
        if (_acting.value != 0L) return
        _acting.value = orderId
        viewModelScope.launch {
            // 服务端到底有没有处理这个请求？
            // 网络不通时它其实没收到，再刷一次只是白白多打一个必然失败的请求。
            var serverReached = false
            try {
                val (_, msg) = ApiCall.callWithMessage(block)
                serverReached = true
                notify(msg.ifBlank { fallbackMessage })
            } catch (e: ApiException) {
                // 业务错误（如「订单已超时关闭」）说明服务端已经处理并改了状态，必须刷新
                serverReached = !e.isNetwork
                notify(e.message)
            } finally {
                _acting.value = 0L
                if (serverReached) refresh()
            }
        }
    }
}
