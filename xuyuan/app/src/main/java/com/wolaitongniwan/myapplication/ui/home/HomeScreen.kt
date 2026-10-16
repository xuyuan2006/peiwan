package com.wolaitongniwan.myapplication.ui.home

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.wolaitongniwan.myapplication.data.remote.dto.AnnouncementDto
import com.wolaitongniwan.myapplication.data.remote.dto.BannerDto
import com.wolaitongniwan.myapplication.data.remote.dto.HomeDto
import com.wolaitongniwan.myapplication.data.remote.dto.PackageDto
import com.wolaitongniwan.myapplication.ui.companion.CompanionCard
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.AutoRefresh
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.SectionTitle
import com.wolaitongniwan.myapplication.ui.components.StateBox
import kotlinx.coroutines.delay

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onOpenCompanion: (Long) -> Unit,
    onOpenPackages: () -> Unit,
    onOpenSearch: () -> Unit,
    vm: HomeViewModel = viewModel(),
) {
    val state by vm.state.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    MessageEffect(vm.messages, snackbar)

    // 每次进入本页（含点击底部 Tab）都重拉一次数据，保证看到的是最新状态
    LaunchedEffect(Unit) { vm.refresh() }

    // 停留在这个页面期间，每 5 秒自动向服务器同步一次；切到后台会自动暂停
    AutoRefresh { vm.refresh() }

    Box(Modifier.fillMaxSize()) {
        StateBox(state = state, onRetry = vm::load) { data ->
            PullToRefreshBox(
                isRefreshing = refreshing,
                onRefresh = vm::refresh,
                modifier = Modifier.fillMaxSize(),
            ) {
                HomeContent(
                    data = data,
                    onOpenCompanion = onOpenCompanion,
                    onOpenPackages = onOpenPackages,
                    onOpenSearch = onOpenSearch,
                )
            }
        }

        SnackbarHost(
            hostState = snackbar,
            modifier = Modifier.align(Alignment.BottomCenter),
        )
    }
}

@Composable
private fun HomeContent(
    data: HomeDto,
    onOpenCompanion: (Long) -> Unit,
    onOpenPackages: () -> Unit,
    onOpenSearch: () -> Unit,
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 24.dp),
        verticalArrangement = Arrangement.spacedBy(0.dp),
    ) {
        // ---------------- 顶部：搜索 + 轮播 ----------------
        item {
            Box {
                BannerCarousel(
                    banners = data.banners,
                    intervalSeconds = data.bannerInterval,
                )
                // 搜索框叠在轮播上
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(40.dp)
                            .clip(RoundedCornerShape(20.dp))
                            .background(Color.White.copy(alpha = 0.92f))
                            .clickable(onClick = onOpenSearch),
                        contentAlignment = Alignment.CenterStart,
                    ) {
                        Text(
                            text = "  搜索陪玩、游戏、套餐",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.outline,
                        )
                    }
                }
            }
        }

        // ---------------- 公告走马灯 ----------------
        if (data.announcements.isNotEmpty() || data.siteNotice.isNotBlank()) {
            item {
                NoticeBar(
                    announcements = data.announcements,
                    fallback = data.siteNotice,
                    intervalSeconds = data.noticeInterval,
                )
            }
        }

        // ---------------- 游戏分类 ----------------
        if (data.games.isNotEmpty()) {
            item {
                LazyRow(
                    contentPadding = PaddingValues(horizontal = 16.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(data.games, key = { it.id }) { game ->
                        FilterChip(
                            selected = false,
                            onClick = onOpenSearch,
                            label = { Text(game.name) },
                        )
                    }
                }
            }
        }

        // ---------------- 直接点单（套餐） ----------------
        if (data.packages.isNotEmpty()) {
            item {
                SectionTitle(
                    title = "直接点单",
                    actionText = "全部套餐 →",
                    onAction = onOpenPackages,
                )
            }
            item {
                LazyRow(
                    contentPadding = PaddingValues(horizontal = 16.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    items(data.packages, key = { it.id }) { pkg ->
                        PackageCard(pkg = pkg, onClick = onOpenPackages)
                    }
                }
            }
        }

        // ---------------- 推荐陪玩 ----------------
        item {
            SectionTitle(
                title = "推荐陪玩",
                actionText = if (data.companionTotal > 0) "共 ${data.companionTotal} 位" else null,
                onAction = null,
            )
        }

        if (data.companions.isEmpty()) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(32.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = "还没有陪玩入驻，先去「成为陪玩」申请一个吧～",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        } else {
            items(data.companions, key = { it.userId }) { companion ->
                CompanionCard(
                    item = companion,
                    onClick = { onOpenCompanion(companion.userId) },
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp),
                )
            }
        }
    }
}

