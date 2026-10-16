package com.wolaitongniwan.myapplication.data.remote.dto

/**
 * 订单相关。
 *
 * 状态码：1待支付 2待接单 3服务中 4待确认 5已完成 6退款中 7已关闭 8已退款
 *
 * 界面上**请直接使用 canXxx 字段**控制按钮显隐，不要自己推断状态机：
 * 服务端已按网页端规则算好，且两个反直觉点都在服务端兜住了
 * （状态 3 起只能「申请退款」不能「取消」；取消一张待接单订单的结果是「已退款」而非「已关闭」）。
 */
data class OrderDto(
    val id: Long = 0,
    val orderNo: String = "",
    val userId: Long = 0,
    val companionId: Long = 0,
    val gameId: Long = 0,
    val gameName: String = "",
    val skillId: Long = 0,
    val skillTitle: String = "",
    val serviceType: String = "",
    val quantity: Int = 1,
    val unitPrice: Double = 0.0,
    val unitPriceText: String = "",
    val amount: Double = 0.0,
    val amountText: String = "",
    val platformFee: Double = 0.0,
    val companionIncome: Double = 0.0,
    val status: Int = 0,
    val statusText: String = "",
    val statusClass: String = "",
    val userRemark: String = "",
    val cancelReason: String = "",
    val refundReason: String = "",
    val refundRemark: String = "",
    val coverUrl: String = "",
    val isPublic: Boolean = false,
    val isReviewed: Boolean = false,
    val expireAt: String = "",
    val payTime: String = "",
    val finishTime: String = "",
    val createdAt: String = "",
    val updatedAt: String = "",

    // 双方信息（列表接口会带上）
    val userNickname: String = "",
    val userAvatarUrl: String = "",
    val companionNickname: String = "",
    val companionAvatarUrl: String = "",

    // 我在本单的角色与可执行操作
    val myRole: String = "guest",   // buyer / companion / guest
    val isBuyer: Boolean = false,
    val isCompanion: Boolean = false,
    val canChat: Boolean = false,
    val canPay: Boolean = false,
    val canCancel: Boolean = false,
    val canRefund: Boolean = false,
    val canConfirm: Boolean = false,
    val canReview: Boolean = false,
    val canGrab: Boolean = false,
    val canAccept: Boolean = false,
    val canFinish: Boolean = false,
)

data class OrderLogDto(
    val id: Long = 0,
    val fromStatus: Int = 0,
    val toStatus: Int = 0,
    val toText: String = "",
    val operatorType: String = "system",
    val remark: String = "",
    val createdAt: String = "",
)

data class MessageDto(
    val id: Long = 0,
    val orderId: Long = 0,
    val senderId: Long = 0,
    val senderRole: String = "user",   // user / companion / admin
    val content: String = "",
    val isMine: Boolean = false,
    val createdAt: String = "",
)

data class OrderCountsDto(
    val total: Int = 0,
    val pendingPay: Int = 0,
    val pendingAccept: Int = 0,
    val serving: Int = 0,
    val pendingConfirm: Int = 0,
    val done: Int = 0,
    val refunding: Int = 0,
)

// ---------------------------------------------------------------- 聚合返回

/** 大部分订单写操作都返回 { order, balance } */
data class OrderWrapDto(
    val order: OrderDto? = null,
    val balance: Double = 0.0,
)

/** order/buyPackage */
data class BuyPackageResultDto(
    val order: OrderDto? = null,
    val paid: Boolean = false,
    val need: Double = 0.0,
    val needText: String = "",
    val balance: Double = 0.0,
    val needRecharge: Boolean = false,
)

data class OrderDetailDto(
    val order: OrderDto? = null,
    val logs: List<OrderLogDto> = emptyList(),
    val unread: Int = 0,
    val chatOpen: Boolean = false,
)

data class OrderPageDto(
    val list: List<OrderDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 10,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
    val counts: OrderCountsDto = OrderCountsDto(),
)

/** order/messages */
data class MessageListDto(
    val list: List<MessageDto> = emptyList(),
    val chatOpen: Boolean = false,
    val unread: Int = 0,
)

data class SendMessageResultDto(
    val messageId: Long = 0,
    val role: String = "",
)
