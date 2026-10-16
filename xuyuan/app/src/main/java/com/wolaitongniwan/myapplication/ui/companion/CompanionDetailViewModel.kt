package com.wolaitongniwan.myapplication.ui.companion

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.CreateOrderRequest
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class CompanionDetailViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<CompanionDetailDto>>(UiState.Loading)
    val state: StateFlow<UiState<CompanionDetailDto>> = _state.asStateFlow()

    private val _ordering = MutableStateFlow(false)
    val ordering: StateFlow<Boolean> = _ordering.asStateFlow()

    /** 下单成功 → 交给界面跳转到订单详情 */
    private val _orderCreated = MutableSharedFlow<Long>(extraBufferCapacity = 1)
    val orderCreated: SharedFlow<Long> = _orderCreated

    private var companionId: Long = 0

    fun load(id: Long) {
        companionId = id
        _state.value = UiState.Loading
        viewModelScope.launch {
            try {
                _state.value = UiState.Success(ApiCall.call { api.companionDetail(id) })
            } catch (e: ApiException) {
                _state.value = UiState.Failure(e.message)
            }
        }
    }

    /** 下单；未登录时由界面拦截并跳登录页 */
    fun createOrder(skillId: Long, quantity: Int, remark: String) {
        if (_ordering.value) return
        if (!Session.isLoggedIn()) {
            notify("请先登录后再下单")
            return
        }
        _ordering.value = true
        viewModelScope.launch {
            try {
                val (result, msg) = ApiCall.callWithMessage {
                    api.createOrder(
                        CreateOrderRequest(
                            skillId = skillId,
                            quantity = quantity,
                            remark = remark,
                        )
                    )
                }
                notify(msg.ifBlank { "下单成功" })
                result.order?.let { _orderCreated.tryEmit(it.id) }
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _ordering.value = false
            }
        }
    }
}
