<?php
// classes/Product.php

class Product {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(): array {
        return $this->db->query(
            'SELECT p.product_id, p.product_name, c.name AS category, p.size,
                    p.cost_price, p.price, p.reorder_lvl, s.quantity
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             JOIN stock s ON s.product_id = p.product_id
             WHERE p.archived_at IS NULL
             ORDER BY c.name, p.product_name, p.size'
        )->fetchAll();
    }

    public function getArchived(): array {
        return $this->db->query(
            'SELECT p.product_id, p.product_name, c.name AS category, p.size,
                    p.cost_price, p.price, p.reorder_lvl, s.quantity, p.archived_at
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             JOIN stock s ON s.product_id = p.product_id
             WHERE p.archived_at IS NOT NULL
             ORDER BY p.archived_at DESC'
        )->fetchAll();
    }

    public function archive(int $id): bool {
        return $this->db->prepare(
            'UPDATE products SET archived_at=NOW() WHERE product_id=?'
        )->execute([$id]);
    }

    public function restore(int $id): bool {
        return $this->db->prepare(
            'UPDATE products SET archived_at=NULL WHERE product_id=?'
        )->execute([$id]);
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category, s.quantity
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             JOIN stock s ON s.product_id = p.product_id
             WHERE p.product_id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getForPOS(): array {
        return $this->db->query(
            'SELECT p.product_id, p.product_name, p.size, p.price, p.cost_price,
                    c.name AS category, s.quantity
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             JOIN stock s ON s.product_id = p.product_id
             WHERE s.quantity > 0 AND p.archived_at IS NULL
             ORDER BY c.name, p.product_name, p.size'
        )->fetchAll();
    }

    public function create(string $name, ?int $catId, string $size, float $price, float $costPrice, int $reorder): bool {
        try {
            $this->db->beginTransaction();
            $s = $this->db->prepare(
                'INSERT INTO products (product_name, category_id, size, price, cost_price, reorder_lvl)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $s->execute([$name, $catId, $size, $price, $costPrice, $reorder]);
            $pid = (int)$this->db->lastInsertId();
            $this->db->prepare('INSERT INTO stock (product_id, quantity) VALUES (?, 0)')->execute([$pid]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function update(int $id, string $name, ?int $catId, string $size, float $price, float $costPrice, int $qty, int $reorder): bool {
        try {
            $this->db->beginTransaction();
            $s = $this->db->prepare(
                'UPDATE products SET product_name=?, category_id=?, size=?, price=?, cost_price=?, reorder_lvl=? WHERE product_id=?'
            );
            $s->execute([$name, $catId, $size, $price, $costPrice, $reorder, $id]);
            $this->db->prepare('UPDATE stock SET quantity=? WHERE product_id=?')->execute([$qty, $id]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function delete(int $id): bool {
        return $this->db->prepare('DELETE FROM products WHERE product_id=?')->execute([$id]);
    }

    public function countAll(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    public function countLowStock(): int {
        return (int)$this->db->query(
            'SELECT COUNT(*) FROM products p JOIN stock s ON s.product_id=p.product_id WHERE s.quantity<=p.reorder_lvl'
        )->fetchColumn();
    }

    public function getLowStock(): array {
        return $this->db->query('SELECT * FROM low_stock_view')->fetchAll();
    }

    public function getCategories(): array {
        return $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }

    public function createCategory(string $name): bool {
        return $this->db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
    }
}
