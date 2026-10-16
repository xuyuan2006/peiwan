package com.wolaitongniwan.myapplication.data.remote.dto

/**
 * 请求体。
 *
 * 字段名会自动转成 snake_case（Gson 的 LOWER_CASE_WITH_UNDERSCORES 策略），
 * 例如 passwordConfirm → password_confirm，与后端接口约定完全一致。
 */

// ---------------------------------------------------------------- 鉴权

data class LoginRequest(
    val account: String,
    val password: String,
    val device: String = "",
    val platform: String = "android",
    val appVersion: String = "",
)

/**
 * 注册请求。
 *
 * 注意这里**没有** username：账号由服务端自动生成。
 * 昵称 + 手机号 + 邮箱已经足够标识一个人，再让用户自己想一个「账号」既增加填写负担，
 * 又常常因为重名被驳回。
 */
data class RegisterRequest(
    val nickname: String,
    val password: String,
    val passwordConfirm: String,
    val phone: String,
    val email: String,
    val gender: Int = 0,
    val code: String = "",
    val device: String = "",
    val platform: String = "android",
    val appVersion: String = "",
)

data class SendCodeRequest(
    val scene: String,          // register / reset
    val target: String,
)

data class ForgotRequest(
    val email: String = "",
    val phone: String = "",
    val code: String = "",
    val password: String,
    val passwordConfirm: String,
)

data class ChangePasswordRequest(
    val oldPassword: String,
    val password: String,
    val passwordConfirm: String,
)

// ---------------------------------------------------------------- 通用

data class IdRequest(val id: Long)

data class IdReasonRequest(
    val id: Long,
    val reason: String = "",
)

// ---------------------------------------------------------------- 订单

data class CreateOrderRequest(
    val skillId: Long,
    val quantity: Int,
    val remark: String = "",
)

data class BuyPackageRequest(
    val packageId: Long,
    val remark: String = "",
)

data class SendMessageRequest(
    val id: Long,
    val content: String,
)

// ---------------------------------------------------------------- 钱包

data class RechargeRequest(
    val amount: Double,
    val voucher: String = "",
    val remark: String = "",
    /**
     * 在线支付的具体方式：wxpay（微信支付）/ alipay（支付宝）。
     * 只有后端渠道是「易支付」时才有意义（易支付收银台支持这两种）；
     * 当面付固定是支付宝，后端会忽略这个字段。
     */
    val payType: String = "",
)

data class WithdrawRequest(
    val amount: Double,
    val accountType: String,
    val accountName: String,
    val accountNo: String,
)

// ---------------------------------------------------------------- 用户 / 陪玩

data class SaveProfileRequest(
    val nickname: String,
    val phone: String = "",
    val email: String = "",
    val gender: Int = 0,
    /** 相对路径，留空表示不改头像 */
    val avatar: String = "",
    val oldPassword: String = "",
    val password: String = "",
)

data class ApplyCompanionRequest(
    val realName: String,
    val phone: String,
    val idCard: String = "",
    val gender: Int = 0,
    val city: String = "",
    val intro: String = "",
    val tags: String = "",
)

data class SaveSkillRequest(
    val id: Long = 0,
    val gameId: Long,
    val title: String,
    val serviceType: String = "hour",
    val rankLevel: String = "",
    val price: Double,
    val description: String = "",
    /** 相对路径，留空表示保持原图 */
    val cover: String = "",
)

data class ToggleOnlineRequest(val online: Boolean)

// ---------------------------------------------------------------- 工单 / 评价

data class CreateTicketRequest(
    val category: String,
    val title: String,
    val content: String,
    val orderId: Long = 0,
)

data class ReplyTicketRequest(
    val id: Long,
    val content: String,
)

data class CreateReviewRequest(
    val orderId: Long,
    val score: Int,
    val content: String = "",
    val tags: List<String> = emptyList(),
    val isAnonymous: Int = 0,
)

data class ReplyReviewRequest(
    val reviewId: Long,
    val reply: String,
)
