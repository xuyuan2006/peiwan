package com.wolaitongniwan.myapplication.data.remote.dto

// ---------------------------------------------------------------- 首页 / 内容

data class BannerDto(
    val id: Long = 0,
    val title: String = "",
    val imageUrl: String = "",
    val linkUrl: String = "",
    val sort: Int = 0,
)

data class AnnouncementDto(
    val id: Long = 0,
    val content: String = "",
    val linkUrl: String = "",
    val sort: Int = 0,
)

data class GameDto(
    val id: Long = 0,
    val name: String = "",
    val iconUrl: String = "",
    val isHot: Boolean = false,
    val sort: Int = 0,
)

data class PackageDto(
    val id: Long = 0,
    val title: String = "",
    val gameId: Long = 0,
    val price: Double = 0.0,
    val priceText: String = "",
    val coverUrl: String = "",
    val description: String = "",
    val sort: Int = 0,
)

// ---------------------------------------------------------------- 陪玩

data class CompanionDto(
    val userId: Long = 0,
    val nickname: String = "",
    val avatarUrl: String = "",
    val gender: Int = 0,
    val city: String = "",
    val intro: String = "",
    val coverUrl: String = "",
    val voiceUrl: String = "",
    val tags: List<String> = emptyList(),
    val score: Double = 5.0,
    val goodRate: Double = 100.0,
    val orderCount: Int = 0,
    val reviewCount: Int = 0,
    val creditScore: Int = 100,
    val online: Boolean = false,
    val onlineText: String = "",
    val isRecommend: Boolean = false,
    val auditStatus: Int = 0,
    val minPrice: Double? = null,
    val minPriceText: String = "面议",
    val skillCount: Int = 0,
)

data class SkillDto(
    val id: Long = 0,
    val companionId: Long = 0,
    val gameId: Long = 0,
    val gameName: String = "",
    val title: String = "",
    val serviceType: String = "hour",   // hour / round / day / custom
    val serviceTypeText: String = "",
    val unit: String = "",
    val rankLevel: String = "",
    val price: Double = 0.0,
    val priceText: String = "",
    val coverUrl: String = "",
    val description: String = "",
    val status: Int = 1,
    val sort: Int = 0,
)

data class ReviewDto(
    val id: Long = 0,
    val orderId: Long = 0,
    val userId: Long = 0,
    val companionId: Long = 0,
    val score: Int = 5,
    val content: String = "",
    val tags: List<String> = emptyList(),
    val isAnonymous: Boolean = false,
    val nickname: String = "",
    val avatarUrl: String = "",
    val reply: String = "",
    val replyAt: String = "",
    val createdAt: String = "",
)

// ---------------------------------------------------------------- 聚合返回

data class HomeStatsDto(
    val companions: Int = 0,
    val orders: Int = 0,
    val completed: Int = 0,
)

data class HomeDto(
    val siteName: String = "",
    val siteNotice: String = "",
    val servicePhone: String = "",
    val banners: List<BannerDto> = emptyList(),
    val announcements: List<AnnouncementDto> = emptyList(),
    val noticeInterval: Int = 4,
    val bannerInterval: Int = 5,
    val games: List<GameDto> = emptyList(),
    val packages: List<PackageDto> = emptyList(),
    val companions: List<CompanionDto> = emptyList(),
    val companionTotal: Int = 0,
    val stats: HomeStatsDto = HomeStatsDto(),
)

/** home/companions：分页字段 + games 合并在一起 */
data class CompanionPageDto(
    val list: List<CompanionDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 12,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
    val games: List<GameDto> = emptyList(),
)

data class CompanionStatsDto(
    val total: Int = 0,
    val completed: Int = 0,
)

data class CompanionDetailDto(
    val profile: CompanionDto? = null,
    val skills: List<SkillDto> = emptyList(),
    val reviews: List<ReviewDto> = emptyList(),
    val stats: CompanionStatsDto = CompanionStatsDto(),
)

data class PackagePageDto(
    val games: List<GameDto> = emptyList(),
    val list: List<PackageDto> = emptyList(),
)

data class SearchResultDto(
    val keyword: String = "",
    val companions: List<CompanionDto> = emptyList(),
    val packages: List<PackageDto> = emptyList(),
)
