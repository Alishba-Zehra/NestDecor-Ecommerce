<?php
/**
 * Admin API — Update a SKU
 *
 *   PATCH /api/v1/admin/skus/:id
 *
 * Updates price, stock_quantity, or is_active. Never allows stock to go
 * negative (also enforced at the database level via a CHECK constraint —
 * this validation just returns a friendlier error before hitting the DB).
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    json_response(405, ['error' => 'Method not allowed.']);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$id) {
    json_response(400, ['error' => 'SKU id is required in the URL.']);
}

$existing = $pdo->prepare('SELECT * FROM skus WHERE id = ?');
$existing->execute([$id]);
if (!$existing->fetch()) {
    json_response(404, ['error' => 'SKU not found.']);
}

$data = json_input();
$fields = [];
$params = [];

if (isset($data['price'])) {
    if ((float) $data['price'] < 0) {
        json_response(422, ['error' => 'price cannot be negative.']);
    }
    $fields[] = 'price = ?';
    $params[] = (float) $data['price'];
}

if (isset($data['stock_quantity'])) {
    if ((int) $data['stock_quantity'] < 0) {
        json_response(422, ['error' => 'stock_quantity cannot be negative.']);
    }
    $fields[] = 'stock_quantity = ?';
    $params[] = (int) $data['stock_quantity'];
}

if (isset($data['is_active'])) {
    $fields[] = 'is_active = ?';
    $params[] = (int) (bool) $data['is_active'];
}

if (empty($fields)) {
    json_response(422, ['error' => 'No valid fields provided to update.']);
}

$params[] = $id;
$sql = 'UPDATE skus SET ' . implode(', ', $fields) . ' WHERE id = ?';
$pdo->prepare($sql)->execute($params);

json_response(200, ['message' => 'SKU updated.']);
