package com.wolaitongniwan.myapplication.ui.wallet

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.OptionDto
import com.wolaitongniwan.myapplication.data.remote.dto.TransactionDto
import com.wolaitongniwan.myapplication.data.remote.dto.WalletDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.ListState
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class TransactionsViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<TransactionDto>())
    val state: StateFlow<ListState<TransactionDto>> = _state.asStateFlow()

    private val _wallet = MutableStateFlow(WalletDto())
    val wallet: StateFlow<WalletDto> = _wallet.asStateFlow()

    private val _typeOptions = MutableStateFlow<List<OptionDto>>(emptyList())
    val typeOptions: StateFlow<List<OptionDto>> = _typeOptions.asStateFlow()

    /** 空串 = 全部类型 */
    private val _type = MutableStateFlow("")
    val type: StateFlow<String> = _type.asStateFlow()

    /** 0 = 全部，1 = 收入，2 = 支出 */
    private val _direction = MutableStateFlow(0)
    val direction: StateFlow<Int> = _direction.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 20

    init {
        refresh()
    }

    fun setType(value: String) {
        if (_type.value == value) return
        _type.value = value
        refresh()
    }

    fun setDirection(value: Int) {
        if (_direction.value == value) return
        _direction.value = value
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
            val result = ApiCall.call {
                api.transactions(
                    type = _type.value.takeIf { it.isNotBlank() },
                    direction = _direction.value.takeIf { it > 0 },
                    page = page,
                    perPage = perPage,
                )
            }
            _wallet.value = result.wallet
            if (_typeOptions.value.isEmpty() && result.typeOptions.isNotEmpty()) {
                _typeOptions.value = result.typeOptions
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
