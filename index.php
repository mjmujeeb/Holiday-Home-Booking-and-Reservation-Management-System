<?php
require __DIR__ . '/inc/bootstrap.php';

$featured = db_all(
    "SELECT home_id, name, location, description, rating, price, image
       FROM holiday_homes WHERE status = 'available'
      ORDER BY rating DESC, home_id ASC LIMIT 5"
);
$stats   = db_one("SELECT COUNT(*) AS homes, COUNT(DISTINCT location) AS places FROM holiday_homes WHERE status = 'available'");
$minDate = today()->format('Y-m-d');

$pageTitle       = config('app_name');
$bodyClass       = 'page-home';
$metaDescription = 'A small collection of holiday homes with real photographs and plain prices. Search by place and dates, book in a minute.';
include HHH_ROOT . '/inc/header.php';
?>

<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <p class="eyebrow">Holiday homes, chosen one at a time</p>
            <h1>Stay somewhere that feels like a <em>place</em>.</h1>
            <p class="lede">
                A hillside villa. A fourteenth-century stone house in Lazio. A ranch among the coffee farms of Costa Rica.
                Each home here has honest photographs, a description written by a person, and a price you can read at a glance.
            </p>
            <dl class="hero-facts">
                <div><dt>Homes</dt><dd><?= (int) $stats['homes'] ?></dd></div>
                <div><dt>Destinations</dt><dd><?= (int) $stats['places'] ?></dd></div>
                <div><dt>Cancellation</dt><dd>Free until check-in</dd></div>
            </dl>
        </div>
        <figure class="hero-media">
            <img src="<?= e(url('assets/img/hero.jpg')) ?>" width="800" height="450"
                 alt="An infinity pool and terrace lit by a sunset over the water">
        </figure>
    </div>
</section>

<div class="wrap">
    <form class="search-strip" action="<?= e(url('users/search.php')) ?>" method="get" data-date-pair>
        <div class="field">
            <label for="s-location">Where</label>
            <input id="s-location" name="location" type="text" maxlength="100" placeholder="Florida, Italy, Costa Rica…" autocomplete="off">
        </div>
        <div class="field">
            <label for="s-in">Check-in</label>
            <input id="s-in" name="checkIn" type="date" min="<?= e($minDate) ?>" required data-checkin>
        </div>
        <div class="field">
            <label for="s-out">Check-out</label>
            <input id="s-out" name="checkOut" type="date" min="<?= e($minDate) ?>" required data-checkout>
        </div>
        <button class="btn" type="submit">Find a home</button>
    </form>
</div>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <p class="eyebrow">Highest rated</p>
                <h2>Where guests were happiest</h2>
            </div>
            <a class="link-arrow" href="<?= e(url('users/search.php')) ?>">See every stay <?= icon('arrow', 16) ?></a>
        </div>

        <?php if (!$featured) : ?>
            <p class="empty-state">No homes are listed right now. Please check back soon.</p>
        <?php else : ?>
            <div class="stay-grid">
                <?php foreach ($featured as $i => $h) : ?>
                    <article class="stay<?= $i === 0 ? ' stay-wide' : '' ?>">
                        <a class="stay-media" href="<?= e(url('users/reserve.php?home_id=' . (int) $h['home_id'])) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= e(home_image_url($h['image'])) ?>" alt="" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>" width="940" height="625">
                        </a>
                        <div class="stay-body">
                            <p class="stay-loc"><?= icon('pin', 15) ?> <?= e($h['location']) ?></p>
                            <h3><a href="<?= e(url('users/reserve.php?home_id=' . (int) $h['home_id'])) ?>"><?= e($h['name']) ?></a></h3>
                            <p class="stay-text"><?= e(excerpt($h['description'], $i === 0 ? 190 : 110)) ?></p>
                            <div class="stay-foot">
                                <span class="price"><strong><?= e(money($h['price'])) ?></strong> <small>/ night</small></span>
                                <?= rating_html($h['rating']) ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-paper">
    <div class="wrap">
        <p class="eyebrow">How it works</p>
        <h2>Three steps, no surprises</h2>
        <ol class="steps">
            <li>
                <span class="step-no">01</span>
                <h3>Say where and when</h3>
                <p>Enter a destination and your dates. We only show homes that are actually free on those nights.</p>
            </li>
            <li>
                <span class="step-no">02</span>
                <h3>Read, then choose</h3>
                <p>Look at the photographs, read the description and see the full total before you commit to anything.</p>
            </li>
            <li>
                <span class="step-no">03</span>
                <h3>Reserve and relax</h3>
                <p>Confirm in one click. Your booking lives under My reservations, and you can cancel any time before you arrive.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section trust">
    <div class="wrap trust-grid">
        <div>
            <p class="eyebrow eyebrow-light">Privacy &amp; security</p>
            <h2>Your details stay yours</h2>
            <p class="lede">We keep very little about you, and what we keep is protected the way it should be.</p>
        </div>
        <dl class="trust-list">
            <div>
                <dt>Passwords</dt>
                <dd>Stored only as salted one-way hashes. Nobody at HappyHolidayHome, administrators included, can read them.</dd>
            </div>
            <div>
                <dt>Email check</dt>
                <dd>A six-digit code proves the address is yours. It expires after ten minutes and stops working after five wrong tries.</dd>
            </div>
            <div>
                <dt>Sign-in</dt>
                <dd>Session cookies are locked to this site, hidden from scripts, and end after thirty idle minutes.</dd>
            </div>
            <div>
                <dt>Bookings</dt>
                <dd>Totals are worked out on our server, and availability is checked once more at the moment you book.</dd>
            </div>
        </dl>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
