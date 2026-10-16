<?php
/**
 * 分页组件
 * @var int $page
 * @var int $totalPage
 */
if (!isset($totalPage)) {
    $totalPage = 1;
}
if (!isset($page)) {
    $page = 1;
}
if ($totalPage > 1):
?>
<nav class="pager">
  <?php if ($page > 1): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(query_url(['page' => $page - 1])) ?>">上一页</a>
  <?php endif; ?>
  <span class="pager-info">第 <?= (int) $page ?> / <?= (int) $totalPage ?> 页</span>
  <?php if ($page < $totalPage): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(query_url(['page' => $page + 1])) ?>">下一页</a>
  <?php endif; ?>
</nav>
<?php endif; ?>
