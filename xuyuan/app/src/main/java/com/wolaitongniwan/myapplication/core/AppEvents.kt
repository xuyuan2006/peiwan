package com.wolaitongniwan.myapplication.core

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow

/**
 * 全局事件总线（极简版）。
 *
 * 目前只有一个用途：任何请求收到 401 时，通知 UI「登录态失效了，请回登录页」。
 * 放在这里是为了让网络拦截器（非 Compose 环境）能把事件抛给界面层。
 */
object AppEvents {

    private val _sessionExpired = MutableSharedFlow<String>(extraBufferCapacity = 1)

    /** UI 层收集它：收到就清 token 并跳登录页 */
    val sessionExpired: SharedFlow<String> = _sessionExpired

    /** 由网络拦截器调用；用 tryEmit 保证绝不阻塞 OkHttp 线程 */
    fun emitSessionExpired(message: String = "登录已失效，请重新登录") {
        _sessionExpired.tryEmit(message)
    }
}
