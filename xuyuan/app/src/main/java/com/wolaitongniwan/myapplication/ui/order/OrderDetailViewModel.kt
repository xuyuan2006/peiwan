package com.wolaitongniwan.myapplication.ui.order

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.ApiResponse
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.IdReasonRequest
import com.wolaitongniwan.myapplication.data.remote.dto.IdRequest
import com.wolaitongniwan.myapplication.data.remote.dto.OrderDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderWrapDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class OrderDetailViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    // 直接用接口响应类型 OrderDetailDto，不再另建 UI 包装类。
    // 之前这里有个 OrderDetailState，字段与 OrderDetailDto 完全相同（order/logs/chatOpen/unread），
    // 结果屏幕层收到的是前者、而 composable 的参数声明的是后者，编译期类型不匹配：
    //   Argument type mismatch: actual type is 'OrderDetailState', but 'OrderDetailDto' was expected.
    private val _state = MutableStateFlow<UiState<OrderDetailDto>>(UiState.Loading)
    val state: StateFlow<UiState<OrderDetailDto>> = _state.asStateFlow()

    /** 有操作正在进行（按钮转圈 + 防重复点击） */
    private val _acting = MutableStateFlow(false)
    val acting: StateFlow<Boolean> = _acting.asStateFlow()

    private var orderId: Long = 0

    fun load(id: Long) {
        orderId = id
        // 已有数据时不要切回 Loading，避免下拉刷新时页面闪一下
        if (_state.value !is UiState.Success) {
            _state.value = UiState.Loading
        }
        viewModelScope.launch { fetch() }
    }

    fun refresh() {
        viewModelScope.launch { fetch() }
    }

    private suspend fun fetch() {
        if (orderId <= 0) return
        try {
            val data = ApiCall.call { api.orderDetail(orderId) }
            // data 本身就是 OrderDetailDto，直接交给 UI
            _state.value = UiState.Success(data)
        } catch (e: ApiException) {
            if (_state.value !is UiState.Success) {
                _state.value = UiState.Failure(e.message)
            }
            notify(e.message)
        }
    }

    // ---------------------------------------------------------------- 状态流转

    fun pay() = act("支付成功") { api.payOrder(IdRequest(orderId)) }

    fun cancel(reason: String) = act("订单已处理") {
        api.cancelOrder(IdReasonRequest(orderId, reason.ifBlank { "用户取消订单" }))
    }

    fun refund(reason: String) = act("退款申请已提交") {
        api.refundOrder(IdReasonRequest(orderId, reason))
    }

    fun confirm() = act("已确认完成") { api.confirmOrder(IdRequest(orderId)) }

    /** 陪玩侧：订单被指派给我时确认接单 */
    fun accept() = act("已接单") { api.acceptOrder(IdRequest(orderId)) }

    /** 陪玩侧：提交服务完成，等待用户确认（超时后系统自动结算） */
    fun finish() = act("已提交完成") { api.finishOrder(IdRequest(orderId)) }

    /**
     * 统一的写操作包装。
     *
     * 无论成功失败都会重新拉一次详情 —— 支付超时这类「失败」，
     * 服务端其实已经把订单落库成「已关闭」了，界面必须跟着变。
     */
    private fun act(fallback: String, block: suspend () -> ApiResponse<OrderWrapDto>) {
        if (_acting.value) return
        _acting.value = true
        viewModelScope.launch {
            // 只有「服务端确实处理过」才需要重新拉详情：
            // 网络不通时服务端没收到请求，再拉一次也只是白白多一个失败请求。
            var serverReached = false
            try {
                val (_, msg) = ApiCall.callWithMessage(block)
                serverReached = true
                notify(msg.ifBlank { fallback })
            } catch (e: ApiException) {
                // 业务错误（如支付超时）说明服务端已把订单改成「已关闭」，必须刷新
                serverReached = !e.isNetwork
                notify(e.message)
            } finally {
                _acting.value = false
                if (serverReached) fetch()
            }
        }
    }
}
