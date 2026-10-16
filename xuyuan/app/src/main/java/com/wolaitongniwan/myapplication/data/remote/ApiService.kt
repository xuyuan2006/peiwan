package com.wolaitongniwan.myapplication.data.remote

import com.wolaitongniwan.myapplication.core.ApiResponse
import com.wolaitongniwan.myapplication.core.PageDto
import com.wolaitongniwan.myapplication.data.remote.dto.ApplyCompanionRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ApplyResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.AuthTokenDto
import com.wolaitongniwan.myapplication.data.remote.dto.BuyPackageRequest
import com.wolaitongniwan.myapplication.data.remote.dto.BuyPackageResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.ChangePasswordRequest
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionCenterDto
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionPageDto
import com.wolaitongniwan.myapplication.data.remote.dto.CreateOrderRequest
import com.wolaitongniwan.myapplication.data.remote.dto.CreateReviewRequest
import com.wolaitongniwan.myapplication.data.remote.dto.CreateTicketRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ForgotRequest
import com.wolaitongniwan.myapplication.data.remote.dto.GameDto
import com.wolaitongniwan.myapplication.data.remote.dto.HallPageDto
import com.wolaitongniwan.myapplication.data.remote.dto.HomeDto
import com.wolaitongniwan.myapplication.data.remote.dto.IdReasonRequest
import com.wolaitongniwan.myapplication.data.remote.dto.IdRequest
import com.wolaitongniwan.myapplication.data.remote.dto.IncomeDto
import com.wolaitongniwan.myapplication.data.remote.dto.InitDto
import com.wolaitongniwan.myapplication.data.remote.dto.LoginRequest
import com.wolaitongniwan.myapplication.data.remote.dto.MeDto
import com.wolaitongniwan.myapplication.data.remote.dto.MessageListDto
import com.wolaitongniwan.myapplication.data.remote.dto.MyCompanionProfileWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.MyReviewsDto
import com.wolaitongniwan.myapplication.data.remote.dto.OnlineDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderLogDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderPageDto
import com.wolaitongniwan.myapplication.data.remote.dto.OrderWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.PackagePageDto
import com.wolaitongniwan.myapplication.data.remote.dto.RechargePageDto
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeRequest
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.RegisterRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ReplyReviewRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ReplyTicketRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ReviewWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.SaveProfileRequest
import com.wolaitongniwan.myapplication.data.remote.dto.SaveSkillRequest
import com.wolaitongniwan.myapplication.data.remote.dto.SearchResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.SendCodeRequest
import com.wolaitongniwan.myapplication.data.remote.dto.SendMessageRequest
import com.wolaitongniwan.myapplication.data.remote.dto.SendMessageResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.SiteConfigDto
import com.wolaitongniwan.myapplication.data.remote.dto.SkillListDto
import com.wolaitongniwan.myapplication.data.remote.dto.SkillStatusDto
import com.wolaitongniwan.myapplication.data.remote.dto.SkillWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketCategoriesDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketDetailDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketPageDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketThreadDto
import com.wolaitongniwan.myapplication.data.remote.dto.TicketWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.ToggleOnlineRequest
import com.wolaitongniwan.myapplication.data.remote.dto.TransactionPageDto
import com.wolaitongniwan.myapplication.data.remote.dto.UploadDto
import com.wolaitongniwan.myapplication.data.remote.dto.UserCenterDto
import com.wolaitongniwan.myapplication.data.remote.dto.UserProfileDto
import com.wolaitongniwan.myapplication.data.remote.dto.UserWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.VerifyWrapDto
import com.wolaitongniwan.myapplication.data.remote.dto.WalletOverviewDto
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawPageDto
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawRequest
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawResultDto
import okhttp3.MultipartBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Query

/**
 * 全部后端接口。
 *
 * 路由形式是 `api/index.php?r=控制器/方法`，所以路径里直接写死了查询串；
 * Retrofit 会在其后用 `&` 追加 @Query 参数，两者不冲突。
 *
 * 所有方法都返回 `ApiResponse<T>`，由 ApiCall 统一判断 code 并抛 ApiException，
 * 所以 ViewModel 里不需要写 try/catch 解析 code。
 */
interface ApiService {

    // ================================================================ 公共

    @GET("api/index.php?r=common/init")
    suspend fun commonInit(): ApiResponse<InitDto>

    @GET("api/index.php?r=common/config")
    suspend fun siteConfig(): ApiResponse<SiteConfigDto>

    // ================================================================ 鉴权

    @POST("api/index.php?r=auth/login")
    suspend fun login(@Body body: LoginRequest): ApiResponse<AuthTokenDto>

    @POST("api/index.php?r=auth/register")
    suspend fun register(@Body body: RegisterRequest): ApiResponse<AuthTokenDto>

    @POST("api/index.php?r=auth/logout")
    suspend fun logout(): ApiResponse<Any?>

