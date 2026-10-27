# 已废弃

工单系统已经改成**完全独立的站点**（`kf/` 目录），不再与陪玩站做任何对接，
因此这份对接说明不再需要，可以安全删除。

陪玩站这边唯一留下的痕迹：`app/Controllers/Api/KfController.php` 已改成一行说明，
`app/Api/Router.php` 里的 kf 路由已移除，`ticket_replies` 表没有改动。
