package com.wolaitongniwan.myapplication.data.remote.dto

// ---------------------------------------------------------------- 钱包

data class WalletCountsDto(
    val rechargePending: Int = 0,
    val withdrawPending: Int = 0,
    val orderPendingPay: Int = 0,
)

data class WithdrawConfigDto(
    val min: Double = 50.0,
    val feeRate: Double = 1.0,
)

data class WalletOverviewDto(
    val wallet: WalletDto = WalletDto(),
    val counts: WalletCountsDto = WalletCountsDto(),
    val withdrawConfig: WithdrawConfigDto = WithdrawConfigDto(),
)

data class TransactionDto(
    val id: Long = 0,
    val type: String = "",
    val typeText: String = "",
    val direction: Int = 1,        // 1 收入 / 2 支出
    val directionText: String = "",
    val amount: Double = 0.0,
    val amountText: String = "",
    val balanceAfter: Double = 0.0,
    val frozenAfter: Double = 0.0,
    val remark: String = "",
    val createdAt: String = "",
)

data class TransactionPageDto(
    val list: List<TransactionDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 20,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
    val wallet: WalletDto = WalletDto(),
    val typeOptions: List<OptionDto> = emptyList(),
)

// ---------------------------------------------------------------- 充值

data class RechargeDto(
    val id: Long = 0,
    val orderNo: String = "",
    val amount: Double = 0.0,
    val amountText: String = "",
    val channel: String = "manual",   // manual / epay / alipay
    val status: Int = 0,              // 0 待处理 / 1 已到账 / 2 已关闭 / 3 已驳回
    val statusText: String = "",
    val payVoucherUrl: String = "",
    val payUrl: String = "",
    val tradeNo: String = "",
    val auditRemark: String = "",
    val remark: String = "",
    val createdAt: String = "",
    val paidAt: String = "",
)

/**
 * 充值下单结果。
 *
 * payMode 取值：
 *  - redirect：用系统浏览器/WebView 打开 payUrl 完成支付
 *  - qrcode  ：把 qrCode 的内容渲染成二维码让用户扫（支付宝当面付）
 *  - manual  ：人工转账，等客服核对到账
 */
data class RechargeResultDto(
    val recharge: RechargeDto? = null,
    val payMode: String = "manual",
    val payUrl: String = "",
    val qrCode: String = "",
)

data class RechargeWrapDto(
    val recharge: RechargeDto? = null,
)

data class RechargePageDto(
    val list: List<RechargeDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 20,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
)

// ---------------------------------------------------------------- 提现

data class WithdrawDto(
    val id: Long = 0,
    val orderNo: String = "",
    val amount: Double = 0.0,
    val amountText: String = "",
    val fee: Double = 0.0,
    val realAmount: Double = 0.0,
    val accountType: String = "alipay",   // alipay / wechat / bank
    val accountName: String = "",
    val accountNo: String = "",
    val status: Int = 0,                  // 0 待审核 / 1 已打款 / 2 已驳回
    val statusText: String = "",
    val auditRemark: String = "",
    val createdAt: String = "",
)

data class WithdrawResultDto(
    val withdraw: WithdrawDto? = null,
    val wallet: WalletDto = WalletDto(),
)

data class WithdrawPageDto(
    val list: List<WithdrawDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val perPage: Int = 20,
    val totalPage: Int = 0,
    val hasMore: Boolean = false,
    val wallet: WalletDto = WalletDto(),
    val withdrawConfig: WithdrawConfigDto = WithdrawConfigDto(),
)
