package com.wolaitongniwan.myapplication.data.remote.dto

// ---------------------------------------------------------------- 陪玩（本人视角）

/**
 * 陪玩资料「本人视角」——只有当前登录用户自己的接口才会返回它，
 * 因为里面含 realName / idCard 这类实名信息。对外公开的陪玩主页用 CompanionDto。
 */
data class MyCompanionProfileDto(
    val userId: Long = 0,
    val nickname: String = "",
    val avatarUrl: String = "",
    val realName: String = "",
    val idCard: String = "",
    val phone: String = "",
    val gender: Int = 0,
    val city: String = "",
    val intro: String = "",
    val tags: List<String> = emptyList(),
    val coverUrl: String = "",
    val voiceUrl: String = "",
    val score: Double = 5.0,
    val goodRate: Double = 100.0,
    val orderCount: Int = 0,
    val reviewCount: Int = 0,
    val totalIncome: Double = 0.0,
    val auditStatus: Int = 0,
    val auditText: String = "",
    val auditRemark: String = "",
    val online: Boolean = false,
    val isRecommend: Boolean = false,
)

data class AuditDto(
    val applied: Boolean = false,
    val auditStatus: Int = -1,
    val auditText: String = "未申请",
    val auditRemark: String = "",
)

data class CompanionCountsDto(
    val pendingAccept: Int = 0,
    val serving: Int = 0,
    val hallTotal: Int = 0,
)

data class IncomeDto(
    val totalOrders: Int = 0,
    val completedOrders: Int = 0,
    val servingOrders: Int = 0,
    val totalIncome: Double = 0.0,
    val totalIncomeText: String = "0.00",
    val todayIncome: Double = 0.0,
    val todayIncomeText: String = "0.00",
    val todayOrders: Int = 0,
)

data class CompanionCenterDto(
    val profile: MyCompanionProfileDto? = null,
    val audit: AuditDto = AuditDto(),
    val online: Boolean = false,
    val wallet: WalletDto = WalletDto(),
    val income: IncomeDto = IncomeDto(),
    val counts: CompanionCountsDto = CompanionCountsDto(),
    val skills: List<SkillDto> = emptyList(),
)

/** companion/profile 与 user/profile 里的 companion 节点结构一致 */
data class MyCompanionProfileWrapDto(
    val applied: Boolean = false,
    val auditStatus: Int = -1,
    val auditText: String = "未申请",
    val auditRemark: String = "",
    val online: Boolean = false,
    val profile: MyCompanionProfileDto? = null,
)

data class ServiceTypeDto(
    val key: String = "",
    val name: String = "",
    val unit: String = "",
)

data class SkillListDto(
    val list: List<SkillDto> = emptyList(),
    val games: List<GameDto> = emptyList(),
    val serviceTypes: List<ServiceTypeDto> = emptyList(),
)

data class SkillWrapDto(
    val skill: SkillDto? = null,
)

/** companion/hall：分页字段 + games */
data class HallPageDto(
    val list: List<OrderDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 12,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
    val games: List<GameDto> = emptyList(),
)

data class OnlineDto(val online: Boolean = false)
data class SkillStatusDto(val status: Int = 0)
data class ApplyResultDto(val auditStatus: Int = 0, val auditText: String = "待审核")

// ---------------------------------------------------------------- 用户中心

data class UserCountsDto(
    val total: Int = 0,
    val pendingPay: Int = 0,
    val pendingAccept: Int = 0,
    val serving: Int = 0,
    val pendingConfirm: Int = 0,
    val completed: Int = 0,
    val unreadMessage: Int = 0,
)

/** 陪玩收益卡（未过审时为 null） */
data class IncomeBriefDto(
    val totalIncome: Double = 0.0,
    val todayIncome: Double = 0.0,
    val todayOrders: Int = 0,
    val completedOrders: Int = 0,
)

data class UserCenterDto(
    val user: UserDto = UserDto(),
    val wallet: WalletDto = WalletDto(),
    val counts: UserCountsDto = UserCountsDto(),
    val companion: CompanionStatusDto = CompanionStatusDto(),
    val compStat: IncomeBriefDto? = null,
    val intro: String = "",
)

data class UserProfileDto(
    val user: UserDto = UserDto(),
    val companion: MyCompanionProfileWrapDto = MyCompanionProfileWrapDto(),
)

data class UserWrapDto(
    val user: UserDto = UserDto(),
)
