<?php
// classes/StockIn.php

class StockIn {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $staffId, ?int $supplierId, string $notes, array $items): int|false {
        try {
            $this->db->beginTransaction();
            $s = $this->db->prepare(
                'INSERT INTO stock_in (supplier_id, staff_id, notes) VALUES (?,?,?)'
            );
            $s->execute([$supplierId, $staffId, $notes]);
            $stockInId = (int)$this->db->lastInsertId();

            $si = $this->db->prepare(
                'INSERT INTO stock_in_items (stockin_id, product_id, quantity, cost_price) VALUES (?,?,?,?)'
            );
            foreach ($items as $item) {
                $si->execute([$stockInId, $item['product_id'], $item['qty'], $item['cost_price']]);
            }

            $this->db->commit();
            return $stockInId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getAll(): array {
        return $this->db->query(
            'SELECT si.stockin_id, si.date_received, si.notes,
                    sup.name AS supplier_name,
                    CONCAT(st.SFN," ",st.SLN) AS received_by
             FROM stock_in si
             LEFT JOIN suppliers sup ON sup.supplier_id=si.supplier_id
             JOIN staff st ON st.staff_id=si.staff_id
             ORDER BY si.date_received DESC'
        )->fetchAll();
    }

    public function getItemsByStockInId(int $id): array {
        $s = $this->db->prepare(
            'SELECT sii.*, p.product_name, p.size
             FROM stock_in_items sii
             JOIN products p ON p.product_id=sii.product_id
             WHERE sii.stockin_id=?'
        );
        $s->execute([$id]);
        return $s->fetchAll();
    }
}
