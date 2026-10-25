<?php
require dirname(__DIR__) . '/inc/bootstrap.php';

$homeId = is_post() ? post_int('home_id') : get_int('home_id');
$home = $homeId > 0
    ? db_one("SELECT home_id, name, location, description, rating, price, image
                FROM holiday_homes WHERE home_id = ? AND status = 'available'", 'i', [$homeId])
    : null;
if (!$home) {
    abort_page(404, 'Home not found', 'That home is not available any more. Browse the full collection to find another.');
}

$inRaw  = is_post() ? post_str('checkIn', 10)  : get_str('checkIn', 10);
$outRaw = is_post() ? post_str('checkOut', 10) : get_str('checkOut', 10);
$error  = '';

if (is_post()) {
    csrf_verify();
    require_login();
    $uid = (int) current_user_id();

    $in  = parse_date($inRaw);
    $out = parse_date($outRaw);
    $nights = ($in && $out) ? nights_between($in, $out) : 0;

    if (!$in || !$out) {
        $error = 'Please choose both a check-in and a check-out date.';
    } elseif ($in < today()) {
        $error = 'Check-in cannot be in the past.';
    } elseif ($out <= $in) {
        $error = 'Check-out must be after check-in.';
    } elseif ($nights > 30) {
        $error = 'Stays can be up to 30 nights.';
    } elseif ($in > today()->modify('+2 years')) {
        $error = 'Bookings open up to two years ahead.';
    } elseif (($wait = throttle_wait('book', 'u' . $uid, 10, 3600)) > 0) {
        $error = 'You have made several bookings in a short time. Please try again in ' . wait_text($wait) . '.';
    } else {
        try {
            $reservationId = db_transaction(function () use ($homeId, $uid, $in, $out, $nights) {
                // Lock the home's row so two people cannot book the same nights at the same moment.
                $h = db_one("SELECT price FROM holiday_homes WHERE home_id = ? AND status = 'available' FOR UPDATE", 'i', [$homeId]);
                if (!$h) {
                    throw new DomainException('This home has just been taken off the market.');
                }
                $clash = (int) db_value(
                    'SELECT COUNT(*) FROM reservations WHERE home_id = ? AND check_in_date < ? AND check_out_date > ?',
                    'iss', [$homeId, $out->format('Y-m-d'), $in->format('Y-m-d')]
                );
                if ($clash > 0) {
                    throw new DomainException('Sorry, someone has just booked part of those dates. Please pick different ones.');
                }
                $total = sprintf('%.2f', $nights * (float) $h['price']);   // price comes from the database, never from the browser
                db_run('INSERT INTO reservations (user_id, home_id, check_in_date, check_out_date, total_price) VALUES (?, ?, ?, ?, ?)',
                    'iisss', [$uid, $homeId, $in->format('Y-m-d'), $out->format('Y-m-d'), $total]);
                return db_last_id();
            });
            throttle_hit('book', 'u' . $uid);
            audit('user', $uid, 'reservation_created', "reservation #$reservationId, home #$homeId, {$in->format('Y-m-d')} to {$out->format('Y-m-d')}");
            flash('success', 'Your stay at ' . $home['name'] . ' is booked. We look forward to hosting you.');
            redirect('users/reservations.php');
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

// Nights that are already taken (dates only, never who booked them).
$booked = db_all(
    'SELECT check_in_date, check_out_date FROM reservations
      WHERE home_id = ? AND check_out_date > CURDATE() ORDER BY check_in_date LIMIT 12',
    'i', [$homeId]
);

$prefIn  = parse_date($inRaw)  ? $inRaw  : '';
$prefOut = parse_date($outRaw) ? $outRaw : '';
$minDate = today()->format('Y-m-d');
$paras   = preg_split('/\R{2,}|\R/', (string) $home['description']) ?: [];

$pageTitle = $home['name'];
$bodyClass = 'page-reserve';
include HHH_ROOT . '/inc/header.php';
?>

<section class="wrap crumbs">
    <a href="<?= e(url('users/search.php')) ?>">&larr; All stays</a>
</section>

<section class="wrap detail">
    <div class="detail-main">
        <figure class="detail-photo">
            <img src="<?= e(home_image_url($home['image'])) ?>" alt="<?= e($home['name']) ?>, <?= e($home['location']) ?>" width="940" height="625">
        </figure>
        <p class="eyebrow"><?= icon('pin', 15) ?> <?= e($home['location']) ?></p>
        <h1><?= e($home['name']) ?></h1>
        <p class="detail-meta"><?= rating_html($home['rating']) ?> <span class="dot"></span> <?= e(money($home['price'])) ?> per night</p>
        <div class="prose">
            <?php foreach ($paras as $p) : if (trim($p) === '') { continue; } ?>
                <p><?= e($p) ?></p>
            <?php endforeach; ?>
        </div>

        <?php if ($booked) : ?>
            <div class="booked-box">
                <h2>Already booked</h2>
                <ul>
                    <?php foreach ($booked as $b) : ?>
                        <li><?= e(pretty_date($b['check_in_date'])) ?> <span aria-hidden="true">→</span><span class="sr-only">to</span> <?= e(pretty_date($b['check_out_date'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <aside class="book-card" aria-label="Reserve this home">
        <p class="book-price"><strong><?= e(money($home['price'])) ?></strong> <small>per night</small></p>

        <?php if ($error) : ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="" data-date-pair data-price="<?= e($home['price']) ?>" data-currency="<?= e(config('currency', '₹')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="home_id" value="<?= (int) $home['home_id'] ?>">
            <div class="field">
                <label for="r-in">Check-in</label>
                <input id="r-in" name="checkIn" type="date" min="<?= e($minDate) ?>" value="<?= e($prefIn) ?>" required data-checkin>
            </div>
            <div class="field">
                <label for="r-out">Check-out</label>
                <input id="r-out" name="checkOut" type="date" min="<?= e($minDate) ?>" value="<?= e($prefOut) ?>" required data-checkout>
            </div>

            <dl class="book-sum" data-summary hidden>
                <div><dt data-sum-label></dt><dd data-sum-sub></dd></div>
                <div class="book-total"><dt>Total</dt><dd data-sum-total></dd></div>
            </dl>

            <?php if (is_logged_in()) : ?>
                <button class="btn btn-block" type="submit">Reserve now</button>
                <p class="hint">You will not be charged online. Cancel free until the day you arrive.</p>
            <?php else : ?>
                <?php $next = 'users/reserve.php?' . http_build_query(array_filter(['home_id' => $homeId, 'checkIn' => $prefIn, 'checkOut' => $prefOut])); ?>
                <a class="btn btn-block" data-login-link data-login-url="<?= e(url('users/login.php')) ?>"
                   data-next-base="<?= e('users/reserve.php?home_id=' . $homeId) ?>"
                   href="<?= e(url('users/login.php?next=' . rawurlencode($next))) ?>">Log in to reserve</a>
                <p class="hint">New here? <a href="<?= e(url('users/signup.php')) ?>">Create an account</a>. It takes a minute.</p>
            <?php endif; ?>
        </form>
    </aside>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
