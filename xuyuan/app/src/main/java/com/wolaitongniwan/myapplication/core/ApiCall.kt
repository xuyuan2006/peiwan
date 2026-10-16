package com.wolaitongniwan.myapplication.core

import com.google.gson.Gson
import com.google.gson.JsonSyntaxException
import retrofit2.HttpException
import java.io.IOException
import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException

/**
 * 把「Retrofit 调用 + 统一响应解包」收敛到一处。
 *
 * 处理三件事：
 *  1. code != 0 → 抛 ApiException(msg)，UI 直接 toast 即可
 *  2. HTTP 4xx/5xx（Retrofit 会抛 HttpException）→ 尽量解析响应体里的 msg，
 *     解析不出来再退回系统文案
 *  3. 网络异常 → 转成可读中文，而不是把 "Failed to connect to /103.47.80.215:80" 抛给用户
 */
object ApiCall {

    /** 共享的 Gson：字段名用下划线风格，这样 Kotlin 的 camelCase 能直接对上后端的 snake_case */
    val gson: Gson = com.google.gson.GsonBuilder()
        .setFieldNamingPolicy(com.google.gson.FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
        .create()

    /** 需要 data 的场景：data 为空视为服务端异常 */
    suspend fun <T : Any> call(block: suspend () -> ApiResponse<T>): T {
        val response = execute(block)
        return response.data
            ?: throw ApiException(response.code, response.msg.ifBlank { "服务端未返回数据" })
    }

    /** 不需要 data 的场景（登出、关闭工单等） */
    suspend fun callUnit(block: suspend () -> ApiResponse<Any?>): String {
        return execute(block).msg
    }

    /**
     * 只要服务端提示文案、不关心 data。
     * 比 [callUnit] 好在它是泛型的，`ApiResponse<OnlineDto>` 这类类型也能直接传进来，
     * 不需要调用方做不安全的类型转换。
     */
    suspend fun <T> message(block: suspend () -> ApiResponse<T>): String {
        return execute(block).msg
    }

    /**
     * 既要 data 又要服务端提示文案的场景。
     * 例如支付成功时后端会返回「支付成功，等待陪玩接单」，直接拿它当 toast 比客户端写死更准。
     */
    suspend fun <T : Any> callWithMessage(block: suspend () -> ApiResponse<T>): Pair<T, String> {
        val response = execute(block)
        val data = response.data
            ?: throw ApiException(response.code, response.msg.ifBlank { "服务端未返回数据" })
        return data to response.msg
    }

    private suspend fun <T> execute(block: suspend () -> ApiResponse<T>): ApiResponse<T> {
        try {
            val response = block()
            if (response.code != 0) {
                throw ApiException(response.code, response.msg.ifBlank { "操作失败，请稍后再试" })
            }
            return response
        } catch (e: ApiException) {
            throw e
        } catch (e: HttpException) {
            throw e.toApiException()
        } catch (e: UnknownHostException) {
            throw ApiException(ApiException.CODE_NETWORK, "无法连接服务器，请检查网络或稍后再试", e)
        } catch (e: ConnectException) {
            throw ApiException(ApiException.CODE_NETWORK, "服务器拒绝连接，请稍后再试", e)
        } catch (e: SocketTimeoutException) {
            throw ApiException(ApiException.CODE_NETWORK, "连接超时，请稍后再试", e)
        } catch (e: IOException) {
            throw ApiException(ApiException.CODE_NETWORK, "网络异常，请检查网络后重试", e)
        } catch (e: JsonSyntaxException) {
            throw ApiException(ApiException.CODE_PARSE, "数据解析失败，请联系客服", e)
        } catch (e: kotlin.coroutines.cancellation.CancellationException) {
            // 协程取消**不是**错误：页面切走、请求被新的刷新替换时都会走到这里。
            // 必须原样抛出 —— 一旦被下面的通用分支吞掉，用户就会看到一个
            // 「StandaloneCoroutine was cancelled」的错误弹窗，而其实什么都没出错。
            // 而且吞掉取消会破坏协程的结构化并发，让上游以为请求还活着。
            throw e
        } catch (e: Exception) {
            throw ApiException(ApiException.CODE_PARSE, e.message ?: "请求失败，请稍后再试", e)
        }
    }

    private fun HttpException.toApiException(): ApiException {
        val httpCode = code()

        // 服务端在错误时也会返回 {code, msg}，尽量把中文提示捞出来
        val raw = try {
            response()?.errorBody()?.string()
        } catch (_: Exception) {
            null
        }

        if (!raw.isNullOrBlank()) {
            try {
                val envelope = gson.fromJson(raw, ErrorEnvelope::class.java)
                val msg = envelope?.msg.orEmpty()
                if (msg.isNotBlank()) {
                    // 服务端的 code 与 HTTP 状态同义；解析出有效值就优先用它
                    val parsedCode = envelope?.code ?: 0
                    return ApiException(if (parsedCode > 0) parsedCode else httpCode, msg)
                }
            } catch (_: Exception) {
                // 忽略：不是标准响应体就退回系统文案
            }
        }

        // 注意 404 的文案：线上 nginx 配了 error_page 404，会把接口的 404 响应体
        // 替换成站点的 HTML 404 页，于是上面的 JSON 解析必然失败。
        // 所以这里的兜底文案要写成「对用户有意义」的话，而不是「接口不存在」这种开发期用语。
        val fallback = when (httpCode) {
            ApiException.CODE_UNAUTHORIZED -> "登录已失效，请重新登录"
            403 -> "没有操作权限"
            404 -> "内容不存在或已下架"
            405 -> "请求方式不正确"
            429 -> "操作过于频繁，请稍后再试"
            in 500..599 -> "服务异常，请稍后再试"
            else -> "请求失败（HTTP $httpCode）"
        }
        return ApiException(httpCode, fallback)
    }
}
