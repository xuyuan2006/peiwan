package com.wolaitongniwan.myapplication.ui.common

import android.content.Context
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody

/**
 * 把相册选中的图片 URI 转成 multipart 部件。
 *
 * 是 suspend 且切到 IO 线程的原因：要把整张图读进内存，
 * 直接在 Compose 回调（主线程）里做，遇到大图会卡顿甚至 OOM。
 *
 * 说明：后端 `Upload::image` 会自己用 `getimagesize` 校验真实类型，
 * 这里的 mime/扩展名只是为了日志可读，不承担安全职责。
 */
suspend fun uriToImagePart(
    context: Context,
    uri: Uri,
    fieldName: String = "file",
): MultipartBody.Part? = withContext(Dispatchers.IO) {
    try {
        val resolver = context.contentResolver
        val bytes = resolver.openInputStream(uri)?.use { it.readBytes() }
        if (bytes == null || bytes.isEmpty()) return@withContext null

        val mime = resolver.getType(uri) ?: "image/jpeg"
        val ext = when {
            mime.contains("png") -> "png"
            mime.contains("gif") -> "gif"
            mime.contains("webp") -> "webp"
            else -> "jpg"
        }
        val body = bytes.toRequestBody(mime.toMediaTypeOrNull())
        MultipartBody.Part.createFormData(fieldName, "upload_${System.currentTimeMillis()}.$ext", body)
    } catch (_: Throwable) {
        null
    }
}
