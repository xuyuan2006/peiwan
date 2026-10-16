package com.wolaitongniwan.myapplication.core

/**
 * 统一的业务异常。所有网络层错误最终都会变成它，UI 只需要读 message 就能直接提示用户。
 */
class ApiException(
    val code: Int,
    override val message: String,
    cause: Throwable? = null,
) : Exception(message, cause) {

    /** 登录态失效：UI 层收到它应当清 token 并跳登录页 */
    val isUnauthorized: Boolean get() = code == CODE_UNAUTHORIZED

    /** 网络不可达（连不上服务器），区别于服务端返回的业务错误 */
    val isNetwork: Boolean get() = code == CODE_NETWORK

    companion object {
        const val CODE_UNAUTHORIZED = 401
        const val CODE_NETWORK = -1
        const val CODE_PARSE = -2
    }
}
