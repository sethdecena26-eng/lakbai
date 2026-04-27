<?php
// classes/Customer.php

class Customer {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(): array {
        return $this->db->query(
            'SELECT customer_id, CFN, CLN, contact_number,
                    CONCAT(CFN," ",CLN) AS full_name
             FROM customers ORDER BY CFN, CLN'
        )->fetchAll();
    }

    public function search(string $query): array {
        $q = '%' . $query . '%';
        $s = $this->db->prepare(
            'SELECT customer_id, CFN, CLN, contact_number,
                    CONCAT(CFN," ",CLN) AS full_name
             FROM customers
             WHERE CFN LIKE ? OR CLN LIKE ? OR CONCAT(CFN," ",CLN) LIKE ? OR contact_number LIKE ?
             ORDER BY CFN, CLN LIMIT 10'
        );
        $s->execute([$q, $q, $q, $q]);
        return $s->fetchAll();
    }

    public function getById(int $id): array|false {
        $s = $this->db->prepare(
            'SELECT customer_id, CFN, CLN, contact_number,
                    CONCAT(CFN," ",CLN) AS full_name
             FROM customers WHERE customer_id = ?'
        );
        $s->execute([$id]);
        return $s->fetch();
    }

    public function create(string $CFN, string $CLN, string $contact): int|false {
        $s = $this->db->prepare(
            'INSERT INTO customers (CFN, CLN, contact_number) VALUES (?, ?, ?)'
        );
        $s->execute([$CFN, $CLN, $contact]);
        return (int)$this->db->lastInsertId() ?: false;
    }

    public function countAll(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }

    public function getPurchaseHistory(int $customerId): array {
        $s = $this->db->prepare(
            'SELECT p.purchase_id, p.total_amount, p.purchase_date,
                    pay.payment_method, pay.amount_paid
             FROM purchases p
             LEFT JOIN payments pay ON pay.purchase_id = p.purchase_id
             WHERE p.customer_id = ?
             ORDER BY p.purchase_date DESC'
        );
        $s->execute([$customerId]);
        return $s->fetchAll();
    }
}
