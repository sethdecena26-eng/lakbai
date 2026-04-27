<?php
// classes/OnlineOrder.php

class OnlineOrder {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'o.order_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'o.payment_status = ?';
            $params[] = $filters['payment_status'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(o.ordered_at) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(o.ordered_at) <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(CONCAT(c.CFN,' ',c.CLN) LIKE ? OR o.delivery_address LIKE ?)";
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $sql = "SELECT o.*,
                    CONCAT(c.CFN,' ',c.CLN)   AS customer_name,
                    c.contact_number,
                    CONCAT(s.SFN,' ',s.SLN)   AS staff_name
                FROM online_orders o
                JOIN customers c ON c.customer_id = o.customer_id
                JOIN staff     s ON s.staff_id     = o.staff_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.ordered_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT o.*,
                    CONCAT(c.CFN,' ',c.CLN) AS customer_name,
                    c.contact_number, c.email AS customer_email,
                    CONCAT(s.SFN,' ',s.SLN) AS staff_name
             FROM online_orders o
             JOIN customers c ON c.customer_id = o.customer_id
             JOIN staff     s ON s.staff_id     = o.staff_id
             WHERE o.order_id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getItems(int $orderId): array {
        $stmt = $this->db->prepare(
            "SELECT i.*, p.product_name, p.size, cat.name AS category
             FROM online_order_items i
             JOIN products p   ON p.product_id = i.product_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             WHERE i.order_id = ?"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function create(int $customerId, int $staffId, string $address, string $paymentMethod, string $notes, array $items): int|false {
        try {
            $this->db->beginTransaction();

            $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $items));
            $paymentStatus = $paymentMethod === 'paid_online' ? 'paid' : 'unpaid';

            $stmt = $this->db->prepare(
                "INSERT INTO online_orders
                    (customer_id, staff_id, delivery_address, payment_method, payment_status, notes, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$customerId, $staffId, $address, $paymentMethod, $paymentStatus, $notes, $total]);
            $orderId = (int)$this->db->lastInsertId();

            $si = $this->db->prepare(
                "INSERT INTO online_order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)"
            );
            foreach ($items as $item) {
                $si->execute([$orderId, $item['product_id'], $item['qty'], $item['price']]);
            }

            $this->db->commit();
            return $orderId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->db->prepare(
            "UPDATE online_orders SET order_status=? WHERE order_id=?"
        )->execute([$status, $id]);
    }

    public function updatePaymentStatus(int $id, string $status): bool {
        return $this->db->prepare(
            "UPDATE online_orders SET payment_status=? WHERE order_id=?"
        )->execute([$status, $id]);
    }

    public function updateOrder(int $id, string $address, string $paymentMethod, string $paymentStatus, string $notes): bool {
        return $this->db->prepare(
            "UPDATE online_orders SET delivery_address=?, payment_method=?, payment_status=?, notes=? WHERE order_id=?"
        )->execute([$address, $paymentMethod, $paymentStatus, $notes, $id]);
    }

    public function countByStatus(): array {
        $rows = $this->db->query(
            "SELECT order_status, COUNT(*) AS cnt FROM online_orders GROUP BY order_status"
        )->fetchAll();
        $out = ['pending'=>0,'confirmed'=>0,'shipped'=>0,'delivered'=>0,'cancelled'=>0];
        foreach ($rows as $r) $out[$r['order_status']] = (int)$r['cnt'];
        return $out;
    }

    public function getTotalRevenue(): float {
        return (float)$this->db->query(
            "SELECT COALESCE(SUM(total_amount),0) FROM online_orders WHERE order_status != 'cancelled'"
        )->fetchColumn();
    }

    public function getForReport(string $from, string $to): array {
        $stmt = $this->db->prepare(
            "SELECT o.*,
                    CONCAT(c.CFN,' ',c.CLN) AS customer_name,
                    CONCAT(s.SFN,' ',s.SLN) AS staff_name
             FROM online_orders o
             JOIN customers c ON c.customer_id = o.customer_id
             JOIN staff     s ON s.staff_id     = o.staff_id
             WHERE DATE(o.ordered_at) BETWEEN ? AND ?
             ORDER BY o.ordered_at DESC"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }
}
