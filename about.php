<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$pageTitle       = 'About';
$bodyClass       = 'page-about';
$metaDescription = 'Why HappyHolidayHome keeps its collection small, and how it looks after your information.';
include HHH_ROOT . '/inc/header.php';
?>

<section class="page-head">
    <div class="wrap">
        <p class="eyebrow">About</p>
        <h1>A small collection, kept honest.</h1>
    </div>
</section>

<section class="wrap about-grid">
    <div class="prose prose-lg">
        <p>
            Finding a holiday home should feel like flipping through a friend’s recommendations, not wading through
            a thousand near-identical listings. So the collection here is deliberately small: a villa, an apartment,
            a stone house, a ranch. Each one is described the way you would describe it to someone you like.
        </p>
        <p>
            Nothing is hidden at the bottom of the page. The nightly price is on the card, the full total appears
            before you book, and a stay can be cancelled at any time until the day you arrive.
        </p>
        <p>
            Travellers are all different, so the aim is not to have the most homes. It is to have homes worth
            writing about, and to make the booking itself so simple you barely notice it.
        </p>
    </div>
    <figure class="about-photo">
        <img src="<?= e(url('assets/img/about.jpg')) ?>" width="1329" height="886" loading="lazy"
             alt="A resort at dusk with a lit pool and a small arched bridge">
    </figure>
</section>

<section class="section section-paper">
    <div class="wrap">
        <p class="eyebrow">Your information</p>
        <h2>How we look after it</h2>
        <div class="care-grid">
            <div>
                <h3>We keep little</h3>
                <p>Your name, email, mobile number and your bookings. That is all we need to run a reservation, so that is all we hold.</p>
            </div>
            <div>
                <h3>Passwords are unreadable</h3>
                <p>They are stored as salted one-way hashes. We cannot see them, and we will never ask you for one by email or phone.</p>
            </div>
            <div>
                <h3>Codes are short-lived</h3>
                <p>Email verification codes expire after ten minutes, and repeated wrong guesses lock the attempt.</p>
            </div>
            <div>
                <h3>Staff access is guarded</h3>
                <p>The admin area is separate from the public site, supports app-based two-step sign-in, and every change is written to an audit log.</p>
            </div>
        </div>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
