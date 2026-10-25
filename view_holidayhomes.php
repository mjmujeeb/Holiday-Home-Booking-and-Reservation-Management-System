<?php
require __DIR__ . '/inc/admin_init.php';

$homes = db_all('SELECT h.*, (SELECT COUNT(*) FROM reservations r WHERE r.home_id = h.home_id) AS res_count
                   FROM holiday_homes h ORDER BY h.home_id');

/** Shared fields for the add and edit dialogs. */
function home_fields(?array $h, string $p): void
{ ?>
    <div class="field"><label for="<?= $p ?>name">Name</label>
        <input id="<?= $p ?>name" name="name" type="text" maxlength="120" value="<?= e($h['name'] ?? '') ?>" required></div>
    <div class="field"><label for="<?= $p ?>location">Location</label>
        <input id="<?= $p ?>location" name="location" type="text" maxlength="120" value="<?= e($h['location'] ?? '') ?>" required></div>
    <div class="field"><label for="<?= $p ?>description">Description</label>
        <textarea id="<?= $p ?>description" name="description" maxlength="3000" rows="5" required><?= e($h['description'] ?? '') ?></textarea></div>
    <div class="grid-3">
        <div class="field"><label for="<?= $p ?>price">Price / night</label>
            <input id="<?= $p ?>price" name="price" type="number" min="1" max="1000000" step="0.01" value="<?= e($h['price'] ?? '') ?>" required></div>
        <div class="field"><label for="<?= $p ?>rating">Rating (0–5)</label>
            <input id="<?= $p ?>rating" name="rating" type="number" min="0" max="5" step="0.1" value="<?= e($h['rating'] ?? '4.0') ?>" required></div>
        <div class="field"><label for="<?= $p ?>status">Status</label>
            <select id="<?= $p ?>status" name="status">
                <option value="available"<?= ($h['status'] ?? 'available') === 'available' ? ' selected' : '' ?>>Listed</option>
                <option value="not_available"<?= ($h['status'] ?? '') === 'not_available' ? ' selected' : '' ?>>Unlisted</option>
            </select></div>
    </div>
    <div class="field"><label for="<?= $p ?>image">Photo <?= $h ? '(leave empty to keep the current one)' : '' ?></label>
        <input id="<?= $p ?>image" name="image" type="file" accept="image/jpeg,image/png,image/webp" <?= $h ? '' : 'required' ?>>
        <p class="hint">JPEG, PNG or WebP, at least 600×400, up to 5 MB.</p></div>
<?php }

$pageTitle = 'Holiday homes';
$activeNav = 'homes';
include __DIR__ . '/inc/admin_header.php';
?>
<div class="toolbar">
    <p class="muted"><?= count($homes) ?> homes in the collection.</p>
    <button class="btn btn-primary" type="button" data-open="dlg-add">Add a home</button>
</div>

<section class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Home</th><th>Location</th><th class="num">Price</th><th class="num">Rating</th><th>Status</th><th class="num">Bookings</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($homes as $h) : ?>
            <tr>
                <td><div class="thumb-cell"><img src="<?= e(home_image_url($h['image'])) ?>" alt="" width="64" height="44" loading="lazy"><strong><?= e($h['name']) ?></strong></div></td>
                <td><?= e($h['location']) ?></td>
                <td class="num"><?= e(money($h['price'])) ?></td>
                <td class="num"><?= e(number_format((float) $h['rating'], 1)) ?></td>
                <td><span class="badge <?= $h['status'] === 'available' ? 'badge-ok' : 'badge-off' ?>"><?= $h['status'] === 'available' ? 'Listed' : 'Unlisted' ?></span></td>
                <td class="num"><?= (int) $h['res_count'] ?></td>
                <td class="row-actions">
                    <button class="btn btn-sm" type="button" data-open="dlg-edit-<?= (int) $h['home_id'] ?>">Edit</button>
                    <form method="post" action="<?= e(url('admin/delete_holidayhome.php')) ?>" data-confirm="Delete “<?= e($h['name']) ?>” permanently?">
                        <?= csrf_field() ?><input type="hidden" name="home_id" value="<?= (int) $h['home_id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
</section>

<dialog id="dlg-add" class="dialog">
    <form method="post" action="<?= e(url('admin/insert_holidayhome.php')) ?>" enctype="multipart/form-data">
        <header><h2>Add a home</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('close', 18) ?></button></header>
        <div class="dialog-body"><?= csrf_field() ?><?php home_fields(null, 'add-'); ?></div>
        <footer><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Add home</button></footer>
    </form>
</dialog>

<?php foreach ($homes as $h) : ?>
<dialog id="dlg-edit-<?= (int) $h['home_id'] ?>" class="dialog">
    <form method="post" action="<?= e(url('admin/update_holidayhome.php')) ?>" enctype="multipart/form-data">
        <header><h2>Edit “<?= e($h['name']) ?>”</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('close', 18) ?></button></header>
        <div class="dialog-body"><?= csrf_field() ?><input type="hidden" name="home_id" value="<?= (int) $h['home_id'] ?>">
            <?php home_fields($h, 'e' . (int) $h['home_id'] . '-'); ?></div>
        <footer><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></footer>
    </form>
</dialog>
<?php endforeach; ?>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