    @GET("api/index.php?r=auth/me")
    suspend fun me(): ApiResponse<MeDto>

    @GET("api/index.php?r=auth/verifyChannel")
    suspend fun verifyChannel(): ApiResponse<VerifyWrapDto>

    @POST("api/index.php?r=auth/sendCode")
    suspend fun sendCode(@Body body: SendCodeRequest): ApiResponse<Any?>

    @POST("api/index.php?r=auth/forgot")
    suspend fun forgot(@Body body: ForgotRequest): ApiResponse<Any?>

    @POST("api/index.php?r=auth/changePassword")
    suspend fun changePassword(@Body body: ChangePasswordRequest): ApiResponse<Any?>

    // ================================================================ 首页 / 内容

    @GET("api/index.php?r=home/index")
    suspend fun homeIndex(@Query("game_id") gameId: Long? = null): ApiResponse<HomeDto>

    @GET("api/index.php?r=home/games")
    suspend fun games(): ApiResponse<List<GameDto>>

    @GET("api/index.php?r=home/packages")
    suspend fun packages(@Query("game_id") gameId: Long? = null): ApiResponse<PackagePageDto>

    @GET("api/index.php?r=home/companions")
    suspend fun companions(
        @Query("game_id") gameId: Long? = null,
        @Query("gender") gender: Int? = null,
        @Query("keyword") keyword: String? = null,
        @Query("sort") sort: String? = null,
        @Query("online_only") onlineOnly: Int? = null,
        @Query("min_price") minPrice: String? = null,
        @Query("max_price") maxPrice: String? = null,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 12,
    ): ApiResponse<CompanionPageDto>

    @GET("api/index.php?r=home/companion")
    suspend fun companionDetail(@Query("id") id: Long): ApiResponse<CompanionDetailDto>

    @GET("api/index.php?r=home/search")
    suspend fun search(@Query("keyword") keyword: String): ApiResponse<SearchResultDto>

    // ================================================================ 订单

    @POST("api/index.php?r=order/create")
    suspend fun createOrder(@Body body: CreateOrderRequest): ApiResponse<OrderWrapDto>

    @POST("api/index.php?r=order/buyPackage")
    suspend fun buyPackage(@Body body: BuyPackageRequest): ApiResponse<BuyPackageResultDto>

    @GET("api/index.php?r=order/index")
    suspend fun orderList(
        @Query("status") status: String = "all",
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 10,
    ): ApiResponse<OrderPageDto>

    @GET("api/index.php?r=order/detail")
    suspend fun orderDetail(@Query("id") id: Long): ApiResponse<OrderDetailDto>

    @GET("api/index.php?r=order/logs")
    suspend fun orderLogs(@Query("id") id: Long): ApiResponse<List<OrderLogDto>>

    @POST("api/index.php?r=order/pay")
    suspend fun payOrder(@Body body: IdRequest): ApiResponse<OrderWrapDto>

    @POST("api/index.php?r=order/cancel")
    suspend fun cancelOrder(@Body body: IdReasonRequest): ApiResponse<OrderWrapDto>

    @POST("api/index.php?r=order/refund")
    suspend fun refundOrder(@Body body: IdReasonRequest): ApiResponse<OrderWrapDto>

    @POST("api/index.php?r=order/confirm")
    suspend fun confirmOrder(@Body body: IdRequest): ApiResponse<OrderWrapDto>

    @GET("api/index.php?r=order/messages")
    suspend fun orderMessages(
        @Query("id") id: Long,
        @Query("since_id") sinceId: Long = 0,
    ): ApiResponse<MessageListDto>

    @POST("api/index.php?r=order/sendMessage")
    suspend fun sendOrderMessage(@Body body: SendMessageRequest): ApiResponse<SendMessageResultDto>

    // ================================================================ 钱包

    @GET("api/index.php?r=wallet/index")
    suspend fun walletOverview(): ApiResponse<WalletOverviewDto>

    @GET("api/index.php?r=wallet/transactions")
    suspend fun transactions(
        @Query("type") type: String? = null,
        @Query("direction") direction: Int? = null,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20,
    ): ApiResponse<TransactionPageDto>

    @POST("api/index.php?r=wallet/recharge")
    suspend fun recharge(@Body body: RechargeRequest): ApiResponse<RechargeResultDto>

    @GET("api/index.php?r=wallet/rechargePay")
    suspend fun rechargePay(@Query("id") id: Long): ApiResponse<RechargeResultDto>

    @GET("api/index.php?r=wallet/rechargeDetail")
    suspend fun rechargeDetail(@Query("id") id: Long): ApiResponse<RechargeWrapDto>

    /** 取消未支付的充值单（后端只允许取消 status=0 且属于自己的单） */
    @POST("api/index.php?r=wallet/rechargeCancel")
    suspend fun rechargeCancel(@Query("id") id: Long): ApiResponse<Any?>

