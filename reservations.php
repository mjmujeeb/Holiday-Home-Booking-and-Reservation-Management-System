<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_login();

$uid = (int) current_user_id();
$rows = db_all(
    'SELECT r.reservation_id, r.check_in_date, r.check_out_date, r.total_price, r.created_at,
            h.home_id, h.name, h.location, h.image
       FROM reservations r JOIN holiday_homes h ON h.home_id = r.home_id
      WHERE r.user_id = ?
      ORDER BY r.check_in_date DESC',
    'i', [$uid]
);
$todayStr = today()->format('Y-m-d');

$pageTitle = 'My reservations';
$bodyClass = 'page-reservations';
include HHH_ROOT . '/inc/header.php';
?>

<section class="page-head">
    <div class="wrap">
        <p class="eyebrow">Your trips</p>
        <h1>My reservations</h1>
    </div>
</section>

<section class="section-tight">
    <div class="wrap narrow">
        <?php render_flashes(); ?>

        <?php if (!$rows) : ?>
            <div class="empty-state">
                <h2>No trips yet</h2>
                <p>When you book a home it will show up here.</p>
                <a class="btn" href="<?= e(url('users/search.php')) ?>">Find a stay</a>
            </div>
        <?php else : ?>
            <ul class="res-list">
                <?php foreach ($rows as $r) :
                    $in  = $r['check_in_date'];
                    $out = $r['check_out_date'];
                    if ($out <= $todayStr)      { $state = ['Completed', 'chip-past']; }
                    elseif ($in <= $todayStr)   { $state = ['Staying now', 'chip-now']; }
                    else                        { $state = ['Upcoming', 'chip-soon']; }
                    $n = nights_between(parse_date($in), parse_date($out));
                    ?>
                    <li class="res">
                        <a class="res-img" href="<?= e(url('users/reserve.php?home_id=' . (int) $r['home_id'])) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= e(home_image_url($r['image'])) ?>" alt="" loading="lazy" width="940" height="625">
                        </a>
                        <div class="res-body">
                            <span class="chip <?= e($state[1]) ?>"><?= e($state[0]) ?></span>
                            <h2><?= e($r['name']) ?></h2>
                            <p class="stay-loc"><?= icon('pin', 15) ?> <?= e($r['location']) ?></p>
                            <p class="res-dates"><?= icon('calendar', 16) ?>
                                <?= e(pretty_date($in)) ?> → <?= e(pretty_date($out)) ?>
                                <span class="muted">(<?= $n ?> <?= $n === 1 ? 'night' : 'nights' ?>)</span></p>
                            <p class="res-ref">Reference HHH-<?= str_pad((string) (int) $r['reservation_id'], 5, '0', STR_PAD_LEFT) ?></p>
                        </div>
                        <div class="res-side">
                            <p class="res-total"><span class="muted">Total</span><strong><?= e(money($r['total_price'], true)) ?></strong></p>
                            <?php if ($in > $todayStr) : ?>
                                <form method="post" action="<?= e(url('users/cancel_booking.php')) ?>" data-confirm="Cancel your stay at <?= e($r['name']) ?>? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                                    <button class="btn btn-ghost btn-sm btn-danger-text" type="submit">Cancel booking</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
