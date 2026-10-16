package com.wolaitongniwan.myapplication.ui.navigation

/**
 * 全部路由。
 *
 * 需要参数的页面统一用「模板常量 + 构造函数」的写法：
 * 常量给 NavHost 注册用（含 {id} 占位符），构造函数给跳转用。
 */
object Routes {

    // ---------------- 根 ----------------
    const val MAIN = "main"

    // ---------------- 鉴权 ----------------
    const val LOGIN = "login"
    const val REGISTER = "register"
    const val FORGOT = "forgot"
    const val PROFILE_EDIT = "profile_edit"

    // ---------------- 内容浏览 ----------------
    const val PACKAGES = "packages"
    const val SEARCH = "search"

    const val COMPANION_DETAIL = "companion_detail/{id}"
    fun companionDetail(id: Long) = "companion_detail/$id"

    // ---------------- 订单 ----------------
    const val ORDER_DETAIL = "order_detail/{id}"
    fun orderDetail(id: Long) = "order_detail/$id"

    const val ORDER_CHAT = "order_chat/{id}"
    fun orderChat(id: Long) = "order_chat/$id"

    const val ORDER_DETAIL_ARG = "id"

    const val REVIEW_CREATE = "review_create/{orderId}"
    fun reviewCreate(orderId: Long) = "review_create/$orderId"
    const val REVIEW_CREATE_ARG = "orderId"

    const val COMPANION_ORDERS = "companion_orders"

    // ---------------- 钱包 ----------------
    const val WALLET = "wallet"
    const val TRANSACTIONS = "transactions"
    const val RECHARGE = "recharge"
    const val RECHARGE_DETAIL = "recharge_detail/{id}"
    fun rechargeDetail(id: Long) = "recharge_detail/$id"
    const val RECHARGE_LIST = "recharge_list"
    const val WITHDRAW = "withdraw"
    const val WITHDRAW_LIST = "withdraw_list"

    // ---------------- 陪玩端 ----------------
    const val COMPANION_CENTER = "companion_center"
    const val COMPANION_APPLY = "companion_apply"
    const val SKILL_LIST = "skill_list"
    const val SKILL_EDIT = "skill_edit?id={id}"
    fun skillEdit(id: Long = 0) = "skill_edit?id=$id"
    const val SKILL_EDIT_ARG = "id"

    // ---------------- 工单 ----------------
    const val TICKET_LIST = "ticket_list"
    const val TICKET_DETAIL = "ticket_detail/{id}"
    fun ticketDetail(id: Long) = "ticket_detail/$id"
    const val TICKET_CREATE = "ticket_create?orderId={orderId}"
    fun ticketCreate(orderId: Long = 0) = "ticket_create?orderId=$orderId"
    const val TICKET_CREATE_ARG = "orderId"

    const val MY_REVIEWS = "my_reviews"
}
