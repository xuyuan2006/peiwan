package com.wolaitongniwan.myapplication.data.local

import android.content.Context
import android.content.SharedPreferences

/**
 * 登录令牌的本地存储。
 *
 * 为什么用 SharedPreferences 而不是 DataStore：
 * 这里只存两个短字符串，而且必须在 `Application.onCreate` 里**同步**读到 ——
 * OkHttp 拦截器随时要取 token，异步加载会导致冷启动头几个请求「看起来没登录」。
 *
 * SharedPreferences 首次加载后常驻内存，之后的 getString 是纯内存操作，
 * 不会像 DataStore 那样需要在主线程上 runBlocking 等磁盘 IO。
 */
class TokenStore(context: Context) {

    private val prefs: SharedPreferences =
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    /** 供 OkHttp 拦截器同步读取 */
    fun token(): String? = prefs.getString(KEY_TOKEN, null)

    fun expireAt(): String? = prefs.getString(KEY_EXPIRE_AT, null)

    fun hasToken(): Boolean = !token().isNullOrBlank()

    fun save(token: String, expireAt: String) {
        prefs.edit()
            .putString(KEY_TOKEN, token)
            .putString(KEY_EXPIRE_AT, expireAt)
            .apply()
    }

    fun clear() {
        prefs.edit().clear().apply()
    }

    private companion object {
        const val PREFS_NAME = "peiwan_auth"
        const val KEY_TOKEN = "token"
        const val KEY_EXPIRE_AT = "expire_at"
    }
}
