package com.wolaitongniwan.myapplication.ui.mine

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.ToggleOnlineRequest
import com.wolaitongniwan.myapplication.data.remote.dto.UserCenterDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class MineViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }
    private val tokenStore by lazy { AppContainer.tokenStore }

    private val _state = MutableStateFlow<UiState<UserCenterDto>>(UiState.Loading)
    val state: StateFlow<UiState<UserCenterDto>> = _state.asStateFlow()

    private val _toggling = MutableStateFlow(false)
    val toggling: StateFlow<Boolean> = _toggling.asStateFlow()

    init {
        load()
    }

    /** 下拉刷新 / 切回本页时用：不切 Loading，保留已有内容，只在顶部转圈 */
    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    fun refresh() {
        viewModelScope.launch {
            _refreshing.value = true
            try {
                _state.value = UiState.Success(ApiCall.call { api.userCenter() })
            } catch (e: ApiException) {
                // 已经有内容时静默失败：下拉刷新不该把整页替换成错误页
                if (_state.value !is UiState.Success) {
                    _state.value = UiState.Failure(e.message)
                }
            } finally {
                _refreshing.value = false
            }
        }
    }

    fun load() {
        if (!Session.isLoggedIn() && !AppContainer.isLoggedIn()) {
            _state.value = UiState.Failure("未登录")
            return
        }
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.userCenter() }
                _state.value = UiState.Success(data)
                // 同步全局用户态，让底部导航的「接单」Tab 及时出现/消失
                refreshSession()
            } catch (e: ApiException) {
                _state.value = UiState.Failure(e.message)
            }
        }
    }

    /** 一键上下线（只有已通过审核的陪玩能调） */
    fun toggleOnline(online: Boolean) {
        if (_toggling.value) return
        _toggling.value = true
        viewModelScope.launch {
            try {
                val (result, msg) = ApiCall.callWithMessage { api.toggleOnline(ToggleOnlineRequest(online)) }
                notify(msg.ifBlank { if (result.online) "已开始接单" else "已暂停接单" })
                load()
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _toggling.value = false
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            // 先通知服务端撤销令牌；网络失败也要在本地退干净，不能把用户卡在登录态
            try {
                ApiCall.callUnit { api.logout() }
            } catch (_: ApiException) {
            }
            tokenStore.clear()
            Session.update(null)
            // 清空全局登录态后，MainScreen 会把「我的」页切成未登录态，无需再跳转
            _state.value = UiState.Failure("未登录")
        }
    }

    private suspend fun refreshSession() {
        try {
            Session.update(ApiCall.call { api.me() })
        } catch (_: ApiException) {
            // 拉不到就沿用旧状态，不打扰用户
        }
    }
}
