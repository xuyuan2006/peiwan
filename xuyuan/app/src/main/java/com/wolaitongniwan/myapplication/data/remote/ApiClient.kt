package com.wolaitongniwan.myapplication.data.remote

import com.wolaitongniwan.myapplication.BuildConfig
import com.wolaitongniwan.myapplication.core.ApiCall
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Retrofit / OkHttp 的组装。
 *
 * 注意事项：
 *  - baseUrl 必须以 "/" 结尾，否则 Retrofit 直接抛异常。
 *  - 拦截器顺序很重要：先加 AuthInterceptor 再加日志拦截器，
 *    这样日志里能看到实际带上的 Authorization 头。
 *  - 日志里会屏蔽 Authorization，但调试级别仍会打印请求体（含密码），
 *    所以不要把 debug 包发给用户。
 */
object ApiClient {

    fun build(
        baseUrl: String,
        tokenProvider: () -> String?,
        onUnauthorized: () -> Unit,
    ): ApiService {
        val logging = HttpLoggingInterceptor().apply {
            level = if (BuildConfig.DEBUG) {
                HttpLoggingInterceptor.Level.BODY
            } else {
                HttpLoggingInterceptor.Level.NONE
            }
            redactHeader("Authorization")
        }

        val client = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            // 上传头像/凭证可能慢一些，写超时给足
            .writeTimeout(90, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .addInterceptor(AuthInterceptor(tokenProvider, onUnauthorized))
            .addInterceptor(logging)
            .build()

        return Retrofit.Builder()
            .baseUrl(if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(ApiCall.gson))
            .build()
            .create(ApiService::class.java)
    }
}
