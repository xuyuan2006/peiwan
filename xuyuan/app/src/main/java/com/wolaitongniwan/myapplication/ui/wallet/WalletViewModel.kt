package com.wolaitongniwan.myapplication.ui.wallet

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.WalletOverviewDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class WalletViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<WalletOverviewDto>>(UiState.Loading)
    val state: StateFlow<UiState<WalletOverviewDto>> = _state.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = UiState.Loading
        fetch()
    }

    fun refresh() {
        _refreshing.value = true
        fetch()
    }

    private fun fetch() {
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.walletOverview() }
                _state.value = UiState.Success(data)
                // 钱包变了，同步一下全局状态，避免「我的」页余额对不上
                Session.updateWallet(data.wallet)
            } catch (e: ApiException) {
                if (_state.value !is UiState.Success) {
                    _state.value = UiState.Failure(e.message)
                }
                notify(e.message)
            } finally {
                _refreshing.value = false
            }
        }
    }
}
