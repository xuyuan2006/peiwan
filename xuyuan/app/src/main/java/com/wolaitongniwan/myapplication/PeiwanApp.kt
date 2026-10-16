package com.wolaitongniwan.myapplication

import android.app.Application
import com.wolaitongniwan.myapplication.core.AppContainer

class PeiwanApp : Application() {

    override fun onCreate() {
        super.onCreate()
        // 初始化网络层与本地令牌存储
        AppContainer.init(this)
    }
}
