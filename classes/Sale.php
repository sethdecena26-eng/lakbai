<?php
// classes/Sale.php  — maps to purchases / purchase_items / payments

class Sale {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * $items = [ ['product_id'=>1,'qty'=>2,'price'=>25.99], ... ]
     */
    public function create(int $staffId, float $total, float $payment, string $method, array $items, ?int $customerId = null): int|false {
        try {
            $this->db->beginTransaction();

            $s = $this->db->prepare(
                'INSERT INTO purchases (staff_id, customer_id, total_amount) VALUES (?, ?, ?)'
            );
            $s->execute([$staffId, $customerId, $total]);
            $purchaseId = (int)$this->db->lastInsertId();

            $si = $this->db->prepare(
                'INSERT INTO purchase_items (purchase_id, product_id, quantity, price) VALUES (?,?,?,?)'
            );
            foreach ($items as $item) {
                $si->execute([$purchaseId, $item['product_id'], $item['qty'], $item['price']]);
            }

            // Save payment
            $this->db->prepare(
                'INSERT INTO payments (purchase_id, payment_method, amount_paid, change_amount)
                 VALUES (?,?,?,?)'
            )->execute([$purchaseId, $method, $payment, $payment - $total]);

            $this->db->commit();
            return $purchaseId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getTodayTotal(): float {
        return (float)$this->db->query(
            "SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE DATE(purchase_date)=CURDATE()"
        )->fetchColumn();
    }

    public function getTodayCount(): int {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM purchases WHERE DATE(purchase_date)=CURDATE()"
        )->fetchColumn();
    }

    public function getRecent(int $limit = 10): array {
        $s = $this->db->prepare(
            'SELECT p.purchase_id, p.total_amount, p.purchase_date,
                    CONCAT(st.SFN," ",st.SLN) AS cashier,
                    pay.payment_method, pay.amount_paid, pay.change_amount
             FROM purchases p
             JOIN staff st ON st.staff_id = p.staff_id
             LEFT JOIN payments pay ON pay.purchase_id = p.purchase_id
             ORDER BY p.purchase_date DESC LIMIT ?'
        );
        $s->execute([$limit]);
        return $s->fetchAll();
    }

    public function getById(int $id): array|false {
        $s = $this->db->prepare(
            'SELECT p.*, CONCAT(st.SFN," ",st.SLN) AS cashier,
                    pay.payment_method, pay.amount_paid, pay.change_amount
             FROM purchases p
             JOIN staff st ON st.staff_id=p.staff_id
             LEFT JOIN payments pay ON pay.purchase_id=p.purchase_id
             WHERE p.purchase_id=?'
        );
        $s->execute([$id]);
        return $s->fetch();
    }

    public function getItemsByPurchaseId(int $purchaseId): array {
        $s = $this->db->prepare(
            'SELECT pi.*, pr.product_name, pr.size, c.name AS category
             FROM purchase_items pi
             JOIN products pr ON pr.product_id=pi.product_id
             LEFT JOIN categories c ON c.id=pr.category_id
             WHERE pi.purchase_id=?'
        );
        $s->execute([$purchaseId]);
        return $s->fetchAll();
    }

    public function getDaily(string $from, string $to): array {
        $s = $this->db->prepare(
            "SELECT sale_date, total_transactions, total_items_sold, total_revenue, cashier
             FROM sales_report_daily
             WHERE sale_date BETWEEN ? AND ?
             ORDER BY sale_date DESC"
        );
        $s->execute([$from, $to]);
        return $s->fetchAll();
    }

    public function getAllForReport(string $date): array {
        $s = $this->db->prepare(
            "SELECT p.purchase_id, p.total_amount, p.purchase_date,
                    CONCAT(st.SFN,' ',st.SLN) AS cashier,
                    pay.payment_method, pay.amount_paid, pay.change_amount
             FROM purchases p
             JOIN staff st ON st.staff_id=p.staff_id
             LEFT JOIN payments pay ON pay.purchase_id=p.purchase_id
             WHERE DATE(p.purchase_date)=?
             ORDER BY p.purchase_date DESC"
        );
        $s->execute([$date]);
        return $s->fetchAll();
    }
}
