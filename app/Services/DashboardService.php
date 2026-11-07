<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class DashboardService
{
    public static function getStats(): array
    {
        $pdo = Database::connection();

        $queries = [
            'projects' => 'SELECT COUNT(*) FROM projects',
            'messages' => 'SELECT COUNT(*) FROM sms_messages',
            'pending' => "SELECT COUNT(*) FROM sms_messages WHERE status = 'pending'",
            'failed' => "SELECT COUNT(*) FROM sms_messages WHERE status = 'failed'",
        ];

        $stats = [];
        foreach ($queries as $key => $sql) {
            $stats[$key] = (int) $pdo->query($sql)->fetchColumn();
        }

        return $stats;
    }
}
