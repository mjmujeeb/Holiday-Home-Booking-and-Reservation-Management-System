</main>

<footer class="site-footer">
    <div class="wrap footer-grid">
        <div class="footer-brand">
            <a class="brand brand-light" href="<?= e(url('index.php')) ?>">
                <?= brand_mark(30) ?>
                <span class="brand-word">Happy<em>Holiday</em>Home</span>
            </a>
            <p>A small, carefully kept collection of holiday homes. Real photographs, plain prices, and your data handled with care.</p>
        </div>
        <nav aria-label="Footer">
            <h2 class="eyebrow">Explore</h2>
            <ul>
                <li><a href="<?= e(url('users/search.php')) ?>">All stays</a></li>
                <li><a href="<?= e(url('users/about.php')) ?>">About</a></li>
                <li><a href="<?= e(url('users/contact.php')) ?>">Contact</a></li>
                <li><a href="<?= e(url('users/reservations.php')) ?>">My reservations</a></li>
            </ul>
        </nav>
        <div>
            <h2 class="eyebrow">Get in touch</h2>
            <ul>
                <li><a href="mailto:<?= e(config('contact.email')) ?>"><?= e(config('contact.email')) ?></a></li>
                <li><?= e(config('contact.phone')) ?></li>
            </ul>
        </div>
    </div>
    <div class="wrap footer-base">
        <span>&copy; <?= date('Y') ?> <?= e(config('app_name')) ?></span>
        <?php if (config('author.name')) : ?>
            <span>
                Designed &amp; built by <?= e(config('author.name')) ?>
                <?php if (config('author.github')) : ?>· <a href="<?= e(config('author.github')) ?>" target="_blank" rel="noopener noreferrer">GitHub</a><?php endif; ?>
                <?php if (config('author.linkedin')) : ?>· <a href="<?= e(config('author.linkedin')) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a><?php endif; ?>
            </span>
        <?php endif; ?>
    </div>
</footer>
</body>
</html>
