-- ============================================================
--  Company X Merch POS — Database Schema
--  Updated: SFN/SLN for Staff, CFN/CLN for Customers
-- ============================================================

CREATE DATABASE IF NOT EXISTS companyx_pos
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE companyx_pos;

-- Staff / Users
CREATE TABLE IF NOT EXISTS staff (
  staff_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  SFN         VARCHAR(100)  NOT NULL,
  SLN         VARCHAR(100)  NOT NULL,
  email       VARCHAR(150)  NOT NULL UNIQUE,
  password    VARCHAR(255)  NOT NULL,
  role        ENUM('admin','employee') NOT NULL DEFAULT 'employee',
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Customers
CREATE TABLE IF NOT EXISTS customers (
  customer_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  CFN            VARCHAR(100) NOT NULL,
  CLN            VARCHAR(100) NOT NULL,
  contact_number VARCHAR(20),
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(80) NOT NULL UNIQUE
);

-- Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
  supplier_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  contact     VARCHAR(80),
  email       VARCHAR(150),
  phone       VARCHAR(30),
  address     TEXT,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE IF NOT EXISTS products (
  product_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_name  VARCHAR(150) NOT NULL,
  category_id   INT UNSIGNED,
  size          VARCHAR(20)  NOT NULL DEFAULT 'One Size',
  price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cost_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  reorder_lvl   INT          NOT NULL DEFAULT 5,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Stock
CREATE TABLE IF NOT EXISTS stock (
  stock_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL UNIQUE,
  quantity   INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Purchases
CREATE TABLE IF NOT EXISTS purchases (
  purchase_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id      INT UNSIGNED NOT NULL,
  customer_id   INT UNSIGNED,
  total_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  purchase_date DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id)    REFERENCES staff(staff_id),
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
  payment_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id    INT UNSIGNED NOT NULL UNIQUE,
  payment_method ENUM('cash','digital') NOT NULL DEFAULT 'cash',
  amount_paid    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  change_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (purchase_id) REFERENCES purchases(purchase_id) ON DELETE CASCADE
);

-- Purchase Items
CREATE TABLE IF NOT EXISTS purchase_items (
  purchase_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id      INT UNSIGNED NOT NULL,
  product_id       INT UNSIGNED NOT NULL,
  quantity         INT          NOT NULL DEFAULT 1,
  price            DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (purchase_id) REFERENCES purchases(purchase_id) ON DELETE CASCADE,
  FOREIGN KEY (product_id)  REFERENCES products(product_id)  ON DELETE CASCADE
);

-- Stock In
CREATE TABLE IF NOT EXISTS stock_in (
  stockin_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id   INT UNSIGNED,
  staff_id      INT UNSIGNED NOT NULL,
  notes         TEXT,
  date_received DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL,
  FOREIGN KEY (staff_id)    REFERENCES staff(staff_id)
);

-- Stock In Items
CREATE TABLE IF NOT EXISTS stock_in_items (
  stockin_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stockin_id      INT UNSIGNED NOT NULL,
  product_id      INT UNSIGNED NOT NULL,
  quantity        INT          NOT NULL DEFAULT 1,
  cost_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (stockin_id)  REFERENCES stock_in(stockin_id)  ON DELETE CASCADE,
  FOREIGN KEY (product_id)  REFERENCES products(product_id)  ON DELETE CASCADE
);

DELIMITER $$

CREATE TRIGGER reduce_stock_after_purchase
AFTER INSERT ON purchase_items
FOR EACH ROW
BEGIN
  UPDATE stock SET quantity = quantity - NEW.quantity
  WHERE product_id = NEW.product_id;
END$$

CREATE TRIGGER compute_total_purchase
AFTER INSERT ON purchase_items
FOR EACH ROW
BEGIN
  UPDATE purchases
  SET total_amount = (
    SELECT SUM(quantity * price) FROM purchase_items
    WHERE purchase_id = NEW.purchase_id
  )
  WHERE purchase_id = NEW.purchase_id;
END$$

CREATE TRIGGER add_stock_after_stockin
AFTER INSERT ON stock_in_items
FOR EACH ROW
BEGIN
  UPDATE stock SET quantity = quantity + NEW.quantity
  WHERE product_id = NEW.product_id;
END$$

CREATE TRIGGER restore_stock_on_void
AFTER DELETE ON purchase_items
FOR EACH ROW
BEGIN
  UPDATE stock SET quantity = quantity + OLD.quantity
  WHERE product_id = OLD.product_id;
END$$

DELIMITER ;

CREATE OR REPLACE VIEW sales_report AS
SELECT p.purchase_id,
  COALESCE(CONCAT(c.CFN,' ',c.CLN),'Walk-in Customer') AS customer_full_name,
  c.CFN AS customer_first_name, c.CLN AS customer_last_name,
  CONCAT(s.SFN,' ',s.SLN) AS staff_full_name,
  s.SFN AS staff_first_name, s.SLN AS staff_last_name,
  p.purchase_date, p.total_amount,
  pay.payment_method, pay.amount_paid, pay.change_amount
FROM purchases p
LEFT JOIN customers c  ON c.customer_id  = p.customer_id
JOIN      staff     s  ON s.staff_id     = p.staff_id
LEFT JOIN payments  pay ON pay.purchase_id = p.purchase_id;

CREATE OR REPLACE VIEW product_stock_view AS
SELECT pr.product_id, pr.product_name, c.name AS category,
  pr.size, pr.cost_price, pr.price, st.quantity
FROM products pr
LEFT JOIN categories c ON c.id = pr.category_id
JOIN stock st ON st.product_id = pr.product_id;

CREATE OR REPLACE VIEW low_stock_view AS
SELECT pr.product_id, pr.product_name, c.name AS category,
  pr.size, st.quantity, pr.reorder_lvl
FROM products pr
LEFT JOIN categories c ON c.id = pr.category_id
JOIN stock st ON st.product_id = pr.product_id
WHERE st.quantity <= pr.reorder_lvl;

CREATE OR REPLACE VIEW purchase_details_view AS
SELECT pi.purchase_id, pr.product_name, c.name AS category,
  pr.size, pi.quantity, pi.price,
  (pi.quantity * pi.price) AS subtotal, p.purchase_date
FROM purchase_items pi
JOIN products pr ON pr.product_id = pi.product_id
LEFT JOIN categories c ON c.id = pr.category_id
JOIN purchases p ON p.purchase_id = pi.purchase_id;

-- Seed Data
INSERT INTO staff (SFN, SLN, email, password, role) VALUES
('Admin',  'User',   'admin@companyx.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Seth',   'Decena', 'seth@companyx.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee'),
('Denzel', 'Sinio',  'denzel@companyx.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee');

INSERT INTO customers (CFN, CLN, contact_number) VALUES
('Juan','Dela Cruz','09171234567'),
('Maria','Santos','09281234567'),
('Jose','Reyes','09391234567');

INSERT INTO categories (name) VALUES ('Shirts'),('Belt Bags'),('Hats'),('Caps');

INSERT INTO suppliers (name, contact, email, phone, address) VALUES
('Textile Suppliers Inc.','Mark Tan','mark@textilesup.com','09171111111','Davao City'),
('Fashion Imports Ltd.','Lisa Go','lisa@fashionimp.com','09282222222','Cebu City'),
('Accessories Wholesale','Carlos Lim','carlos@accwhole.com','09393333333','Manila');

INSERT INTO products (product_name, category_id, size, price, cost_price, reorder_lvl) VALUES
('Classic T-Shirt',1,'S',25.99,12.00,8),
('Classic T-Shirt',1,'M',25.99,12.00,8),
('Classic T-Shirt',1,'L',25.99,12.00,8),
('Premium Polo Shirt',1,'M',39.99,18.00,5),
('Premium Polo Shirt',1,'L',39.99,18.00,5),
('Crossbody Belt Bag',2,'One Size',29.99,14.00,5),
('Sport Belt Bag',2,'One Size',34.99,16.00,5),
('Leather Belt Bag',2,'One Size',49.99,22.00,5),
('Baseball Cap',3,'One Size',19.99,8.00,5),
('Snapback Hat',3,'One Size',24.99,10.00,5),
('Bucket Hat',3,'S/M',22.99,9.00,5),
('Bucket Hat',3,'L/XL',22.99,9.00,5);

INSERT INTO stock (product_id, quantity)
SELECT product_id,
  CASE
    WHEN product_name='Classic T-Shirt' AND size='S'  THEN 45
    WHEN product_name='Classic T-Shirt' AND size='M'  THEN 8
    WHEN product_name='Classic T-Shirt' AND size='L'  THEN 143
    WHEN product_name='Premium Polo Shirt' AND size='M' THEN 20
    WHEN product_name='Premium Polo Shirt' AND size='L' THEN 15
    WHEN product_name='Crossbody Belt Bag' THEN 5
    WHEN product_name='Sport Belt Bag'     THEN 18
    WHEN product_name='Leather Belt Bag'   THEN 12
    WHEN product_name='Baseball Cap'       THEN 3
    WHEN product_name='Snapback Hat'       THEN 25
    WHEN product_name='Bucket Hat' AND size='S/M'  THEN 15
    WHEN product_name='Bucket Hat' AND size='L/XL' THEN 18
    ELSE 10
  END
FROM products;

-- Daily sales summary view (used by Sale::getDaily)
CREATE OR REPLACE VIEW sales_report_daily AS
SELECT
  DATE(p.purchase_date)          AS sale_date,
  COUNT(DISTINCT p.purchase_id)  AS total_transactions,
  SUM(pi.quantity)               AS total_items_sold,
  SUM(p.total_amount)            AS total_revenue,
  CONCAT(st.SFN,' ',st.SLN)     AS cashier
FROM purchases p
JOIN purchase_items pi ON pi.purchase_id = p.purchase_id
JOIN staff st ON st.staff_id = p.staff_id
GROUP BY DATE(p.purchase_date), p.staff_id;

-- ============================================================
--  Archive System — Migration
-- ============================================================

-- Add archived_at column to products, suppliers, staff
ALTER TABLE products  ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL;
ALTER TABLE staff     ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL;

-- Archive log table (optional audit trail)
CREATE TABLE IF NOT EXISTS archive_log (
  log_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('product','supplier','staff') NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  entity_name VARCHAR(200) NOT NULL,
  archived_by INT UNSIGNED,
  archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (archived_by) REFERENCES staff(staff_id) ON DELETE SET NULL
);

-- ============================================================
--  Online Orders — Migration
-- ============================================================

-- Extend customers with delivery address
ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS email   VARCHAR(150) DEFAULT NULL;

-- Online orders table
CREATE TABLE IF NOT EXISTS online_orders (
  order_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id     INT UNSIGNED NOT NULL,
  staff_id        INT UNSIGNED NOT NULL COMMENT 'Staff who entered the order',
  order_status    ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  payment_method  ENUM('cod','paid_online') NOT NULL DEFAULT 'cod',
  payment_status  ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  delivery_address TEXT NOT NULL,
  notes           TEXT,
  total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ordered_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  FOREIGN KEY (staff_id)    REFERENCES staff(staff_id)
);

-- Online order items (mirrors purchase_items pattern)
CREATE TABLE IF NOT EXISTS online_order_items (
  item_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity   INT          NOT NULL DEFAULT 1,
  price      DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES online_orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id)
);
