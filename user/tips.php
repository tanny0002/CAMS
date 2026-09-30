<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$tips = db()->query('SELECT * FROM security_tips WHERE is_active = 1 ORDER BY id DESC')->fetchAll();

$pageTitle = 'Security tips';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Security tips</h1>
        <p class="muted">Short reminders you can apply every day.</p>
    </div>
</div>

<div class="grid grid-2">
    <?php foreach ($tips as $tip): ?>
        <article class="card">
            <?php if ($tip['category']): ?><span class="badge"><?= e($tip['category']) ?></span><?php endif; ?>
            <h3 style="margin-top:0.45rem"><?= e($tip['title']) ?></h3>
            <p><?= e($tip['body']) ?></p>
        </article>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
