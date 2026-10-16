package com.wolaitongniwan.myapplication.ui.companion

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.ApiResponse
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.ApplyCompanionRequest
import com.wolaitongniwan.myapplication.data.remote.dto.CompanionCenterDto
import com.wolaitongniwan.myapplication.data.remote.dto.GameDto
import com.wolaitongniwan.myapplication.data.remote.dto.IdRequest
import com.wolaitongniwan.myapplication.data.remote.dto.MyCompanionProfileDto
import com.wolaitongniwan.myapplication.data.remote.dto.SaveSkillRequest
import com.wolaitongniwan.myapplication.data.remote.dto.ServiceTypeDto
import com.wolaitongniwan.myapplication.data.remote.dto.SiteConfigDto
import com.wolaitongniwan.myapplication.data.remote.dto.SkillDto
import com.wolaitongniwan.myapplication.data.remote.dto.ToggleOnlineRequest
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

// ---------------------------------------------------------------- 陪玩中心

class CompanionCenterViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<CompanionCenterDto>>(UiState.Loading)
    val state: StateFlow<UiState<CompanionCenterDto>> = _state.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    init { load() }

    fun load() {
        if (_state.value !is UiState.Success) {
            _state.value = UiState.Loading
        }
        viewModelScope.launch { fetch() }
    }

    private suspend fun fetch() {
        try {
            _state.value = UiState.Success(ApiCall.call { api.companionCenter() })
        } catch (e: ApiException) {
            if (_state.value !is UiState.Success) {
                _state.value = UiState.Failure(e.message)
            }
            notify(e.message)
        }
    }

    fun toggleOnline(online: Boolean) {
        action("设置失败") { api.toggleOnline(ToggleOnlineRequest(online)) }
    }

    fun toggleSkill(id: Long) {
        action("操作失败") { api.toggleSkill(IdRequest(id)) }
    }

    fun deleteSkill(id: Long) {
        action("删除失败") { api.deleteSkill(IdRequest(id)) }
    }

    /** 统一的写操作包装：完成后刷新，保证中心页数据与服务端一致 */
    private fun <T> action(fallback: String, block: suspend () -> ApiResponse<T>) {
        if (_busy.value) return
        _busy.value = true
        viewModelScope.launch {
            try {
                val msg = ApiCall.message(block)
                notify(msg.ifBlank { fallback })
            } catch (e: ApiException) {
                notify(e.message.ifBlank { fallback })
            } finally {
                _busy.value = false
                fetch()
            }
        }
    }
}

// ---------------------------------------------------------------- 入驻申请

data class ApplyFormState(
    val realName: String = "",
    val phone: String = "",
    val idCard: String = "",
    val gender: Int = 0,
    val city: String = "",
    val intro: String = "",
    val tags: String = "",
    val submitting: Boolean = false,
)

class CompanionApplyViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _form = MutableStateFlow(ApplyFormState())
    val form: StateFlow<ApplyFormState> = _form.asStateFlow()

    private val _existing = MutableStateFlow<MyCompanionProfileDto?>(null)
    val existing: StateFlow<MyCompanionProfileDto?> = _existing.asStateFlow()

    private val _auditText = MutableStateFlow("未申请")
    val auditText: StateFlow<String> = _auditText.asStateFlow()

    private val _auditRemark = MutableStateFlow("")
    val auditRemark: StateFlow<String> = _auditRemark.asStateFlow()

    /** 站点配置：考核标准 + 申请后的联系方式（都由后台填写） */
    private val _config = MutableStateFlow(SiteConfigDto())
    val config: StateFlow<SiteConfigDto> = _config.asStateFlow()

    /** 提交成功后是否展示联系方式弹窗（后台没填联系方式就不弹，直接返回） */
    private val _showContact = MutableStateFlow(false)
    val showContact: StateFlow<Boolean> = _showContact.asStateFlow()

    private val _done = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val done: SharedFlow<Unit> = _done

    init {
        load()
        loadConfig()
    }

    private fun loadConfig() {
        viewModelScope.launch {
            try {
                _config.value = ApiCall.call { api.siteConfig() }
            } catch (_: ApiException) {
                // 拉不到就按「没有考核标准、没有联系方式」处理，不影响正常申请
            }
        }
    }

    /** 联系方式弹窗点「知道了」之后才真正返回上一页 */
    fun dismissContact() {
        _showContact.value = false
        _done.tryEmit(Unit)
    }

    /** 已有申请时把资料回填，避免用户重新填一遍 */
    fun load() {
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.companionProfile() }
                _auditText.value = data.auditText
                _auditRemark.value = data.auditRemark
                data.profile?.let { p ->
                    _existing.value = p
                    _form.value = ApplyFormState(
                        realName = p.realName,
                        phone = p.phone,
                        idCard = p.idCard,
                        gender = p.gender,
                        city = p.city,
                        intro = p.intro,
                        tags = p.tags.joinToString(","),
                    )
                }
            } catch (_: ApiException) {
                // 拉不到就按新申请处理
            }
        }
    }

    fun update(transform: (ApplyFormState) -> ApplyFormState) = _form.update(transform)

    fun submit() {
        val form = _form.value
        if (form.submitting) return
        if (form.realName.isBlank()) return notify("请填写真实姓名")
        if (form.phone.isBlank()) return notify("请填写手机号")
        if (form.idCard.isBlank()) return notify("请填写身份证号（平台要求实名）")

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (result, msg) = ApiCall.callWithMessage {
                    api.applyCompanion(
                        ApplyCompanionRequest(
                            realName = form.realName.trim(),
                            phone = form.phone.trim(),
                            idCard = form.idCard.trim(),
                            gender = form.gender,
                            city = form.city.trim(),
                            intro = form.intro.trim(),
                            tags = form.tags.trim(),
                        )
                    )
                }
                notify(msg.ifBlank { "申请已提交" })
                _auditText.value = result.auditText
                // 身份可能变化（后台关掉审核时立即通过），刷新全局状态让底部导航跟着变
                try {
                    Session.update(ApiCall.call { api.me() })
                } catch (_: ApiException) {
                }

                // 后台配了联系方式就先弹出来（引导申请者加客服），没配就直接返回
                val cfg = _config.value
                if (cfg.companionContactQr.isNotBlank() || cfg.companionContactText.isNotBlank()) {
                    _showContact.value = true
                } else {
                    _done.tryEmit(Unit)
                }
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }
}

