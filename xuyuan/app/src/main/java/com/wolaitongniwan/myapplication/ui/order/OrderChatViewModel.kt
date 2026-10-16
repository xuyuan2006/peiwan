package com.wolaitongniwan.myapplication.ui.order

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.MessageDto
import com.wolaitongniwan.myapplication.data.remote.dto.SendMessageRequest
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * 订单内聊天。
 *
 * 后端没有 WebSocket，采用「首屏全量 + 之后按 since_id 增量轮询」的方案：
 * 增量请求只返回新消息，代价很小，也不需要维护长连接。
 */
class OrderChatViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    // ⚠️ 不能叫 messages：基类 BaseViewModel 已经有 val messages: SharedFlow<String>（非 open），
    //    子类再声明同名属性会报「hides member of supertype and needs 'override' modifier」，
    //    而基类那个是 final，加 override 同样编不过。所以这里用 chatMessages。
    private val _chatMessages = MutableStateFlow<List<MessageDto>>(emptyList())
    val chatMessages: StateFlow<List<MessageDto>> = _chatMessages.asStateFlow()

    /** 当前状态是否允许发言（服务端按「已接单且服务中/待确认/已完成」判断） */
    private val _chatOpen = MutableStateFlow(true)
    val chatOpen: StateFlow<Boolean> = _chatOpen.asStateFlow()

    private val _loading = MutableStateFlow(true)
    val loading: StateFlow<Boolean> = _loading.asStateFlow()

    private val _sending = MutableStateFlow(false)
    val sending: StateFlow<Boolean> = _sending.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    /** 发送成功后通知界面清空输入框 */
    private val _sent = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val sent: SharedFlow<Unit> = _sent

    private var polling: Job? = null
    private var orderId: Long = 0

    fun start(id: Long) {
        // 换单号时必须先停掉旧循环：否则正在跑的那个循环会突然开始查新单号，
        // 而 _chatMessages 里还留着旧单的消息，since_id 会对不上。
        if (orderId != id) {
            stop()
            _chatMessages.value = emptyList()
            _loading.value = true
            _error.value = null
        }
        orderId = id

        if (polling?.isActive == true) return

        polling = viewModelScope.launch {
            loadOnce(initial = true)
            while (isActive) {
                delay(POLL_INTERVAL_MS)
                loadOnce(initial = false)
            }
        }
    }

    fun stop() {
        polling?.cancel()
        polling = null
    }

    fun send(content: String) {
        val text = content.trim()
        if (text.isEmpty() || _sending.value || orderId <= 0) return

        _sending.value = true
        viewModelScope.launch {
            try {
                ApiCall.callWithMessage { api.sendOrderMessage(SendMessageRequest(orderId, text)) }
                _sent.tryEmit(Unit)
                loadOnce(initial = false)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _sending.value = false
            }
        }
    }

    private suspend fun loadOnce(initial: Boolean) {
        if (orderId <= 0) return
        try {
            val sinceId = if (initial) 0L else (_chatMessages.value.lastOrNull()?.id ?: 0L)
            val data = ApiCall.call { api.orderMessages(orderId, sinceId) }

            _chatOpen.value = data.chatOpen
            _chatMessages.update { prev ->
                if (initial) {
                    data.list
                } else {
                    // 用 id 去重，避免轮询与本地发送撞车时出现重复气泡
                    val known = prev.mapTo(HashSet()) { it.id }
                    prev + data.list.filter { it.id !in known }
                }
            }
            _error.value = null
        } catch (e: ApiException) {
            // 首屏失败要显示错误页；轮询失败保持静默，否则每 3 秒弹一次提示
            if (initial) {
                _error.value = e.message
            }
        } finally {
            if (initial) {
                _loading.value = false
            }
        }
    }

    override fun onCleared() {
        stop()
        super.onCleared()
    }

    private companion object {
        const val POLL_INTERVAL_MS = 3_000L
    }
}