// ---------------------------------------------------------------- 子组件

/** 首图轮播：自动翻页 + 圆点指示，只有一张时不启动定时器 */
// HorizontalPager 在 foundation 1.7+ 已稳定，但补上 OptIn 是零成本的保险：
// 万一依赖解析到更低版本，这里也不会突然编不过。
@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun BannerCarousel(banners: List<BannerDto>, intervalSeconds: Int) {
    if (banners.isEmpty()) {
        // 没有配广告位时给一个品牌色占位，避免顶部空一大块
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(180.dp)
                .background(
                    Brush.verticalGradient(
                        listOf(
                            MaterialTheme.colorScheme.primary,
                            MaterialTheme.colorScheme.primaryContainer,
                        )
                    )
                ),
        )
        return
    }

    val pagerState = rememberPagerState(pageCount = { banners.size })
    val interval = intervalSeconds.coerceIn(2, 60)
    val context = LocalContext.current

    LaunchedEffect(banners.size, interval) {
        if (banners.size <= 1) return@LaunchedEffect
        while (true) {
            delay(interval * 1000L)
            val next = (pagerState.currentPage + 1) % banners.size
            pagerState.animateScrollToPage(next)
        }
    }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(180.dp),
    ) {
        HorizontalPager(
            state = pagerState,
            modifier = Modifier.fillMaxSize(),
        ) { page ->
            val banner = banners[page]
            // 后端给的是「自助填写的跳转链接」，可能为空、也可能是相对路径。
            // 只处理明确以 http 开头的，其余一律当纯展示图，避免点了没反应或崩掉。
            val openable = banner.linkUrl.startsWith("http")
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .clickable(enabled = openable) {
                        runCatching {
                            context.startActivity(
                                Intent(Intent.ACTION_VIEW, Uri.parse(banner.linkUrl))
                            )
                        }
                    },
            ) {
                AsyncImage(
                    model = banner.imageUrl,
                    contentDescription = banner.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize(),
                )
            }
        }

        if (banners.size > 1) {
            Row(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(bottom = 10.dp),
                horizontalArrangement = Arrangement.spacedBy(5.dp),
            ) {
                repeat(banners.size) { index ->
                    val active = pagerState.currentPage == index
                    Box(
                        modifier = Modifier
                            .size(if (active) 7.dp else 5.dp)
                            .clip(CircleShape)
                            .background(
                                if (active) Color.White else Color.White.copy(alpha = 0.5f)
                            ),
                    )
                }
            }
        }
    }
}

/** 公告走马灯：没有公告时回落到站点公告文案 */
@Composable
private fun NoticeBar(
    announcements: List<AnnouncementDto>,
    fallback: String,
    intervalSeconds: Int,
) {
    val texts = remember(announcements, fallback) {
        if (announcements.isNotEmpty()) announcements.map { it.content } else listOf(fallback)
    }
    var index by remember { mutableIntStateOf(0) }
    val interval = intervalSeconds.coerceIn(2, 60)

    LaunchedEffect(texts.size, interval) {
        if (texts.size <= 1) return@LaunchedEffect
        while (true) {
            delay(interval * 1000L)
            index = (index + 1) % texts.size
        }
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp)
            .clip(RoundedCornerShape(8.dp))
            .background(MaterialTheme.colorScheme.surfaceVariant)
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = "公告",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.primary,
            fontWeight = FontWeight.Bold,
        )
        Spacer(Modifier.width(8.dp))
        Text(
            text = texts.getOrElse(index) { "" },
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
    }
}

/** 一口价套餐卡片 */
@Composable
private fun PackageCard(pkg: PackageDto, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .width(190.dp)
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column {
            if (pkg.coverUrl.isNotBlank()) {
                AsyncImage(
                    model = pkg.coverUrl,
                    contentDescription = pkg.title,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(96.dp),
                )
            } else {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(96.dp)
                        .background(MaterialTheme.colorScheme.primaryContainer),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        text = "点单套餐",
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                    )
                }
            }

            Column(Modifier.padding(10.dp)) {
                Text(
                    text = pkg.title,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Medium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Spacer(Modifier.height(6.dp))
                MoneyText(text = pkg.priceText, fontSize = 16.sp)
            }
        }
    }
}
