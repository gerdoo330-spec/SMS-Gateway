<?php
session_start();
require_once __DIR__ . '/../app/Services/Database.php';
require_once __DIR__ . '/../app/Services/JalaliDate.php';

$pdo = Database::connection();

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$allowedStatuses = ['queued', 'processing', 'completed', 'failed'];
$status = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : '';
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'q.status = :status';
    $params[':status'] = $status;
}
if ($search !== '') {
    $where[] = '(m.phone LIKE :search_phone OR m.message LIKE :search_message OR CAST(m.id AS CHAR) = :search_id)';
    $params[':search_phone'] = '%' . $search . '%';
    $params[':search_message'] = '%' . $search . '%';
    $params[':search_id'] = $search;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stats = [
    'total' => (int)$pdo->query('SELECT COUNT(*) FROM sms_queue')->fetchColumn(),
    'queued' => (int)$pdo->query("SELECT COUNT(*) FROM sms_queue WHERE status = 'queued'")->fetchColumn(),
    'processing' => (int)$pdo->query("SELECT COUNT(*) FROM sms_queue WHERE status = 'processing'")->fetchColumn(),
    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM sms_queue WHERE status = 'completed'")->fetchColumn(),
    'failed' => (int)$pdo->query("SELECT COUNT(*) FROM sms_queue WHERE status = 'failed'")->fetchColumn(),
];

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sms_queue q INNER JOIN sms_messages m ON m.id = q.message_id $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare("
    SELECT q.id AS queue_id, q.status AS queue_status, q.attempts, q.available_at,
           q.locked_at, q.last_error, q.created_at AS queued_at,
           m.id AS message_id, m.phone, m.message, m.direction,
           m.status AS message_status, m.created_at AS message_created_at,
           p.name AS project_name
    FROM sms_queue q
    INNER JOIN sms_messages m ON m.id = q.message_id
    LEFT JOIN projects p ON p.id = m.project_id
    $whereSql
    ORDER BY q.id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $value) {
    $listStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();

$statusLabels = [
    'queued' => 'در صف انتظار',
    'processing' => 'در حال پردازش',
    'completed' => 'تکمیل‌شده',
    'failed' => 'ناموفق',
];
$statusClasses = [
    'queued' => 'queued',
    'processing' => 'processing',
    'completed' => 'completed',
    'failed' => 'failed',
];

function queryUrl(array $changes = []): string {
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) unset($query[$key]);
    }
    return '?' . http_build_query($query);
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>مدیریت صف پیامک | SMS Gateway</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:#172b45;font-family:Tahoma,Arial,sans-serif}
.queue-page{max-width:1400px;margin:auto;padding:30px 22px}.page-head{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:24px}
h1{font-size:25px;margin:0 0 8px}.subtitle{color:#718096;font-size:13px}.back-link{display:inline-flex;text-decoration:none;background:#102b4c;color:#fff;padding:11px 16px;border-radius:9px;font-size:13px}
.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:22px}.stat{background:#fff;border:1px solid #e6edf5;border-radius:14px;padding:18px;box-shadow:0 5px 18px rgba(16,43,76,.035)}
.stat-label{font-size:12px;color:#718096}.stat-value{display:block;font-size:27px;font-weight:bold;margin:10px 0 4px;color:#102b4c}.stat-note{font-size:11px;color:#8a98aa}
.toolbar,.table-panel{background:#fff;border:1px solid #e6edf5;border-radius:15px;box-shadow:0 5px 18px rgba(16,43,76,.035)}.toolbar{padding:18px;margin-bottom:18px;display:flex;gap:14px;flex-wrap:wrap}
.filters{display:flex;gap:10px;align-items:end;flex:1;flex-wrap:wrap}.field{min-width:190px;flex:1}.field label{display:block;font-size:12px;font-weight:bold;margin-bottom:8px}
input,select{width:100%;height:42px;border:1px solid #dce5f0;border-radius:9px;padding:0 12px;font:inherit;font-size:13px;background:#fff;color:#172b45}
input:focus,select:focus{outline:none;border-color:#2878c8;box-shadow:0 0 0 3px rgba(40,120,200,.1)}.btn{height:42px;padding:0 18px;border:0;border-radius:9px;background:#2878c8;color:#fff;font:inherit;font-size:13px;font-weight:bold;cursor:pointer}
.reset{font-size:12px;color:#64748b;text-decoration:none;padding:12px 4px}.table-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:19px 20px;border-bottom:1px solid #edf1f6}
.table-head h2{font-size:16px;margin:0}.result-count{font-size:12px;color:#718096}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:950px}
th,td{text-align:right;padding:14px 13px;border-bottom:1px solid #edf1f6;font-size:12px;vertical-align:middle}th{background:#f8fafd;color:#718096;font-weight:bold;white-space:nowrap}tbody tr:hover{background:#fbfdff}
.phone{font-weight:bold;direction:ltr;text-align:right;white-space:nowrap}.message{max-width:280px;line-height:1.9;color:#40516a;overflow-wrap:anywhere}.pill{display:inline-block;padding:6px 10px;border-radius:30px;font-size:11px;white-space:nowrap}
.queued{background:#fff6df;color:#9a6700}.processing{background:#eaf2ff;color:#245ea8}.completed{background:#e8f7ee;color:#217346}.failed{background:#fff0f0;color:#b42332}
.attempts{font-weight:bold;text-align:center}.empty{text-align:center;padding:45px 15px;color:#8290a3}.pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 20px;flex-wrap:wrap}
.pages{display:flex;gap:6px;align-items:center}.page-link{display:inline-flex;min-width:34px;height:34px;align-items:center;justify-content:center;border:1px solid #e1e8f0;border-radius:8px;text-decoration:none;color:#40516a;font-size:12px;padding:0 9px}
.page-link.active{background:#2878c8;border-color:#2878c8;color:#fff}.page-link.disabled{opacity:.4;pointer-events:none}.footnote{font-size:11px;color:#8a98aa;margin:16px 2px 0;line-height:1.9}
@media(max-width:900px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:600px){.queue-page{padding:22px 12px}.stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.stat{padding:14px}.stat-value{font-size:23px}h1{font-size:21px}.toolbar{padding:14px}.filters{display:grid;grid-template-columns:1fr;width:100%}.field{min-width:0}.btn{width:100%}.table-head{padding:16px}}
</style>
</head>
<body>
<main class="queue-page">
<header class="page-head"><div><h1>مدیریت صف پیامک</h1><div class="subtitle">مشاهده و پایش پیامک‌های ثبت‌شده برای پردازش</div></div><a class="back-link" href="index.php">بازگشت به داشبورد</a></header>
<section class="stats">
<article class="stat"><span class="stat-label">کل موارد صف</span><strong class="stat-value"><?= number_format($stats['total']) ?></strong><span class="stat-note">تمام پیام‌های ثبت‌شده</span></article>
<article class="stat"><span class="stat-label">در صف انتظار</span><strong class="stat-value"><?= number_format($stats['queued']) ?></strong><span class="stat-note">آماده پردازش</span></article>
<article class="stat"><span class="stat-label">در حال پردازش</span><strong class="stat-value"><?= number_format($stats['processing']) ?></strong><span class="stat-note">در حال انجام عملیات</span></article>
<article class="stat"><span class="stat-label">تکمیل‌شده</span><strong class="stat-value"><?= number_format($stats['completed']) ?></strong><span class="stat-note">پردازش موفق</span></article>
<article class="stat"><span class="stat-label">ناموفق</span><strong class="stat-value"><?= number_format($stats['failed']) ?></strong><span class="stat-note">نیازمند بررسی</span></article>
</section>
<section class="toolbar"><form class="filters" method="get">
<div class="field"><label for="q">جستجو</label><input id="q" name="q" value="<?= h($search) ?>" placeholder="شماره، متن پیام یا شناسه پیامک"></div>
<div class="field" style="max-width:220px"><label for="status">وضعیت صف</label><select id="status" name="status"><option value="">همه وضعیت‌ها</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
<button class="btn" type="submit">اعمال فیلتر</button><a class="reset" href="queue.php">پاک‌کردن فیلترها</a>
</form></section>
<section class="table-panel">
<div class="table-head"><h2>فهرست پیام‌های صف</h2><span class="result-count"><?= number_format($totalRows) ?> نتیجه</span></div>
<div class="table-wrap"><table><thead><tr><th>شناسه</th><th>گیرنده</th><th>متن پیام</th><th>پروژه</th><th>وضعیت صف</th><th>تلاش</th><th>زمان ثبت</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">موردی مطابق جستجو پیدا نشد.</td></tr><?php else: foreach ($rows as $row): ?>
<tr><td>#<?= h($row['message_id']) ?></td><td class="phone"><?= h($row['phone']) ?></td><td><div class="message" title="<?= h($row['message']) ?>"><?= h(mb_strlen((string)$row['message']) > 90 ? mb_substr((string)$row['message'], 0, 90) . '…' : $row['message']) ?></div></td><td><?= h($row['project_name'] ?: '—') ?></td><td><span class="pill <?= h($statusClasses[$row['queue_status']] ?? 'queued') ?>"><?= h($statusLabels[$row['queue_status']] ?? $row['queue_status']) ?></span></td><td class="attempts"><?= number_format((int)$row['attempts']) ?></td><td><?= h(JalaliDate::format($row['queued_at'])) ?></td></tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<div class="pagination"><span class="result-count">صفحه <?= number_format($page) ?> از <?= number_format($totalPages) ?></span><nav class="pages" aria-label="صفحه‌بندی">
<a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= h(queryUrl(['page' => max(1, $page - 1)])) ?>">قبلی</a>
<?php $startPage=max(1,$page-2); $endPage=min($totalPages,$page+2); for($i=$startPage;$i<=$endPage;$i++): ?><a class="page-link <?= $i===$page?'active':'' ?>" href="<?= h(queryUrl(['page'=>$i])) ?>"><?= number_format($i) ?></a><?php endfor; ?>
<a class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= h(queryUrl(['page' => min($totalPages, $page + 1)])) ?>">بعدی</a>
</nav></div>
</section>
<p class="footnote">این صفحه در حال حاضر فقط برای مشاهده و پایش صف است؛ هیچ پیامکی ارسال، حذف یا ویرایش نمی‌شود. ارسال واقعی پس از تکمیل Worker و اتصال مودم فعال خواهد شد.</p>
</main>
</body>
</html>
