<?php
// classes/User.php  (maps to `staff` table)

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(): array {
        return $this->db->query(
            'SELECT staff_id, SFN, SLN, email, role, created_at FROM staff WHERE archived_at IS NULL ORDER BY staff_id DESC'
        )->fetchAll();
    }

    public function getArchived(): array {
        return $this->db->query(
            'SELECT staff_id, SFN, SLN, email, role, created_at, archived_at FROM staff WHERE archived_at IS NOT NULL ORDER BY archived_at DESC'
        )->fetchAll();
    }

    public function archive(int $id): bool {
        return $this->db->prepare('UPDATE staff SET archived_at=NOW() WHERE staff_id=?')->execute([$id]);
    }

    public function restore(int $id): bool {
        return $this->db->prepare('UPDATE staff SET archived_at=NULL WHERE staff_id=?')->execute([$id]);
    }

    public function getById(int $id): array|false {
        $s = $this->db->prepare(
            'SELECT staff_id, SFN, SLN, email, role FROM staff WHERE staff_id = ?'
        );
        $s->execute([$id]);
        return $s->fetch();
    }

    public function create(string $SFN, string $SLN, string $email, string $password, string $role): bool {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $s = $this->db->prepare(
            'INSERT INTO staff (SFN, SLN, email, password, role) VALUES (?, ?, ?, ?, ?)'
        );
        return $s->execute([$SFN, $SLN, $email, $hash, $role]);
    }

    public function update(int $id, string $SFN, string $SLN, string $email, string $role, string $password = ''): bool {
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $s = $this->db->prepare(
                'UPDATE staff SET SFN=?, SLN=?, email=?, role=?, password=? WHERE staff_id=?'
            );
            return $s->execute([$SFN, $SLN, $email, $role, $hash, $id]);
        }
        $s = $this->db->prepare(
            'UPDATE staff SET SFN=?, SLN=?, email=?, role=? WHERE staff_id=?'
        );
        return $s->execute([$SFN, $SLN, $email, $role, $id]);
    }

    public function delete(int $id): bool {
        $s = $this->db->prepare('DELETE FROM staff WHERE staff_id = ?');
        return $s->execute([$id]);
    }

    public function countAll(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM staff')->fetchColumn();
    }

    // Helper: full name
    public static function fullName(string $SFN, string $SLN): string {
        return trim($SFN . ' ' . $SLN);
    }
}
