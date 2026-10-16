package com.wolaitongniwan.myapplication.ui.wallet

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeRequest
import com.wolaitongniwan.myapplication.data.remote.dto.RechargeResultDto
import com.wolaitongniwan.myapplication.data.remote.dto.SiteConfigDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import okhttp3.MultipartBody

data class RechargeFormState(
    val amount: String = "",
    /** 已上传凭证的相对路径（提交时传给后端的就是它） */
    val voucherPath: String = "",
    /** 本地预览用的图片地址（相册 Uri 字符串） */
    val voucherPreview: String = "",
    val remark: String = "",
    /** 在线支付下的具体方式：wxpay（微信）/ alipay（支付宝）。仅易支付会用到。 */
    val payType: String = "alipay",
    val uploading: Boolean = false,
    val submitting: Boolean = false,
)

class RechargeViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _form = MutableStateFlow(RechargeFormState())
    val form: StateFlow<RechargeFormState> = _form.asStateFlow()

    private val _config = MutableStateFlow(SiteConfigDto())
    val config: StateFlow<SiteConfigDto> = _config.asStateFlow()

    /** 下单结果：非空时界面根据 payMode 决定「跳浏览器 / 展示二维码 / 提示等待审核」 */
    private val _result = MutableStateFlow<RechargeResultDto?>(null)
    val result: StateFlow<RechargeResultDto?> = _result.asStateFlow()

    init {
        loadConfig()
    }

    /** 拉站点配置，主要是为了知道当前支付渠道（决定要不要上传凭证） */
    fun loadConfig() {
        viewModelScope.launch {
            try {
                _config.value = ApiCall.call { api.siteConfig() }
            } catch (_: ApiException) {
                // 拉不到就按人工转账处理（后端默认渠道也是 manual）
            }
        }
    }

    /**
     * 当前是否人工转账渠道（决定要不要上传凭证）。
     *
     * 只以后台配置的 `pay_channel` 为准 —— UI 与提交流程必须用同一个判断，
     * 否则会出现「界面显示人工转账、提交时却按在线支付校验」这种自相矛盾。
     */
    fun isManualChannel(): Boolean = _config.value.payChannel == "manual"

    fun setAmount(value: String) {
        // 只允许数字和一个小数点，最多两位小数
        val filtered = value.filter { it.isDigit() || it == '.' }
        val parts = filtered.split('.')
        val safe = if (parts.size > 2) {
            parts[0] + "." + parts.drop(1).joinToString("")
        } else {
            filtered
        }.let { if (it.contains('.')) it.substringBefore('.') + "." + it.substringAfter('.').take(2) else it }

        _form.update { it.copy(amount = safe) }
    }

    fun setRemark(value: String) = _form.update { it.copy(remark = value.take(200)) }

    /** 易支付下的支付方式：wxpay / alipay */
    fun setPayType(value: String) = _form.update { it.copy(payType = value) }

    /**
     * 从浏览器付完款切回 App 时调用。
     *
     * 支付结果最终以服务端回调为准，这里只是把登录态（含钱包余额）重新拉一遍，
     * 让用户切回来的第一眼就能看到最新余额，不用手动下拉。
     */
    fun refreshAfterPay() {
        viewModelScope.launch {
            try {
                Session.update(ApiCall.call { api.me() })
            } catch (_: ApiException) {
                // 刷新失败不打扰用户，余额下次进钱包页时也会重新拉
            }
        }
    }

    fun uploadVoucher(part: MultipartBody.Part, previewUri: String) {
        if (_form.value.uploading) return
        _form.update { it.copy(uploading = true, voucherPreview = previewUri) }
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.uploadImage("voucher", part) }
                _form.update { it.copy(voucherPath = data.path, uploading = false) }
                notify("凭证已上传")
            } catch (e: ApiException) {
                _form.update { it.copy(uploading = false, voucherPreview = "") }
                notify(e.message)
            }
        }
    }

    fun clearVoucher() = _form.update { it.copy(voucherPath = "", voucherPreview = "") }

    fun submit() {
        val form = _form.value
        if (form.submitting) return

        val amount = form.amount.toDoubleOrNull() ?: 0.0
        if (amount < 1) return notify("充值金额不能小于 1 元")
        if (amount > 50000) return notify("单次充值不能超过 50000 元")
        if (isManualChannel() && form.voucherPath.isBlank()) {
            return notify("人工转账需要先上传转账凭证")
        }

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (data, msg) = ApiCall.callWithMessage {
                    api.recharge(
                        RechargeRequest(
                            amount = amount,
                            voucher = form.voucherPath,
                            remark = form.remark.trim(),
                            payType = form.payType,
                        )
                    )
                }
                notify(msg.ifBlank { "充值申请已提交" })
                _result.value = data
                // 重置表单，避免用户返回后重复提交
                _form.value = RechargeFormState()
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }

    fun consumeResult() {
        _result.value = null
    }
}
