<?php
declare(strict_types=1);

$authLayout = !empty($authLayout);
$landingLayout = !empty($landingLayout);
$appShell = !empty(current_user()) && !$authLayout && !$landingLayout;
?>
<?php if ($authLayout): ?>
</main>
<?php elseif ($landingLayout): ?>
</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <p><strong><?= e(APP_NAME) ?></strong> — <?= e(APP_FULL_NAME) ?></p>
        <p class="muted">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
    </div>
</footer>
<?php elseif ($appShell): ?>
        </div><!-- .container -->
    </main>
    <footer class="site-footer site-footer-app">
        <div class="container footer-inner">
            <p class="muted">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
        </div>
    </footer>
</div><!-- .app-frame -->
<?php else: ?>
        </div><!-- .container -->
    </main>
<footer class="site-footer">
    <div class="container footer-inner">
        <p><strong><?= e(APP_NAME) ?></strong> — <?= e(APP_FULL_NAME) ?></p>
        <p class="muted">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
    </div>
</footer>
<?php endif; ?>
<script src="<?= e(APP_URL) ?>/assets/js/app.js"></script>
</body>
</html>
