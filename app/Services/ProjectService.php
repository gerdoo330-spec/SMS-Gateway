<?php

final class ProjectService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getAllProjects(): array
    {
        $sql = "
            SELECT
                p.*,
                COUNT(k.id) AS total_keys,
                SUM(
                    CASE WHEN k.status = 'active'
                    THEN 1 ELSE 0 END
                ) AS active_keys
            FROM projects p
            LEFT JOIN api_keys k ON k.project_id = p.id
            GROUP BY p.id
            ORDER BY p.id DESC
        ";

        return $this->db->query($sql)->fetchAll();
    }

    public function createProject(
        string $name,
        string $description
    ): int {
        $name = trim($name);
        $description = trim($description);

        if ($name === '') {
            throw new InvalidArgumentException('نام پروژه الزامی است.');
        }

        if (mb_strlen($name) > 150) {
            throw new InvalidArgumentException('نام پروژه بیش از حد طولانی است.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO projects (name, description, status)
            VALUES (:name, :description, 'active')
        ");

        $stmt->execute([
            ':name' => $name,
            ':description' => $description
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createApiKey(int $projectId): string
    {
        $check = $this->db->prepare("
            SELECT id FROM projects
            WHERE id = :id AND status = 'active'
        ");

        $check->execute([':id' => $projectId]);

        if (!$check->fetch()) {
            throw new RuntimeException('پروژه فعال پیدا نشد.');
        }

        $secret = 'sk_live_' . bin2hex(random_bytes(32));
        $prefix = substr($secret, 0, 16);
        $hash = hash('sha256', $secret);

        $stmt = $this->db->prepare("
            INSERT INTO api_keys
                (project_id, key_prefix, key_hash, status)
            VALUES
                (:project_id, :key_prefix, :key_hash, 'active')
        ");

        $stmt->execute([
            ':project_id' => $projectId,
            ':key_prefix' => $prefix,
            ':key_hash' => $hash
        ]);

        return $secret;
    }

    public function revokeApiKey(int $keyId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE api_keys
            SET status = 'revoked'
            WHERE id = :id AND status = 'active'
        ");

        $stmt->execute([':id' => $keyId]);

        return $stmt->rowCount() > 0;
    }
}