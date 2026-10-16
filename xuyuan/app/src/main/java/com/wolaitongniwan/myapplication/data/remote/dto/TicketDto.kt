package com.wolaitongniwan.myapplication.data.remote.dto

import com.wolaitongniwan.myapplication.core.PageDto

// ---------------------------------------------------------------- 工单

data class TicketDto(
    val id: Long = 0,
    val ticketNo: String = "",
    val category: String = "presale",     // presale / aftersale / complaint
    val categoryText: String = "",
    val orderId: Long = 0,
    val title: String = "",
    val content: String = "",
    val status: Int = 0,                  // 0 待处理 / 1 处理中 / 2 已解决 / 3 已关闭
    val statusText: String = "",
    val replyCount: Int = 0,
    val createdAt: String = "",
    val updatedAt: String = "",
)

data class TicketReplyDto(
    val id: Long = 0,
    val ticketId: Long = 0,
    val senderType: String = "user",      // user / admin
    val senderName: String = "我",
    val content: String = "",
    val createdAt: String = "",
)

data class TicketPageDto(
    val list: List<TicketDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 10,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
    val openCount: Int = 0,
)

data class TicketDetailDto(
    val ticket: TicketDto? = null,
    val thread: List<TicketReplyDto> = emptyList(),
)

data class TicketThreadDto(
    val thread: List<TicketReplyDto> = emptyList(),
)

data class TicketWrapDto(
    val ticket: TicketDto? = null,
)

data class TicketCategoryDto(
    val key: String = "",
    val name: String = "",
    /** 类型的补充说明，例如「下单之前想问问：价格、流程、能不能做」 */
    val hint: String = "",
)

data class TicketStatusOptionDto(
    val key: Int = 0,
    val name: String = "",
)

data class TicketCategoriesDto(
    val list: List<TicketCategoryDto> = emptyList(),
    val statusOptions: List<TicketStatusOptionDto> = emptyList(),
)

// ---------------------------------------------------------------- 评价

data class ReviewWrapDto(
    val review: ReviewDto? = null,
)

/** review/mine：我收到的（作为陪玩）与我发出的（作为买家） */
data class MyReviewsDto(
    val received: PageDto<ReviewDto> = PageDto(),
    val given: PageDto<ReviewDto> = PageDto(),
)

// ---------------------------------------------------------------- 上传

data class UploadDto(
    /** 相对路径，业务接口要传的就是它（不是 url） */
    val path: String = "",
    /** 绝对地址，仅用于本地预览 */
    val url: String = "",
    val type: String = "",
)
