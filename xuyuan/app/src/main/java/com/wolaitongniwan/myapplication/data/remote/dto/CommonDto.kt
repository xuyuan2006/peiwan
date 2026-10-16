package com.wolaitongniwan.myapplication.data.remote.dto

/**
 * 公共 / 站点配置相关。
 *
 * 说明：本项目用 Gson 的 LOWER_CASE_WITH_UNDERSCORES 命名策略，
 * 所以 Kotlin 的 camelCase 会自动对上后端的 snake_case（如 avatarUrl ↔ avatar_url）。
 * 另外后端保证每个字段都会出现（缺失时给空串/0），因此这里可以放心用非空类型。
 */

data class InitDto(
    val apiVersion: String = "",
    val serverTime: String = "",
    val timestamp: Long = 0,
    val siteName: String = "",
    val installed: Boolean = true,
    val apiReady: Boolean = false,
    val minAppVersion: String = "1.0.0",
    val forceUpdate: Boolean = false,
    val updateUrl: String = "",
    val updateNote: String = "",
)

data class VerifyInfoDto(
    val channel: String = "off",   // off / email / sms
    val label: String = "",
    val enabled: Boolean = false,
    val ttlMinutes: Int = 3,
)

data class RechargeChannelDto(
    val key: String = "",
    val name: String = "",
    val hint: String = "",
    val online: Boolean = false,
)

data class VerifyWrapDto(
    val register: VerifyInfoDto = VerifyInfoDto(),
    val reset: VerifyInfoDto = VerifyInfoDto(),
)

data class SiteConfigDto(
    val siteName: String = "",
    /** 站点 Logo 的完整地址；为空表示用客户端自带的默认图标 */
    val siteLogo: String = "",
    val siteNotice: String = "",
    val servicePhone: String = "",
    val noticeInterval: Int = 4,
    val bannerInterval: Int = 5,
    val companionIntro: String = "",
    val companionNeedAudit: Boolean = true,
    // 陪玩申请相关（后台「平台设置 → 运营展示」里填）
    //  - companionAssess：考核标准，申请前必须停留 4 秒看完才能提交；为空则不拦截
    //  - companionContactQr / companionContactText：提交申请后展示的联系方式
    val companionAssess: String = "",
    val companionContactQr: String = "",
    val companionContactText: String = "",
    val minWithdraw: Double = 50.0,
    val withdrawFeeRate: Double = 1.0,
    val platformFeeRate: Double = 20.0,
    val payChannel: String = "manual",
    val rechargeChannels: List<RechargeChannelDto> = emptyList(),
    val orderAutoCancelMinutes: Int = 30,
    val orderAutoConfirmHours: Int = 24,
    val verify: VerifyWrapDto = VerifyWrapDto(),
)

/** 简单 { key, name } 选项，用于下拉筛选 */
data class OptionDto(
    val key: String = "",
    val name: String = "",
)
