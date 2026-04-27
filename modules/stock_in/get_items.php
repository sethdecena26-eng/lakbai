<?php
require_once __DIR__ . '/../../config/app.php';
requireLogin();
header('Content-Type: application/json');
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode([]); exit; }
echo json_encode((new StockIn())->getItemsByStockInId($id));
