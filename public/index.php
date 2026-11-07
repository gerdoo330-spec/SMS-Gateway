<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Services/DashboardService.php';

$databaseReady = false;
$dbError = null;
$stats = ['projects' => 0, 'messages' => 0, 'pending' => 0, 'failed' => 0];

try {
    $stats = DashboardService::getStats();
    $databaseReady = true;
} catch (Throwable $exception) {
    error_log('[SMS Gateway dashboard] ' . $exception->getMessage());
    $dbError = 'اتصال به دیتابیس برقرار نشد. تنظیمات config/database.php و اجرای MySQL را بررسی کن.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | داشبورد</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">
    <aside class="sidebar">
        <div class="side-brand"><span class="brand-mark">SMS</span><div><strong>SMS Gateway</strong><small>سامانه مدیریت پیامک</small></div></div>
        <nav class="side-nav">
            <a class="active" href="index.php">نمای کلی</a>
            <a href="queue.php">مدیریت صف پیامک</a>
            <a href="projects.php">پروژه‌ها و API</a>
            <a href="api-keys.php">مدیریت کلیدهای API</a>
            <a href="#" aria-disabled="true">ارسال دستی پیامک <em>به‌زودی</em></a>
            <a href="#" aria-disabled="true">تنظیمات مودم <em>به‌زودی</em></a>
        </nav>
        <div class="sidebar-footer">نسخه <?= e(APP_VERSION) ?></div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div><p class="eyebrow">مدیریت سامانه</p><h1>داشبورد</h1></div>
            <div class="admin-chip"><span class="online-dot"></span> محیط توسعه محلی</div>
        </header>

        <?php if ($databaseReady): ?>
            <div class="notice success"><span class="notice-dot"></span> اتصال به پایگاه داده برقرار است.</div>
        <?php else: ?>
            <div class="notice warning"><?= e($dbError ?? 'خطای نامشخص در اتصال دیتابیس') ?></div>
        <?php endif; ?>

        <section class="welcome-panel">
            <div>
                <p class="eyebrow light">پنل پیامکی مستقل</p>
                <h2>به SMS Gateway خوش آمدی</h2>
                <p>مدیریت متمرکز ارسال پیامک برای تمام پروژه‌ها از یک سرویس مستقل.</p>
            </div>
            <div class="welcome-icon">✉</div>
        </section>

        <section class="section-heading"><div><h2>نمای کلی سامانه</h2><p>آمار ثبت‌شده در پایگاه داده</p></div></section>
        <section class="stats-grid">
            <article class="stat-card"><span class="stat-label">پروژه‌های ثبت‌شده</span><strong><?= number_format($stats['projects']) ?></strong><span class="stat-foot">پروژه متصل به Gateway</span></article>
            <article class="stat-card"><span class="stat-label">کل پیامک‌ها</span><strong><?= number_format($stats['messages']) ?></strong><span class="stat-foot">ارسالی و دریافتی</span></article>
            <article class="stat-card"><span class="stat-label">در انتظار پردازش</span><strong><?= number_format($stats['pending']) ?></strong><span class="stat-foot">پیامک‌های صف ارسال</span></article>
            <article class="stat-card"><span class="stat-label">ارسال ناموفق</span><strong><?= number_format($stats['failed']) ?></strong><span class="stat-foot">نیازمند بررسی</span></article>
        </section>

        <section class="bottom-grid">
            <article class="content-card">
                <h3>وضعیت زیرساخت</h3>
                <div class="system-row"><span>وب‌سرور Apache</span><b class="state-ok">در حال اجرا</b></div>
                <div class="system-row"><span>پایگاه داده MySQL</span><b class="<?= $databaseReady ? 'state-ok' : 'state-error' ?>"><?= $databaseReady ? 'متصل' : 'قطع' ?></b></div>
                <div class="system-row"><span>مودم TFi60-S1</span><b class="state-pending">هنوز پیکربندی نشده</b></div>
                <div class="system-row"><span>Local Agent</span><b class="state-pending">در مرحله توسعه</b></div>
            </article>
            <article class="content-card next-card">
                <h3>مرحله بعدی توسعه</h3>
                <p>صف پیامک آماده مشاهده است. گام بعدی، پیاده‌سازی پردازشگر صف و اتصال امن آن به مودم خواهد بود.</p>
                <a class="tag" href="queue.php">مشاهده صف پیامک</a>
            </article>
        </section>
        <footer class="page-footer">SMS Gateway · مستقل و قابل اتصال به چندین پروژه</footer>
    </main>
</body>
</html>
