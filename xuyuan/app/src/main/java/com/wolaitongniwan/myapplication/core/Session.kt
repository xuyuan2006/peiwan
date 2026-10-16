package com.wolaitongniwan.myapplication.core

import com.wolaitongniwan.myapplication.data.remote.dto.MeDto
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * 当前登录用户的全局状态。
 *
 * 登录成功、或 App 启动时用本地令牌拉 `auth/me` 之后写入这里；
 * 各页面（比如底部导航要不要显示「接单」Tab）响应式地读它。
 *
 * 之所以要单独放一份：底部导航、个人中心、陪玩中心都需要「我是谁 / 我是不是陪玩」，
 * 如果每个页面都自己去请求一次 auth/me，既慢又容易出现状态不一致。
 */
object Session {

    private val _me = MutableStateFlow<MeDto?>(null)
    val me: StateFlow<MeDto?> = _me.asStateFlow()

    val current: MeDto? get() = _me.value

    fun update(value: MeDto?) {
        _me.value = value
    }

    fun isLoggedIn(): Boolean = _me.value != null

    /** 是否「已通过审核的陪玩」——决定是否显示接单大厅入口 */
    fun canWork(): Boolean {
        val companion = _me.value?.companion ?: return false
        return companion.applied && companion.auditStatus == 1
    }

    /** 刷新钱包余额（下单、支付、充值后调用，避免各处余额显示不一致） */
    fun updateWallet(wallet: com.wolaitongniwan.myapplication.data.remote.dto.WalletDto) {
        val current = _me.value ?: return
        _me.value = current.copy(wallet = wallet)
    }
}
