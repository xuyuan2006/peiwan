package com.wolaitongniwan.myapplication.ui.wallet

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.WalletDto
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawConfigDto
import com.wolaitongniwan.myapplication.data.remote.dto.WithdrawRequest
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class WithdrawFormState(
    val amount: String = "",
    /** alipay / wechat / bank */
    val accountType: String = "alipay",
    val accountName: String = "",
    val accountNo: String = "",
    val submitting: Boolean = false,
)

class WithdrawViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _form = MutableStateFlow(WithdrawFormState())
    val form: StateFlow<WithdrawFormState> = _form.asStateFlow()

    private val _wallet = MutableStateFlow(WalletDto())
    val wallet: StateFlow<WalletDto> = _wallet.asStateFlow()

    private val _config = MutableStateFlow(WithdrawConfigDto())
    val config: StateFlow<WithdrawConfigDto> = _config.asStateFlow()

    private val _done = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val done: SharedFlow<Unit> = _done

    init {
        load()
    }

    fun load() {
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.walletOverview() }
                _wallet.value = data.wallet
                _config.value = data.withdrawConfig
            } catch (e: ApiException) {
                notify(e.message)
            }
        }
    }

    fun setAmount(value: String) {
        val filtered = value.filter { it.isDigit() || it == '.' }
        val safe = if (filtered.count { it == '.' } > 1) {
            filtered.substringBefore('.') + "." + filtered.substringAfter('.').replace(".", "")
        } else {
            filtered
        }.let { if (it.contains('.')) it.substringBefore('.') + "." + it.substringAfter('.').take(2) else it }

        _form.update { it.copy(amount = safe) }
    }

    /** 全部提现：直接填可用余额 */
    fun fillAll() {
        _form.update { it.copy(amount = String.format("%.2f", _wallet.value.balance)) }
    }

    fun setAccountType(type: String) = _form.update { it.copy(accountType = type) }
    fun setAccountName(value: String) = _form.update { it.copy(accountName = value.take(50)) }
    fun setAccountNo(value: String) = _form.update { it.copy(accountNo = value.take(100)) }

    /**
     * 手续费与到账金额的试算。
     *
     * 写成纯函数而不是直接读 _form/_config：那样做依赖「界面恰好也 collect 了这两个流」
     * 才能刷新，是隐式耦合；而且非 Compose 上下文里读 StateFlow 也容易出意外。
     * 界面用已经 collect 到的值调用即可。
     */
    companion object {
        /** 手续费 = 金额 × 费率%，四舍五入到分（与服务端算法一致） */
        fun feeOf(amount: Double, feeRatePercent: Double): Double =
            Math.round(amount * feeRatePercent) / 100.0

        fun realOf(amount: Double, feeRatePercent: Double): Double =
            amount - feeOf(amount, feeRatePercent)
    }

    fun submit() {
        val form = _form.value
        if (form.submitting) return

        val amount = form.amount.toDoubleOrNull() ?: 0.0
        if (amount < _config.value.min) {
            return notify("最低提现金额为 ${String.format("%.0f", _config.value.min)} 元")
        }
        if (amount > _wallet.value.balance + 0.00001) {
            return notify("可用余额不足")
        }
        if (form.accountName.isBlank()) return notify("请填写收款人姓名")
        if (form.accountNo.isBlank()) return notify("请填写收款账号")

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (result, msg) = ApiCall.callWithMessage {
                    api.withdraw(
                        WithdrawRequest(
                            amount = amount,
                            accountType = form.accountType,
                            accountName = form.accountName.trim(),
                            accountNo = form.accountNo.trim(),
                        )
                    )
                }
                notify(msg.ifBlank { "提现申请已提交" })
                Session.updateWallet(result.wallet)
                _done.tryEmit(Unit)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }
}
