<?php
require dirname(__DIR__) . '/inc/bootstrap.php';

$location = get_str('location', 100);
$inRaw    = get_str('checkIn', 10);
$outRaw   = get_str('checkOut', 10);
$sortKey  = get_str('sort', 12);
$sorts = [
    'rating'     => ['Top rated',        'h.rating DESC, h.home_id ASC'],
    'price_low'  => ['Price: low to high', 'h.price ASC, h.home_id ASC'],
    'price_high' => ['Price: high to low', 'h.price DESC, h.home_id ASC'],
];
if (!isset($sorts[$sortKey])) {
    $sortKey = 'rating';
}

$errors = [];
$in = $out = null;
$nights = 0;
if ($inRaw !== '' || $outRaw !== '') {
    $in  = parse_date($inRaw);
    $out = parse_date($outRaw);
    if (!$in || !$out) {
        $errors[] = 'Please choose both a check-in and a check-out date.';
    } elseif ($in < today()) {
        $errors[] = 'Check-in cannot be in the past.';
    } elseif ($out <= $in) {
        $errors[] = 'Check-out must be after check-in.';
    } elseif (($nights = nights_between($in, $out)) > 30) {
        $errors[] = 'Stays can be up to 30 nights. Please shorten your dates.';
    }
    if ($errors) {
        $in = $out = null;
        $nights = 0;
    }
}

$sql    = "SELECT h.home_id, h.name, h.location, h.description, h.rating, h.price, h.image
             FROM holiday_homes h WHERE h.status = 'available'";
$types  = '';
$params = [];
if ($location !== '') {
    $like    = '%' . like_escape($location) . '%';
    $sql    .= ' AND (h.location LIKE ? OR h.name LIKE ?)';
    $types  .= 'ss';
    array_push($params, $like, $like);
}
if ($in && $out) {
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM reservations r
                               WHERE r.home_id = h.home_id AND r.check_in_date < ? AND r.check_out_date > ?)';
    $types .= 'ss';
    array_push($params, $out->format('Y-m-d'), $in->format('Y-m-d'));
}
$sql .= ' ORDER BY ' . $sorts[$sortKey][1];   // value comes from the fixed whitelist above
$homes = db_all($sql, $types, $params);

$carry = [];
if ($in && $out) {
    $carry = ['checkIn' => $in->format('Y-m-d'), 'checkOut' => $out->format('Y-m-d')];
}
$minDate = today()->format('Y-m-d');

$pageTitle = 'Find a stay';
$bodyClass = 'page-search';
include HHH_ROOT . '/inc/header.php';
?>

<section class="page-head">
    <div class="wrap">
        <p class="eyebrow">Stays</p>
        <h1><?= $location !== '' ? 'Homes matching “' . e($location) . '”' : 'Every home in the collection' ?></h1>
        <form class="filter-bar" action="" method="get" data-date-pair>
            <div class="field">
                <label for="f-location">Where</label>
                <input id="f-location" name="location" type="text" maxlength="100" value="<?= e($location) ?>" placeholder="Anywhere">
            </div>
            <div class="field">
                <label for="f-in">Check-in</label>
                <input id="f-in" name="checkIn" type="date" min="<?= e($minDate) ?>" value="<?= e($in ? $in->format('Y-m-d') : '') ?>" data-checkin>
            </div>
            <div class="field">
                <label for="f-out">Check-out</label>
                <input id="f-out" name="checkOut" type="date" min="<?= e($minDate) ?>" value="<?= e($out ? $out->format('Y-m-d') : '') ?>" data-checkout>
            </div>
            <div class="field">
                <label for="f-sort">Sort</label>
                <select id="f-sort" name="sort">
                    <?php foreach ($sorts as $key => $s) : ?>
                        <option value="<?= e($key) ?>"<?= $key === $sortKey ? ' selected' : '' ?>><?= e($s[0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn" type="submit">Update</button>
        </form>
    </div>
</section>

<section class="section-tight">
    <div class="wrap">
        <?php foreach ($errors as $err) : ?>
            <div class="alert alert-error" role="alert"><?= e($err) ?></div>
        <?php endforeach; ?>

        <p class="results-count">
            <?= count($homes) ?> <?= count($homes) === 1 ? 'home' : 'homes' ?>
            <?php if ($in && $out) : ?>
                free for <?= e($in->format('j M')) ?> – <?= e($out->format('j M Y')) ?> (<?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?>)
            <?php endif; ?>
        </p>

        <?php if (!$homes) : ?>
            <div class="empty-state">
                <h2>Nothing matches just yet</h2>
                <p>Try a different destination, or shift your dates by a day or two.</p>
                <a class="btn btn-ghost" href="<?= e(url('users/search.php')) ?>">Clear filters</a>
            </div>
        <?php else : ?>
            <div class="stay-list">
                <?php foreach ($homes as $h) :
                    $link = url('users/reserve.php?' . http_build_query(['home_id' => (int) $h['home_id']] + $carry));
                    ?>
                    <article class="stay-row">
                        <a class="stay-media" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= e(home_image_url($h['image'])) ?>" alt="" loading="lazy" width="940" height="625">
                        </a>
                        <div class="stay-body">
                            <p class="stay-loc"><?= icon('pin', 15) ?> <?= e($h['location']) ?></p>
                            <h2><a href="<?= e($link) ?>"><?= e($h['name']) ?></a></h2>
                            <p class="stay-text"><?= e(excerpt($h['description'], 200)) ?></p>
                        </div>
                        <div class="stay-side">
                            <?= rating_html($h['rating']) ?>
                            <p class="price"><strong><?= e(money($h['price'])) ?></strong> <small>/ night</small></p>
                            <?php if ($nights) : ?>
                                <p class="price-total"><?= e(money((float) $h['price'] * $nights)) ?> for <?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?></p>
                            <?php endif; ?>
                            <a class="btn btn-sm" href="<?= e($link) ?>"><?= $nights ? 'Book these dates' : 'View home' ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
