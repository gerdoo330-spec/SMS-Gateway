<?php
session_start();

require_once __DIR__ . '/../app/Services/Database.php';
require_once __DIR__ . '/../app/Services/ProjectService.php';

$service = new ProjectService();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';
$newApiKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) ||
            !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('درخواست معتبر نیست.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'create_project') {
            $id = $service->createProject(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['description'] ?? '')
            );

            $success = 'پروژه با موفقیت ثبت شد. شناسه: ' . $id;
        } elseif ($action === 'create_key') {
            $newApiKey = $service->createApiKey(
                (int)($_POST['project_id'] ?? 0)
            );

            $success = 'کلید API با موفقیت ساخته شد.';
        } elseif ($action === 'revoke_key') {
            $revoked = $service->revokeApiKey(
                (int)($_POST['key_id'] ?? 0)
            );

            $success = $revoked
                ? 'کلید API غیرفعال شد.'
                : 'کلید فعال موردنظر پیدا نشد.';
        } else {
            throw new RuntimeException('عملیات نامعتبر است.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$projects = $service->getAllProjects();

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مدیریت پروژهها | SMS Gateway</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f3f6fb;
            color: #172b45;
            font-family: Tahoma, Arial, sans-serif;
        }

        .projects-page {
            max-width: 1250px;
            margin: auto;
            padding: 35px 22px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        h1 { margin: 0 0 8px; font-size: 25px; }
        .subtitle { color: #718096; font-size: 13px; }

        .panel {
            background: #fff;
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 5px 20px rgba(16,43,76,.04);
        }

        .panel h2 {
            font-size: 17px;
            margin: 0 0 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 9px;
            font-size: 13px;
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce5f0;
            border-radius: 9px;
            font: inherit;
            outline: none;
        }

        input:focus, textarea:focus {
            border-color: #2878c8;
            box-shadow: 0 0 0 3px rgba(40,120,200,.1);
        }

        textarea {
            resize: vertical;
            min-height: 45px;
        }

        .btn {
            border: 0;
            border-radius: 9px;
            padding: 12px 18px;
            background: #2878c8;
            color: white;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: bold;
        }

        .btn:hover { background: #1d63a8; }
        .btn-dark { background: #102b4c; }
        .btn-danger { background: #c63e4b; }
        .btn-small { padding: 8px 11px; font-size: 12px; }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 15px 12px;
            text-align: right;
            border-bottom: 1px solid #edf1f6;
            font-size: 13px;
        }

        th { color: #718096; background: #f8fafd; }

        .status {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 11px;
            background: #e9f7ef;
            color: #23834d;
        }

        .notice {
            padding: 13px 16px;
            border-radius: 10px;
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

        .key-box {
            background: #f1f6ff;
            border: 1px solid #c9ddfa;
            border-radius: 12px;
            padding: 17px;
            margin-bottom: 22px;
        }

        .key-value {
            direction: ltr;
            display: block;
            overflow-wrap: anywhere;
            font-family: monospace;
            background: white;
            padding: 13px;
            border-radius: 8px;
            margin: 12px 0;
        }

        .muted { color: #718096; font-size: 12px; }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media(max-width: 650px) {
            .projects-page { padding: 22px 13px; }
            .form-grid { grid-template-columns: 1fr; }
            .panel { padding: 17px; }
            h1 { font-size: 21px; }
        }
    </style>
</head>
<body>

<main class="projects-page">

    <header class="topbar">
        <div>
            <h1>مدیریت پروژهها</h1>
            <div class="subtitle">
                مدیریت پروژههای متصل به سرویس پیامکی
            </div>
        </div>

        <a href="index.php" class="btn btn-dark"
           style="text-decoration:none">
            بازگشت به داشبورد
        </a>
    </header>

    <?php if ($success): ?>
        <div class="notice success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($newApiKey): ?>
        <section class="key-box">
            <strong>کلید API جدید ساخته شد</strong>
            <p class="muted">
                این کلید فقط همین یک بار نمایش داده میشود.
                آن را در محل امن نگهداری کن.
            </p>

            <code class="key-value" id="apiKey"><?= e($newApiKey) ?></code>

            <button class="btn" type="button" onclick="copyKey()">
                کپی کلید API
            </button>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>+ ثبت پروژه جدید</h2>

        <form method="post">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action"
                   value="create_project">

            <div class="form-grid">
                <div class="field">
                    <label>نام پروژه</label>
                    <input type="text" name="name"
                           maxlength="150"
                           placeholder="مثلاً RestaurantPOS"
                           required>
                </div>

                <div class="field">
                    <label>توضیحات</label>
                    <input type="text" name="description"
                           placeholder="توضیحات پروژه">
                </div>
            </div>

            <div style="margin-top:18px">
                <button class="btn" type="submit">
                    ثبت پروژه
                </button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>فهرست پروژهها</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام پروژه</th>
                        <th>وضعیت</th>
                        <th>کلیدها</th>
                        <th>عملیات</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$projects): ?>
                    <tr>
                        <td colspan="5">
                            هنوز پروژهای ثبت نشده است.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?= e($project['id']) ?></td>

                            <td>
                                <strong><?= e($project['name']) ?></strong>
                                <div class="muted">
                                    <?= e($project['description']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="status">
                                    <?= $project['status'] === 'active'
                                        ? 'فعال' : 'غیرفعال' ?>
                                </span>
                            </td>

                            <td>
                                <?= e($project['active_keys'] ?? 0) ?>
                                فعال از
                                <?= e($project['total_keys'] ?? 0) ?>
                            </td>

                            <td>
                                <?php if ($project['status'] === 'active'): ?>
                                    <form method="post" class="actions">
                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden"
                                               name="action"
                                               value="create_key">
                                        <input type="hidden"
                                               name="project_id"
                                               value="<?= e($project['id']) ?>">

                                        <button class="btn btn-small"
                                                type="submit">
                                            ساخت API Key
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">
                                        پروژه غیرفعال است
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<script>
function copyKey() {
    const key = document.getElementById('apiKey');

    if (!key) return;

    navigator.clipboard.writeText(key.textContent.trim())
        .then(() => alert('کلید API کپی شد.'))
        .catch(() => alert('کپی خودکار انجام نشد؛ کلید را دستی کپی کن.'));
}
</script>

</body>
</html>

