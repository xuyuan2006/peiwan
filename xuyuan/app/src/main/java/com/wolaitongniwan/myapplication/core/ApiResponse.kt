package com.wolaitongniwan.myapplication.core

/**
 * 接口统一响应体：`{ "code": 0, "msg": "ok", "data": {...} }`
 *
 * code == 0 表示成功，其余值与 HTTP 状态码语义一致
 * （400 业务失败 / 401 未登录 / 403 无权限 / 404 不存在 / 422 参数错 / 429 限流 / 500 服务异常）。
 */
data class ApiResponse<T>(
    val code: Int = 0,
    val msg: String = "",
    val data: T? = null,
)

/** 只取 code / msg 的轻量信封，用于解析错误响应体 */
internal data class ErrorEnvelope(
    val code: Int = 0,
    val msg: String = "",
)

/**
 * 分页结果的统一结构（列表接口都长这样）。
 */
data class PageDto<T>(
    val list: List<T> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 10,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
)
