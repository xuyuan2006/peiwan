package com.wolaitongniwan.myapplication.ui.search

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import com.wolaitongniwan.myapplication.core.ApiCall
import com.wolaitongniwan.myapplication.core.ApiException
import com.wolaitongniwan.myapplication.core.AppContainer
import com.wolaitongniwan.myapplication.data.remote.dto.PackageDto
import com.wolaitongniwan.myapplication.data.remote.dto.SearchResultDto
import com.wolaitongniwan.myapplication.ui.common.BaseViewModel
import com.wolaitongniwan.myapplication.ui.components.AppScaffold
import com.wolaitongniwan.myapplication.ui.components.MessageEffect
import com.wolaitongniwan.myapplication.ui.components.MoneyText
import com.wolaitongniwan.myapplication.ui.components.SectionTitle
import com.wolaitongniwan.myapplication.ui.companion.CompanionCard
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class SearchViewModel : BaseViewModel() {

    private val api by lazy { AppContainer.api }

    /** null 表示还没搜过 */
    private val _state = MutableStateFlow<SearchResultDto?>(null)
    val state: StateFlow<SearchResultDto?> = _state.asStateFlow()

    private val _searching = MutableStateFlow(false)
    val searching: StateFlow<Boolean> = _searching.asStateFlow()

    private val _searched = MutableStateFlow(false)
    val searched: StateFlow<Boolean> = _searched.asStateFlow()

    fun search(keyword: String) {
        val kw = keyword.trim()
        if (kw.isEmpty()) {
            notify("请输入搜索关键词")
            return
        }
        if (_searching.value) return

        _searching.value = true
        viewModelScope.launch {
            try {
                _state.value = ApiCall.call { api.search(kw) }
                _searched.value = true
            } catch (e: ApiException) {
                notify(e.message)
            } finally {
                _searching.value = false
            }
        }
    }
}

@Composable
fun SearchScreen(
    onBack: () -> Unit,
    onOpenCompanion: (Long) -> Unit,
    onOpenPackages: () -> Unit,
    vm: SearchViewModel = viewModel(),
) {
    val result by vm.state.collectAsStateWithLifecycle()
    val searching by vm.searching.collectAsStateWithLifecycle()
    val searched by vm.searched.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    var input by remember { mutableStateOf("") }

    MessageEffect(vm.messages, snackbar)

    AppScaffold(title = "搜索", onBack = onBack, snackbarHostState = snackbar) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            OutlinedTextField(
                value = input,
                onValueChange = { input = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                placeholder = { Text("搜索陪玩昵称、标签、游戏，或套餐名") },
                singleLine = true,
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                trailingIcon = {
                    IconButton(onClick = { vm.search(input) }, enabled = !searching) {
                        Text(if (searching) "…" else "搜索")
                    }
                },
                keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(imeAction = ImeAction.Search),
            )

            val data = result
            when {
                !searched -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text(
                        text = "输入关键词开始搜索",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }

                data == null || (data.companions.isEmpty() && data.packages.isEmpty()) -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            text = "没有找到与「${data?.keyword.orEmpty()}」相关的内容",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }

                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = 16.dp),
                ) {
                    if (data.packages.isNotEmpty()) {
                        item { SectionTitle(title = "点单套餐（${data.packages.size}）") }
                        items(data.packages, key = { "pkg_${it.id}" }) { pkg ->
                            PackageResultRow(pkg) { onOpenPackages() }
                        }
                    }
                    if (data.companions.isNotEmpty()) {
                        item { SectionTitle(title = "陪玩（${data.companions.size}）") }
                        items(data.companions, key = { "cmp_${it.userId}" }) { companion ->
                            CompanionCard(
                                item = companion,
                                onClick = { onOpenCompanion(companion.userId) },
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 3.dp),
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun PackageResultRow(pkg: PackageDto, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 3.dp)
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = pkg.title,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                if (pkg.description.isNotBlank()) {
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = pkg.description,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
            Spacer(Modifier.width(10.dp))
            MoneyText(text = pkg.priceText, fontSize = 16.sp)
        }
    }
}
