plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
}

android {
    namespace = "com.wolaitongniwan.myapplication"
    compileSdk {
        version = release(37)
    }

    defaultConfig {
        applicationId = "com.wolaitongniwan.myapplication"
        // 24 会在安卓 7 上回落到旧版 .webp 图标（模板自带的机器人，替换不了因为是二进制），
        // 所以取 26：自适应图标从 API 26 起支持，这样所有能装的设备都用同一个新图标。
        // 原来的 34 只支持安卓 14+，安卓 13 及以下会直接「解析包失败」。
        minSdk = 26
        targetSdk = 37
        // ⚠️ 每次发布新版本，这两个数字都必须往上加，否则「版本更新弹窗」会一直弹：
        //    App 把自己的 versionName 报给服务端，和后台设置的「App 最新版本号」比较，
        //    服务端更大就弹窗。如果 versionName 万年不变（比如一直是 1.0），
        //    那么后台填 1.1 之后，所有用户——包括已经装了新版的人——都会一直看到更新提示。
        //    versionCode 是给安卓系统比大小的整数，每次也必须 +1（用于覆盖安装）。
        versionCode = 2
        versionName = "1.1"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        // ★ 后端地址：换服务器只改这一行。
        //   Retrofit 要求 baseUrl 必须以 "/" 结尾；接口路径会拼在它后面。
        buildConfigField("String", "API_BASE_URL", "\"http://你的ip或者域名/\"")
    }

    buildTypes {
        release {
            optimization {
                enable = false
            }
            // 正式包若想指向别的后端，在这里再写一次 buildConfigField 覆盖即可
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
    buildFeatures {
        compose = true
        // 用 BuildConfig.API_BASE_URL 读后端地址，必须显式打开
        buildConfig = true
    }
    packaging {
        resources {
            // Compose / 协程会带进来一批重复的许可文件，不去掉会打包失败
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    // ---------- Compose ----------
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.material.icons.extended)

    // ---------- AndroidX ----------
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.navigation.compose)
    // 注意：登录令牌用 SharedPreferences（见 data/local/TokenStore.kt），
    // 不需要 datastore-preferences，故未引入。

    // ---------- 网络 ----------
    implementation(libs.retrofit)
    implementation(libs.retrofit.converter.gson)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation(libs.gson)

    // ---------- 协程 / 图片加载 / 二维码 ----------
    implementation(libs.kotlinx.coroutines.android)
    implementation(libs.coil.compose)
    implementation(libs.zxing.core)

    // ---------- 测试 ----------
    testImplementation(libs.junit)
    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.androidx.compose.ui.test.junit4)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(libs.androidx.junit)

    // ---------- 仅调试 ----------
    debugImplementation(libs.androidx.compose.ui.tooling)
    debugImplementation(libs.androidx.compose.ui.test.manifest)
}
