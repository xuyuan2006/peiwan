package com.wolaitongniwan.myapplication.ui.auth

import android.os.Build
import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.BuildConfig
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.ForgotRequest
import com.wolaitongniwan.myapplication.data.remote.dto.LoginRequest
import com.wolaitongniwan.myapplication.data.remote.dto.MeDto
import com.wolaitongniwan.myapplication.data.remote.dto.RegisterRequest
import com.wolaitongniwan.myapplication.data.remote.dto.SiteConfigDto
import com.wolaitongniwan.myapplication.data.remote.dto.SendCodeRequest
import com.wolaitongniwan.myapplication.data.remote.dto.VerifyWrapDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class LoginFormState(
    val account: String = "",
    val password: String = "",
    val submitting: Boolean = false,
)

data class RegisterFormState(
    val password: String = "",
    val confirm: String = "",
    val nickname: String = "",
    val phone: String = "",
    val email: String = "",
    val gender: Int = 0,
    val code: String = "",
    val submitting: Boolean = false,
    val sending: Boolean = false,
    val countdown: Int = 0,
)

data class ForgotFormState(
    val email: String = "",
    val phone: String = "",
    val code: String = "",
    val password: String = "",
    val confirm: String = "",
    val submitting: Boolean = false,
    val sending: Boolean = false,
    val countdown: Int = 0,
)

class AuthViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }
    private val tokenStore by lazy { AppContainer.tokenStore }

    // ---------------------------------------------------------------- 登录

    private val _loginForm = MutableStateFlow(LoginFormState())
    val loginForm: StateFlow<LoginFormState> = _loginForm.asStateFlow()

    private val _loginSuccess = MutableSharedFlow<MeDto>(extraBufferCapacity = 1)
    val loginSuccess: SharedFlow<MeDto> = _loginSuccess

    // ---------------- 站点配置（登录页要用 site_logo / site_name） ----------------

    private val _siteConfig = MutableStateFlow(SiteConfigDto())
    val siteConfig: StateFlow<SiteConfigDto> = _siteConfig.asStateFlow()

    fun loadSiteConfig() {
        viewModelScope.launch {
            try {
                _siteConfig.value = ApiCall.call { api.siteConfig() }
            } catch (_: ApiException) {
                // 拉不到就用默认图标，不影响登录
            }
        }
    }

    fun onAccountChange(v: String) = _loginForm.update { it.copy(account = v) }
    fun onPasswordChange(v: String) = _loginForm.update { it.copy(password = v) }

    fun login() {
        val form = _loginForm.value
        if (form.account.isBlank() || form.password.isBlank()) {
            notify("请填写账号和密码")
            return
        }
        _loginForm.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val result = ApiCall.call {
                    api.login(
                        LoginRequest(
                            account = form.account.trim(),
                            password = form.password,
                            device = deviceName(),
                            appVersion = BuildConfig.VERSION_NAME,
                        )
                    )
                }
                saveSession(result.token, result.expireAt)
                _loginForm.update { it.copy(submitting = false) }
                _loginSuccess.tryEmit(result.me)
            } catch (e: ApiException) {
                _loginForm.update { it.copy(submitting = false) }
                notify(e.message)
            }
        }
    }

    // ---------------------------------------------------------------- 注册

    private val _registerForm = MutableStateFlow(RegisterFormState())
    val registerForm: StateFlow<RegisterFormState> = _registerForm.asStateFlow()

    private val _verify = MutableStateFlow(VerifyWrapDto())
    val verify: StateFlow<VerifyWrapDto> = _verify.asStateFlow()

    private val _registerSuccess = MutableSharedFlow<MeDto>(extraBufferCapacity = 1)
    val registerSuccess: SharedFlow<MeDto> = _registerSuccess

    private var registerTimer: Job? = null

    fun loadVerifyChannel() {
        viewModelScope.launch {
            try {
                _verify.value = ApiCall.call { api.verifyChannel() }
            } catch (_: ApiException) {
                // 拉不到就按「不需要验证码」处理（后端 off 是默认值），不打断注册流程
            }
        }
    }

    fun onRegisterChange(transform: (RegisterFormState) -> RegisterFormState) {
        _registerForm.update(transform)
    }

    /** 注册页发验证码：按后台配置决定发给邮箱还是手机号 */
    fun sendRegisterCode() {
        val form = _registerForm.value
        val channel = _verify.value.register.channel
        val target = when (channel) {
            "email" -> form.email.trim()
            "sms" -> form.phone.trim()
            else -> {
                notify("当前无需验证码，直接注册即可")
                return
            }
        }
        if (target.isBlank()) {
            notify(if (channel == "email") "请先填写邮箱" else "请先填写手机号")
            return
        }
        sendCode("register", target, onSending = { v -> _registerForm.update { it.copy(sending = v) } }) { sec ->
            _registerForm.update { it.copy(countdown = sec) }
        }
    }

    fun register() {
        val form = _registerForm.value
        if (form.submitting) return

        // 客户端先做一轮校验，减少无谓请求（服务端仍会再校验一次）
        when {
            form.nickname.isBlank() -> return notify("请填写昵称")
            form.password.length < 6 -> return notify("密码至少 6 位")
            form.password != form.confirm -> return notify("两次输入的密码不一致")
            form.phone.isBlank() -> return notify("请填写手机号")
            form.email.isBlank() -> return notify("请填写邮箱")
        }

        _registerForm.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val result = ApiCall.call {
                    api.register(
                        RegisterRequest(
                            nickname = form.nickname.trim(),
                            password = form.password,
                            passwordConfirm = form.confirm,
                            phone = form.phone.trim(),
                            email = form.email.trim().lowercase(),
                            gender = form.gender,
                            code = form.code.trim(),
                            device = deviceName(),
                            appVersion = BuildConfig.VERSION_NAME,
                        )
                    )
                }
                saveSession(result.token, result.expireAt)
                _registerForm.update { it.copy(submitting = false) }
                _registerSuccess.tryEmit(result.me)
            } catch (e: ApiException) {
                _registerForm.update { it.copy(submitting = false) }
                notify(e.message)
            }
        }
    }

    // ---------------------------------------------------------------- 找回密码

    private val _forgotForm = MutableStateFlow(ForgotFormState())
    val forgotForm: StateFlow<ForgotFormState> = _forgotForm.asStateFlow()

    private val _forgotSuccess = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val forgotSuccess: SharedFlow<Unit> = _forgotSuccess

    private var forgotTimer: Job? = null

    fun onForgotChange(transform: (ForgotFormState) -> ForgotFormState) {
        _forgotForm.update(transform)
    }

    fun sendForgotCode() {
        val form = _forgotForm.value
        val channel = _verify.value.reset.channel
        if (channel == "off") {
            notify("平台未开启验证码找回密码，请联系客服")
            return
        }
        val target = if (channel == "email") form.email.trim() else form.phone.trim()
        if (target.isBlank()) {
            notify(if (channel == "email") "请先填写邮箱" else "请先填写手机号")
            return
        }
        sendCode("reset", target, onSending = { v -> _forgotForm.update { it.copy(sending = v) } }) { sec ->
            _forgotForm.update { it.copy(countdown = sec) }
        }
    }

    fun resetPassword() {
        val form = _forgotForm.value
        if (form.submitting) return
        when {
            form.password.length < 6 -> return notify("新密码至少 6 位")
            form.password != form.confirm -> return notify("两次输入的密码不一致")
            form.code.isBlank() -> return notify("请填写验证码")
        }

        _forgotForm.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                ApiCall.callUnit {
                    api.forgot(
                        ForgotRequest(
                            email = form.email.trim().lowercase(),
                            phone = form.phone.trim(),
                            code = form.code.trim(),
                            password = form.password,
                            passwordConfirm = form.confirm,
                        )
                    )
                }
                _forgotForm.update { it.copy(submitting = false) }
                _forgotSuccess.tryEmit(Unit)
            } catch (e: ApiException) {
                _forgotForm.update { it.copy(submitting = false) }
                notify(e.message)
            }
        }
    }

    // ---------------------------------------------------------------- 内部

    private suspend fun saveSession(token: String, expireAt: String) {
        if (token.isBlank()) {
            throw ApiException(0, "服务端未返回登录令牌")
        }
        tokenStore.save(token, expireAt)
    }

    /** 发验证码 + 60 秒倒计时；两个页面共用 */
    private fun sendCode(
        scene: String,
        target: String,
        onSending: (Boolean) -> Unit,
        onTick: (Int) -> Unit,
    ) {
        onSending(true)
        viewModelScope.launch {
            try {
                ApiCall.callUnit { api.sendCode(SendCodeRequest(scene = scene, target = target)) }
                notify("验证码已发送，请查收")
                startCountdown(scene, onTick)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                onSending(false)
            }
        }
    }

    private fun startCountdown(scene: String, onTick: (Int) -> Unit) {
        // 先把同一个页面上一轮的倒计时取消，避免连点导致多个协程同时改秒数
        if (scene == "register") registerTimer?.cancel() else forgotTimer?.cancel()

        val job = viewModelScope.launch {
            for (sec in 60 downTo 1) {
                onTick(sec)
                delay(1000)
            }
            onTick(0)
        }

        if (scene == "register") {
            registerTimer = job
        } else {
            forgotTimer = job
        }
    }

    private fun deviceName(): String =
        listOf(Build.MANUFACTURER, Build.MODEL)
            .filter { it.isNotBlank() }
            .joinToString(" ")
            .take(100)

    override fun onCleared() {
        registerTimer?.cancel()
        forgotTimer?.cancel()
        super.onCleared()
    }
}
