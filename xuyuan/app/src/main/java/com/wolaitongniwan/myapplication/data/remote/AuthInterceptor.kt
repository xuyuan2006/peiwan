package com.wolaitongniwan.myapplication.data.remote

import okhttp3.Interceptor
import okhttp3.Response

/**
 * 给每个请求带上 Bearer 令牌，并在收到 401 时通知全局。
 *
 * 令牌从内存读取（TokenStore 的缓存），所以这里不需要 runBlocking，
 * 不会因为拦截器在 IO 线程上而阻塞或死锁。
 */
class AuthInterceptor(
    private val tokenProvider: () -> String?,
    private val onUnauthorized: () -> Unit,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val builder = chain.request().newBuilder()
            .header("Accept", "application/json")

        tokenProvider()?.takeIf { it.isNotBlank() }?.let { token ->
            builder.header("Authorization", "Bearer $token")
        }

        val response = chain.proceed(builder.build())

        // 约定：只有 401 代表登录态失效（密码错误用的是 400）
        if (response.code == 401) {
            onUnauthorized()
        }
        return response
    }
}
