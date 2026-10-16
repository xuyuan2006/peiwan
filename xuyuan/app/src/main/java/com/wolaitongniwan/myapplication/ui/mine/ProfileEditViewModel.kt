package com.wolaitongniwan.myapplication.ui.mine

import androidx.lifecycle.viewModelScope
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.core.Session
import com.wolaitongniwan.myapplication.data.remote.dto.SaveProfileRequest
import com.wolaitongniwan.myapplication.data.remote.dto.UserProfileDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.common.UiState
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import okhttp3.MultipartBody

data class ProfileFormState(
    val nickname: String = "",
    val phone: String = "",
    val email: String = "",
    val gender: Int = 0,
    /** 已上传头像的相对路径；留空表示不改 */
    val avatarPath: String = "",
    /** 本地预览地址 */
    val avatarPreview: String = "",
    val oldPassword: String = "",
    val newPassword: String = "",
    val confirmPassword: String = "",
    val uploading: Boolean = false,
    val submitting: Boolean = false,
)

class ProfileEditViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    private val _state = MutableStateFlow<UiState<UserProfileDto>>(UiState.Loading)
    val state: StateFlow<UiState<UserProfileDto>> = _state.asStateFlow()

    private val _form = MutableStateFlow(ProfileFormState())
    val form: StateFlow<ProfileFormState> = _form.asStateFlow()

    private val _done = MutableSharedFlow<String>(extraBufferCapacity = 1)
    val done: SharedFlow<String> = _done

    init { load() }

    fun load() {
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.userProfile() }
                _state.value = UiState.Success(data)
                _form.update {
                    it.copy(
                        nickname = data.user.nickname,
                        phone = data.user.phone,
                        email = data.user.email,
                        gender = data.user.gender,
                        avatarPreview = data.user.avatarUrl,
                    )
                }
            } catch (e: ApiException) {
                _state.value = UiState.Failure(e.message)
            }
        }
    }

    fun update(transform: (ProfileFormState) -> ProfileFormState) = _form.update(transform)

    fun uploadAvatar(part: MultipartBody.Part, previewUri: String) {
        if (_form.value.uploading) return
        _form.update { it.copy(uploading = true, avatarPreview = previewUri) }
        viewModelScope.launch {
            try {
                val data = ApiCall.call { api.uploadImage("avatar", part) }
                _form.update { it.copy(avatarPath = data.path, uploading = false) }
                notify("头像已上传，记得点保存")
            } catch (e: ApiException) {
                _form.update { it.copy(uploading = false) }
                notify(e.message)
            }
        }
    }

    fun submit() {
        val form = _form.value
        if (form.submitting) return
        if (form.nickname.isBlank()) return notify("昵称不能为空")

        // 改密码三项要么全空，要么都填对
        val changingPassword = form.newPassword.isNotBlank() || form.oldPassword.isNotBlank()
        if (changingPassword) {
            if (form.oldPassword.isBlank()) return notify("修改密码需要先填写当前密码")
            if (form.newPassword.length < 6) return notify("新密码至少 6 位")
            if (form.newPassword != form.confirmPassword) return notify("两次输入的新密码不一致")
        }

        _form.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val (_, msg) = ApiCall.callWithMessage {
                    api.saveProfile(
                        SaveProfileRequest(
                            nickname = form.nickname.trim(),
                            phone = form.phone.trim(),
                            email = form.email.trim().lowercase(),
                            gender = form.gender,
                            avatar = form.avatarPath,
                            oldPassword = if (changingPassword) form.oldPassword else "",
                            password = if (changingPassword) form.newPassword else "",
                        )
                    )
                }
                // 头像/昵称变了，同步全局状态，让「我的」页立刻更新
                try {
                    Session.update(ApiCall.call { api.me() })
                } catch (_: ApiException) {
                }
                notify(msg.ifBlank { "资料已更新" })
                _done.tryEmit(msg.ifBlank { "资料已更新" })
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _form.update { it.copy(submitting = false) }
            }
        }
    }
}
