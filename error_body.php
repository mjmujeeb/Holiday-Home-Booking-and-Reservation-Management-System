<section class="wrap error-block">
    <p class="error-code"><?= (int) $errorCode ?></p>
    <h1><?= e($errorTitle) ?></h1>
    <p class="lede"><?= e($errorMessage) ?></p>
    <p><a class="btn" href="<?= e(url('index.php')) ?>">Back to the home page</a></p>
</section>
