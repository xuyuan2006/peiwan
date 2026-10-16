package com.wolaitongniwan.myapplication.ui.common

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * 读取类页面的三态封装。
 * 表单类页面（登录、下单等）用各自的 State 类即可，不必套这个。
 */
sealed interface UiState<out T> {
    data object Loading : UiState<Nothing>
    data class Success<T>(val data: T) : UiState<T>
    data class Failure(val message: String) : UiState<Nothing>
}

/** 分页列表的通用状态：保留已加载数据，同时支持「加载更多」和「刷新」 */
data class ListState<T>(
    val items: List<T> = emptyList(),
    val loading: Boolean = false,       // 首次加载
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val error: String? = null,
    val page: Int = 0,
    val hasMore: Boolean = true,
    val total: Int = 0,
) {
    val isEmpty: Boolean get() = items.isEmpty() && !loading && error == null
    val canLoadMore: Boolean get() = hasMore && !loadingMore && !loading && error == null
}

/**
 * ViewModel 基类：统一「一次性提示」通道。
 *
 * 为什么不用 State 装提示消息：State 在配置变更（旋转屏幕）后会重新渲染，
 * 同一个错误会再弹一次。用 SharedFlow 可以保证每条提示只消费一次。
 */
abstract class BaseViewModel : androidx.lifecycle.ViewModel() {

    private val _messages = MutableSharedFlow<String>(extraBufferCapacity = 4)

    /** UI 层 collect 它来弹 Snackbar / Toast */
    val messages: SharedFlow<String> = _messages

    protected fun notify(message: String) {
        _messages.tryEmit(message)
    }
}
