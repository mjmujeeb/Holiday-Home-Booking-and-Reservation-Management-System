<?php
require __DIR__ . '/inc/admin_init.php';

$per  = 40;
$page = max(1, get_int('page'));
$total = (int) db_value('SELECT COUNT(*) FROM audit_log');
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$rows  = db_all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ? OFFSET ?', 'ii', [$per, ($page - 1) * $per]);

$pageTitle = 'Audit log';
$activeNav = 'audit';
include __DIR__ . '/inc/admin_header.php';
?>
<div class="toolbar"><p class="muted">A record of sign-ins, bookings and every change made in this panel. <?= $total ?> entries.</p></div>
<section class="card">
    <div class="table-wrap"><table class="table table-tight">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : ?>
            <tr><td class="nowrap"><?= e(date('j M Y, H:i:s', strtotime($r['created_at']))) ?></td>
                <td><span class="tag tag-<?= e($r['actor_type']) ?>"><?= e($r['actor_type']) ?><?= $r['actor_id'] ? ' #' . (int) $r['actor_id'] : '' ?></span></td>
                <td><?= e(str_replace('_', ' ', $r['action'])) ?></td>
                <td class="muted"><?= e($r['detail']) ?></td><td class="mono"><?= e($r['ip']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows) : ?><tr><td colspan="5" class="muted">Nothing logged yet.</td></tr><?php endif; ?>
        </tbody></table></div>
</section>
<?php if ($pages > 1) : ?>
<nav class="pager" aria-label="Pages">
    <?php if ($page > 1) : ?><a class="btn btn-sm" href="?page=<?= $page - 1 ?>">&larr; Newer</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages) : ?><a class="btn btn-sm" href="?page=<?= $page + 1 ?>">Older &rarr;</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
