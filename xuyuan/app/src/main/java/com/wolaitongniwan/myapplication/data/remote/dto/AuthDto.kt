package com.wolaitongniwan.myapplication.data.remote.dto

/** 用户基础信息（后端已剥离 password 等敏感字段） */
data class UserDto(
    val id: Long = 0,
    /** 对外展示的用户号（随机 6 位）：个人中心里那个「ID」就是它 */
    val no: Int = 0,
    val username: String = "",
    val nickname: String = "",
    val avatarUrl: String = "",
    val phone: String = "",
    val email: String = "",
    val gender: Int = 0,          // 0 保密 / 1 男 / 2 女
    val role: String = "user",    // user / companion / admin
    val creditScore: Int = 100,
    val isAdmin: Boolean = false,
    val isCompanion: Boolean = false,
    val createdAt: String = "",
)

/** 钱包摘要 */
data class WalletDto(
    val balance: Double = 0.0,
    val balanceText: String = "0.00",
    val frozen: Double = 0.0,
    val frozenText: String = "0.00",
    val totalRecharge: Double = 0.0,
    val totalIncome: Double = 0.0,
    val totalWithdraw: Double = 0.0,
    val totalExpense: Double = 0.0,
)

/** 「我」的陪玩身份状态（不含实名等敏感信息） */
data class CompanionStatusDto(
    val applied: Boolean = false,
    val auditStatus: Int = -1,    // -1 未申请 / 0 待审核 / 1 已通过 / 2 已拒绝
    val auditText: String = "未申请",
    val auditRemark: String = "",
    val online: Boolean = false,
)

/** 登录 / 注册 / auth/me 的统一返回 */
data class MeDto(
    val user: UserDto = UserDto(),
    val wallet: WalletDto = WalletDto(),
    val companion: CompanionStatusDto = CompanionStatusDto(),
    val unreadMessage: Int = 0,
)

data class AuthTokenDto(
    val token: String = "",
    val expireAt: String = "",
    val me: MeDto = MeDto(),
)
