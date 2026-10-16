package com.wolaitongniwan.myapplication.ui.ticket

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.CreateTicketRequest
import com.wolaitongniwan.myapplication.data.remote.dto.IdRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ReplyTicketRequest
import com.wolaitongniwan.myapplication.data.remote.dto.TicketCategoriesDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.ListState
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

// ---------------------------------------------------------------- 工单列表

class TicketListViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow(ListState<TicketDto>())
    val state: StateFlow<ListState<TicketDto>> = _state.asStateFlow()

    private val _openCount = MutableStateFlow(0)
    val openCount: StateFlow<Int> = _openCount.asStateFlow()

    private var loadJob: Job? = null
    private val perPage = 10

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
            val result = ApiCall.call { api.ticketList(page = page, perPage = perPage) }
            _openCount.value = result.openCount
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

// ---------------------------------------------------------------- 工单详情

class TicketDetailViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<TicketDetailDto>>(UiState.Loading)
    val state: StateFlow<UiState<TicketDetailDto>> = _state.asStateFlow()

    private val _sending = MutableStateFlow(false)
    val sending: StateFlow<Boolean> = _sending.asStateFlow()

    private val _sent = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val sent: SharedFlow<Unit> = _sent

    private var ticketId: Long = 0

    fun load(id: Long) {
        ticketId = id
        if (_state.value !is UiState.Success) {
            _state.value = UiState.Loading
        }
        refresh()
    }

    fun refresh() {
        viewModelScope.launch { fetch() }
    }

    private suspend fun fetch() {
        if (ticketId <= 0) return
        try {
            _state.value = UiState.Success(ApiCall.call { api.ticketDetail(ticketId) })
        } catch (e: ApiException) {
            if (_state.value !is UiState.Success) {
                _state.value = UiState.Failure(e.message)
            }
            notify(e.message)
        }
    }

    fun reply(content: String) {
        val text = content.trim()
        if (text.isEmpty() || _sending.value) return
        _sending.value = true
        viewModelScope.launch {
            try {
                ApiCall.callWithMessage { api.replyTicket(ReplyTicketRequest(ticketId, text)) }
                _sent.tryEmit(Unit)
                fetch()
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _sending.value = false
            }
        }
    }

    fun close() {
        if (_sending.value) return
        _sending.value = true
        viewModelScope.launch {
            try {
                val (_, msg) = ApiCall.callWithMessage { api.closeTicket(IdRequest(ticketId)) }
                notify(msg.ifBlank { "工单已关闭" })
                fetch()
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _sending.value = false
            }
        }
    }
}

// ---------------------------------------------------------------- 提交工单

data class TicketFormState(
    val category: String = "presale",
    val title: String = "",
    val content: String = "",
    val submitting: Boolean = false,
)

class TicketCreateViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _categories = MutableStateFlow(TicketCategoriesDto())
    val categories: StateFlow<TicketCategoriesDto> = _categories.asStateFlow()

    private val _form = MutableStateFlow(TicketFormState())
    val form: StateFlow<TicketFormState> = _form.asStateFlow()

    private val _created = MutableSharedFlow<Long>(extraBufferCapacity = 1)
    val created: SharedFlow<Long> = _created

    init { loadCategories() }

    fun loadCategories() {
        viewModelScope.launch {
            try {
                _categories.value = ApiCall.call { api.ticketCategories() }
            } catch (_: ApiException) {
                // 分类拉不到时用默认值兜底（后端 presale 是默认项）
            }
        }
    }

    fun setCategory(value: String) = _form.update { it.copy(category = value) }
    fun setTitle(value: String) = _form.update { it.copy(title = value.take(100)) }
    fun setContent(value: String) = _form.update { it.copy(content = value.take(1000)) }

    fun submit(orderId: Long) {
        val form = _form.value
        if (form.submitting) return
        if (form.title.isBlank()) return notify("请填写标题")
        if (form.content.trim().length < 5) return notify("请把问题描述清楚一些（至少 5 个字）")

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (data, msg) = ApiCall.callWithMessage {
                    api.createTicket(
                        CreateTicketRequest(
                            category = form.category,
                            title = form.title.trim(),
                            content = form.content.trim(),
                            orderId = orderId,
                        )
                    )
                }
                notify(msg.ifBlank { "工单已提交" })
                _created.tryEmit(data.ticket?.id ?: 0L)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }
}
