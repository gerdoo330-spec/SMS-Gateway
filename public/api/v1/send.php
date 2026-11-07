<?php

header('Content-Type: application/json; charset=utf-8');

function response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// فقط POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(405, [
        'success' => false,
        'message' => 'فقط درخواست POST مجاز است.'
    ]);
}

// اتصال دیتابیس
require_once __DIR__ . '/../../../app/Services/Database.php';

try {
    $db = Database::connection();
} catch (Throwable $e) {
    response(500, [
        'success' => false,
        'message' => 'خطا در اتصال به دیتابیس.'
    ]);
}

// دریافت API Key
$headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

$authorization =
    $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? $headers['Authorization']
    ?? $headers['authorization']
    ?? '';

if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
    response(401, [
        'success' => false,
        'message' => 'API Key ارسال نشده است.'
    ]);
}

$apiKey = trim($matches[1]);

if (strlen($apiKey) > 200) {
    response(401, [
        'success' => false,
        'message' => 'API Key نامعتبر است.'
    ]);
}

// اعتبارسنجی کلید و پروژه
$keyHash = hash('sha256', $apiKey);

$stmt = $db->prepare("
    SELECT
        k.project_id,
        p.name AS project_name
    FROM api_keys k
    INNER JOIN projects p ON p.id = k.project_id
    WHERE
        k.key_hash = :key_hash
        AND k.status = 'active'
        AND p.status = 'active'
    LIMIT 1
");

$stmt->execute([
    ':key_hash' => $keyHash
]);

$project = $stmt->fetch();

if (!$project) {
    response(401, [
        'success' => false,
        'message' => 'API Key نامعتبر یا غیرفعال است.'
    ]);
}

// دریافت JSON
$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    response(400, [
        'success' => false,
        'message' => 'ساختار JSON نامعتبر است.'
    ]);
}

$phone = trim((string) ($input['phone'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));

// اعتبارسنجی شماره ایران
if (!preg_match('/^(09\d{9}|\+989\d{9}|989\d{9})$/', $phone)) {
    response(422, [
        'success' => false,
        'message' => 'شماره موبایل معتبر نیست.'
    ]);
}

// یکسانسازی شماره
if (str_starts_with($phone, '+98')) {
    $phone = '0' . substr($phone, 3);
} elseif (str_starts_with($phone, '989')) {
    $phone = '0' . substr($phone, 2);
}

// اعتبارسنجی متن
if ($message === '') {
    response(422, [
        'success' => false,
        'message' => 'متن پیام الزامی است.'
    ]);
}

$messageLength = function_exists('mb_strlen')
    ? mb_strlen($message, 'UTF-8')
    : strlen($message);

if ($messageLength > 5000) {
    response(422, [
        'success' => false,
        'message' => 'متن پیام بیش از حد طولانی است.'
    ]);
}

// ثبت همزمان پیام و صف
try {
    $db->beginTransaction();

    // ثبت پیام
    $stmt = $db->prepare("
        INSERT INTO sms_messages
            (project_id, phone, message, direction, status)
        VALUES
            (:project_id, :phone, :message, 'outgoing', 'pending')
    ");

    $stmt->execute([
        ':project_id' => $project['project_id'],
        ':phone' => $phone,
        ':message' => $message
    ]);

    $messageId = (int) $db->lastInsertId();

    // ورود پیام به صف
    $stmt = $db->prepare("
        INSERT INTO sms_queue
            (message_id, status)
        VALUES
            (:message_id, 'queued')
    ");

    $stmt->execute([
        ':message_id' => $messageId
    ]);

    $db->commit();

    response(201, [
        'success' => true,
        'message' => 'پیام با موفقیت ثبت و وارد صف ارسال شد.',
        'data' => [
            'message_id' => $messageId,
            'project' => $project['project_name'],
            'phone' => $phone,
            'message_status' => 'pending',
            'queue_status' => 'queued'
        ]
    ]);

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    response(500, [
        'success' => false,
        'message' => 'خطا در ثبت پیام و صف ارسال.'
    ]);
}

