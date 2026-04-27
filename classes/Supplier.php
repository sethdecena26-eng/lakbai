<?php
// classes/Supplier.php

class Supplier {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(): array {
        return $this->db->query('SELECT * FROM suppliers WHERE archived_at IS NULL ORDER BY name')->fetchAll();
    }

    public function getArchived(): array {
        return $this->db->query('SELECT * FROM suppliers WHERE archived_at IS NOT NULL ORDER BY archived_at DESC')->fetchAll();
    }

    public function archive(int $id): bool {
        return $this->db->prepare('UPDATE suppliers SET archived_at=NOW() WHERE supplier_id=?')->execute([$id]);
    }

    public function restore(int $id): bool {
        return $this->db->prepare('UPDATE suppliers SET archived_at=NULL WHERE supplier_id=?')->execute([$id]);
    }

    public function getById(int $id): array|false {
        $s = $this->db->prepare('SELECT * FROM suppliers WHERE supplier_id=?');
        $s->execute([$id]);
        return $s->fetch();
    }

    public function create(string $name, string $contact, string $email, string $phone, string $address): bool {
        return $this->db->prepare(
            'INSERT INTO suppliers (name, contact, email, phone, address) VALUES (?,?,?,?,?)'
        )->execute([$name, $contact, $email, $phone, $address]);
    }

    public function update(int $id, string $name, string $contact, string $email, string $phone, string $address): bool {
        return $this->db->prepare(
            'UPDATE suppliers SET name=?, contact=?, email=?, phone=?, address=? WHERE supplier_id=?'
        )->execute([$name, $contact, $email, $phone, $address, $id]);
    }

    public function delete(int $id): bool {
        return $this->db->prepare('DELETE FROM suppliers WHERE supplier_id=?')->execute([$id]);
    }

    public function countAll(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
    }
}
