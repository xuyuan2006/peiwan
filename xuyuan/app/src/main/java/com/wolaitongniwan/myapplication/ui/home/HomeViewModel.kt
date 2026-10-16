package com.wolaitongniwan.myapplication.ui.home

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.HomeDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class HomeViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<HomeDto>>(UiState.Loading)
    val state: StateFlow<UiState<HomeDto>> = _state.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = UiState.Loading
        fetch()
    }

    /** 下拉刷新：不切回 Loading，避免列表闪一下 */
    fun refresh() {
        _refreshing.value = true
        fetch()
    }

    private fun fetch() {
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.homeIndex() }
                _state.value = UiState.Success(data)
            } catch (e: ApiException) {
                // 刷新失败时如果已有数据，就只提示、不清空页面
                if (_state.value !is UiState.Success || !e.isNetwork) {
                    _state.value = UiState.Failure(e.message)
                }
                notify(e.message)
            } finally {
                _refreshing.value = false
            }
        }
    }
}