// ---------------------------------------------------------------- 服务项目编辑

data class SkillFormState(
    val id: Long = 0,
    val gameId: Long = 0,
    val title: String = "",
    val serviceType: String = "hour",
    val rankLevel: String = "",
    val price: String = "",
    val description: String = "",
    /** 已上传配图的相对路径 */
    val coverPath: String = "",
    /** 本地预览地址（相册 Uri 或已有的服务器地址） */
    val coverPreview: String = "",
    val uploading: Boolean = false,
    val submitting: Boolean = false,
)

class SkillEditViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _form = MutableStateFlow(SkillFormState())
    val form: StateFlow<SkillFormState> = _form.asStateFlow()

    private val _games = MutableStateFlow<List<GameDto>>(emptyList())
    val games: StateFlow<List<GameDto>> = _games.asStateFlow()

    private val _serviceTypes = MutableStateFlow<List<ServiceTypeDto>>(emptyList())
    val serviceTypes: StateFlow<List<ServiceTypeDto>> = _serviceTypes.asStateFlow()

    private val _done = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val done: SharedFlow<Unit> = _done

    fun load(skillId: Long) {
        viewModelScope.launch {
            try {
                // skills 接口同时返回可选游戏与服务类型，一次请求够用
                val data = ApiCall.call { api.skills(all = 1) }
                _games.value = data.games
                if (data.serviceTypes.isNotEmpty()) {
                    _serviceTypes.value = data.serviceTypes
                }
                if (skillId > 0) {
                    data.list.firstOrNull { it.id == skillId }?.let { fill(it) }
                }
            } catch (e: ApiException) {
                notify(e.message)
            }
        }
    }

    private fun fill(skill: SkillDto) {
        _form.value = SkillFormState(
            id = skill.id,
            gameId = skill.gameId,
            title = skill.title,
            serviceType = skill.serviceType,
            rankLevel = skill.rankLevel,
            price = if (skill.price > 0) String.format("%.2f", skill.price) else "",
            description = skill.description,
            // 编辑时不重新上传就保持原图：coverPath 留空，后端会沿用旧图
            coverPath = "",
            coverPreview = skill.coverUrl,
        )
    }

    fun update(transform: (SkillFormState) -> SkillFormState) = _form.update(transform)

    fun setPrice(value: String) {
        val filtered = value.filter { it.isDigit() || it == '.' }
        val safe = if (filtered.count { it == '.' } > 1) {
            filtered.substringBefore('.') + "." + filtered.substringAfter('.').replace(".", "")
        } else {
            filtered
        }.let { if (it.contains('.')) it.substringBefore('.') + "." + it.substringAfter('.').take(2) else it }
        _form.update { it.copy(price = safe) }
    }

    fun uploadCover(part: okhttp3.MultipartBody.Part, previewUri: String) {
        if (_form.value.uploading) return
        _form.update { it.copy(uploading = true, coverPreview = previewUri) }
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.uploadImage("skill", part) }
                _form.update { it.copy(coverPath = data.path, uploading = false) }
                notify("配图已上传")
            } catch (e: ApiException) {
                _form.update { it.copy(uploading = false) }
                notify(e.message)
            }
        }
    }

    fun submit() {
        val form = _form.value
        if (form.submitting) return
        if (form.gameId <= 0) return notify("请选择游戏")
        if (form.title.isBlank()) return notify("请填写服务标题")
        val price = form.price.toDoubleOrNull() ?: 0.0
        if (price <= 0) return notify("价格必须大于 0")

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (_, msg) = ApiCall.callWithMessage {
                    api.saveSkill(
                        SaveSkillRequest(
                            id = form.id,
                            gameId = form.gameId,
                            title = form.title.trim(),
                            serviceType = form.serviceType,
                            rankLevel = form.rankLevel.trim(),
                            price = price,
                            description = form.description.trim(),
                            cover = form.coverPath,
                        )
                    )
                }
                notify(msg.ifBlank { "已保存" })
                _done.tryEmit(Unit)
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }
}
