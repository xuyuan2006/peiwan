package com.wolaitongniwan.myapplication.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberUpdatedState
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import kotlinx.coroutines.delay

/**
 * 周期性刷新：[intervalMs] 毫秒调用一次 [onTick]。
 *
 * 用法（放在页面里一行即可）：
 * ```
 * AutoRefresh { vm.refresh() }
 * ```
 *
 * ## 两个关键设计
 *
 * **1) 只在页面真正可见时运行**
 * App 切到后台时 Compose **不会**销毁页面 —— 如果不管生命周期，这个循环会在后台
 * 一直跑下去，白耗电和流量。所以用 `repeatOnLifecycle(RESUMED)` 把它挂到生命周期上：
 * 切到后台自动挂起，回到前台自动继续。
 *
 * **2) 离开页面自动停止**
 * 循环跑在 [LaunchedEffect] 里，页面离开组合（比如切换底部 Tab）时会被自动取消，
 * 不需要任何手动清理。
 *
 * ## 用 rememberUpdatedState 的原因
 * 调用方通常写成 `AutoRefresh { vm.refresh() }`，每帧都会生成一个新的 lambda。
 * 如果直接把它作为 `LaunchedEffect` 的 key，循环会被反复重启、永远等不到第一次 delay。
 * 用 `rememberUpdatedState` 记住最新回调，`LaunchedEffect` 就只依赖 lifecycleOwner。
 */
@Composable
fun AutoRefresh(intervalMs: Long = 5_000L, onTick: () -> Unit) {
    val owner = LocalLifecycleOwner.current
    val tick by rememberUpdatedState(onTick)

    LaunchedEffect(owner) {
        owner.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            while (true) {
                delay(intervalMs)
                tick()
            }
        }
    }
}
