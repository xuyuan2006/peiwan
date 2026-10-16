package com.wolaitongniwan.myapplication.core

import android.content.Context
import com.wolaitongniwan.myapplication.BuildConfig
import com.wolaitongniwan.myapplication.data.local.TokenStore
import com.wolaitongniwan.myapplication.data.remote.ApiClient
import com.wolaitongniwan.myapplication.data.remote.ApiService

/**
 * 极简依赖容器（手写 DI）。
 *
 * 没有引入 Hilt/Koin：这个规模的 App 用一个 object 就够了，
 * 而且省掉注解处理器能让构建更快、更少踩版本兼容的坑。
 */
object AppContainer {

    lateinit var tokenStore: TokenStore
        private set

    lateinit var api: ApiService
        private set

    /** 在 Application.onCreate 里调用一次 */
    fun init(context: Context) {
        val app = context.applicationContext

        // TokenStore 用 SharedPreferences，构造完就能同步读，不需要 runBlocking，
        // 也就不会有「主线程等磁盘 IO」导致的启动卡顿。
        tokenStore = TokenStore(app)

        api = ApiClient.build(
            baseUrl = BuildConfig.API_BASE_URL,
            tokenProvider = { tokenStore.token() },
            onUnauthorized = { AppEvents.emitSessionExpired() },
        )
    }

    /** 是否已登录（本地有令牌） */
    fun isLoggedIn(): Boolean = ::tokenStore.isInitialized && tokenStore.hasToken()
}
