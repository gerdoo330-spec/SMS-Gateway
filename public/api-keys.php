<?php

session_start();

require_once __DIR__ . '/../app/Services/Database.php';
require_once __DIR__ . '/../app/Services/JalaliDate.php';

$db = Database::connection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('درخواست نامعتبر است.');
    }

    $keyId = filter_var(
        $_POST['key_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$keyId || $keyId < 1) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'شناسه کلید معتبر نیست.'
        ];
    } else {
        $stmt = $db->prepare("
            UPDATE api_keys
            SET status = 'revoked'
            WHERE id = :id AND status = 'active'
        ");

        $stmt->execute([':id' => $keyId]);

        $_SESSION['flash'] = [
            'type' => $stmt->rowCount() > 0 ? 'success' : 'error',
            'message' => $stmt->rowCount() > 0
                ? 'کلید API با موفقیت باطل شد.'
                : 'کلید فعال موردنظر پیدا نشد.'
        ];
    }

    header('Location: api-keys.php');
    exit;
}

$projects = $db->query("
    SELECT
        p.id,
        p.name,
        k.id AS key_id,
        k.key_prefix,
        k.status AS key_status,
        k.created_at
    FROM projects p
    LEFT JOIN api_keys k ON k.project_id = p.id
    ORDER BY k.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$totalKeys = 0;
$activeKeys = 0;
$revokedKeys = 0;

foreach ($projects as $item) {
    if ($item['key_id'] !== null) {
        $totalKeys++;

        if ($item['key_status'] === 'active') {
            $activeKeys++;
        } else {
            $revokedKeys++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>مدیریت کلیدهای API | SMS Gateway</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f3f6fb;
    color: #172b45;
    font-family: Tahoma, Arial, sans-serif;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 32px 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 28px;
}

h1 {
    font-size: 24px;
    margin: 0 0 8px;
}

.subtitle {
    color: #718096;
    font-size: 13px;
}

.btn {
    border: 0;
    border-radius: 9px;
    padding: 11px 16px;
    background: #2878c8;
    color: white;
    cursor: pointer;
    font: inherit;
    font-size: 12px;
    text-decoration: none;
}

.btn-dark {
    background: #102b4c;
}

.btn-danger {
    background: #c63e4b;
}

.btn:hover {
    opacity: .88;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat {
    background: white;
    padding: 21px;
    border-radius: 14px;
    border: 1px solid #e7edf5;
}

.stat span {
    display: block;
    color: #718096;
    font-size: 12px;
    margin-bottom: 12px;
}

.stat strong {
    font-size: 25px;
}

.panel {
    background: white;
    border: 1px solid #e7edf5;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 20px rgba(16,43,76,.04);
}

.panel h2 {
    font-size: 17px;
    margin: 0 0 20px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    text-align: right;
    padding: 15px 12px;
    border-bottom: 1px solid #edf1f6;
    font-size: 13px;
}

th {
    background: #f8fafd;
    color: #718096;
}

.key {
    direction: ltr;
    display: inline-block;
    background: #f1f5fa;
    border-radius: 6px;
    padding: 7px 10px;
    font-family: monospace;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
}

.active {
    background: #e9f7ef;
    color: #23834d;
}

.revoked {
    background: #fff0f0;
    color: #b42332;
}

.notice {
    padding: 13px;
    border-radius: 9px;
    margin-bottom: 18px;
    font-size: 13px;
}

.success {
    background: #e9f7ef;
    color: #23834d;
}

.error {
    background: #fff0f0;
    color: #b42332;
}

.empty {
    text-align: center;
    color: #718096;
    padding: 35px;
}

@media(max-width:650px) {
    .container {
        padding: 20px 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 15px;
    }
}
</style>
</head>

<body>

<main class="container">

    <header class="header">
        <div>
            <h1>مدیریت کلیدهای API</h1>
            <div class="subtitle">
                مشاهده و مدیریت دسترسی پروژهها به سرویس پیامکی
            </div>
        </div>

        <a href="projects.php" class="btn btn-dark">
            بازگشت به پروژهها
        </a>
    </header>

    <?php if ($flash): ?>
        <div class="notice <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat">
            <span>کلیدهای ساختهشده</span>
            <strong><?= $totalKeys ?></strong>
        </div>

        <div class="stat">
            <span>کلیدهای فعال</span>
            <strong><?= $activeKeys ?></strong>
        </div>

        <div class="stat">
            <span>کلیدهای باطلشده</span>
            <strong><?= $revokedKeys ?></strong>
        </div>

    </section>

    <section class="panel">

        <h2>فهرست کلیدهای دسترسی</h2>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>پروژه</th>
                        <th>پیشوند کلید</th>
                        <th>تاریخ ساخت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $hasKeys = false;
                foreach ($projects as $item):
                    if ($item['key_id'] === null) {
                        continue;
                    }

                    $hasKeys = true;
                ?>

                    <tr>
                        <td>
                            <strong><?= e($item['name']) ?></strong>
                        </td>

                        <td>
                            <code class="key">
                                <?= e($item['key_prefix']) ?>...
                            </code>
                        </td>

                        <td>
                            <?= e(JalaliDate::format($item['created_at'])) ?>
                        </td>

                        <td>
                            <?php if ($item['key_status'] === 'active'): ?>
                                <span class="status active">
                                    فعال
                                </span>
                            <?php else: ?>
                                <span class="status revoked">
                                    باطلشده
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($item['key_status'] === 'active'): ?>

                                <form method="post"
                                      onsubmit="return confirm('آیا از باطل کردن این کلید مطمئن هستی؟')">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= e($_SESSION['csrf_token']) ?>">

                                    <input type="hidden"
                                           name="key_id"
                                           value="<?= e($item['key_id']) ?>">

                                    <button type="submit"
                                            class="btn btn-danger">
                                        باطل کردن کلید
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="subtitle">
                                    غیرفعال شده
                                </span>

                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$hasKeys): ?>
                    <tr>
                        <td colspan="5" class="empty">
                            هنوز هیچ کلید API ساخته نشده است.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </section>

</main>

</body>
</html>