    @GET("api/index.php?r=wallet/rechargeList")
    suspend fun rechargeList(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20,
    ): ApiResponse<RechargePageDto>

    @POST("api/index.php?r=wallet/withdraw")
    suspend fun withdraw(@Body body: WithdrawRequest): ApiResponse<WithdrawResultDto>

    @GET("api/index.php?r=wallet/withdrawList")
    suspend fun withdrawList(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 20,
    ): ApiResponse<WithdrawPageDto>

    // ================================================================ 陪玩端

    @GET("api/index.php?r=companion/hall")
    suspend fun hall(
        @Query("game_id") gameId: Long? = null,
        @Query("keyword") keyword: String? = null,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 12,
    ): ApiResponse<HallPageDto>

    @POST("api/index.php?r=companion/grab")
    suspend fun grabOrder(@Body body: IdRequest): ApiResponse<OrderWrapDto>

    @GET("api/index.php?r=companion/orders")
    suspend fun companionOrders(
        @Query("status") status: String = "all",
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 10,
    ): ApiResponse<OrderPageDto>

    @POST("api/index.php?r=companion/accept")
    suspend fun acceptOrder(@Body body: IdRequest): ApiResponse<OrderWrapDto>

    @POST("api/index.php?r=companion/finish")
    suspend fun finishOrder(@Body body: IdRequest): ApiResponse<OrderWrapDto>

    @POST("api/index.php?r=companion/toggleOnline")
    suspend fun toggleOnline(@Body body: ToggleOnlineRequest): ApiResponse<OnlineDto>

    @GET("api/index.php?r=companion/center")
    suspend fun companionCenter(): ApiResponse<CompanionCenterDto>

    @GET("api/index.php?r=companion/profile")
    suspend fun companionProfile(): ApiResponse<MyCompanionProfileWrapDto>

    @POST("api/index.php?r=companion/apply")
    suspend fun applyCompanion(@Body body: ApplyCompanionRequest): ApiResponse<ApplyResultDto>

    @GET("api/index.php?r=companion/skills")
    suspend fun skills(@Query("all") all: Int? = null): ApiResponse<SkillListDto>

    @POST("api/index.php?r=companion/skillSave")
    suspend fun saveSkill(@Body body: SaveSkillRequest): ApiResponse<SkillWrapDto>

    @POST("api/index.php?r=companion/skillDelete")
    suspend fun deleteSkill(@Body body: IdRequest): ApiResponse<Any?>

    @POST("api/index.php?r=companion/skillToggle")
    suspend fun toggleSkill(@Body body: IdRequest): ApiResponse<SkillStatusDto>

    @GET("api/index.php?r=companion/incomeStat")
    suspend fun incomeStat(): ApiResponse<IncomeDto>

    // ================================================================ 用户中心

    @GET("api/index.php?r=user/center")
    suspend fun userCenter(): ApiResponse<UserCenterDto>

    @GET("api/index.php?r=user/profile")
    suspend fun userProfile(): ApiResponse<UserProfileDto>

    @POST("api/index.php?r=user/saveProfile")
    suspend fun saveProfile(@Body body: SaveProfileRequest): ApiResponse<UserWrapDto>

    @POST("api/index.php?r=user/apply")
    suspend fun applyCompanionByUser(@Body body: ApplyCompanionRequest): ApiResponse<ApplyResultDto>

    // ================================================================ 工单

    @GET("api/index.php?r=ticket/categories")
    suspend fun ticketCategories(): ApiResponse<TicketCategoriesDto>

    @GET("api/index.php?r=ticket/index")
    suspend fun ticketList(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 10,
    ): ApiResponse<TicketPageDto>

    @POST("api/index.php?r=ticket/create")
    suspend fun createTicket(@Body body: CreateTicketRequest): ApiResponse<TicketWrapDto>

    @GET("api/index.php?r=ticket/detail")
    suspend fun ticketDetail(@Query("id") id: Long): ApiResponse<TicketDetailDto>

    @POST("api/index.php?r=ticket/reply")
    suspend fun replyTicket(@Body body: ReplyTicketRequest): ApiResponse<TicketThreadDto>

    @POST("api/index.php?r=ticket/close")
    suspend fun closeTicket(@Body body: IdRequest): ApiResponse<TicketWrapDto>

    // ================================================================ 评价

    @POST("api/index.php?r=review/create")
    suspend fun createReview(@Body body: CreateReviewRequest): ApiResponse<ReviewWrapDto>

    @POST("api/index.php?r=review/reply")
    suspend fun replyReview(@Body body: ReplyReviewRequest): ApiResponse<Any?>

    @GET("api/index.php?r=review/mine")
    suspend fun myReviews(
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 10,
    ): ApiResponse<MyReviewsDto>

    // ================================================================ 上传

    @Multipart
    @POST("api/index.php?r=upload/image")
    suspend fun uploadImage(
        @Query("type") type: String,
        @Part file: MultipartBody.Part,
    ): ApiResponse<UploadDto>
}
