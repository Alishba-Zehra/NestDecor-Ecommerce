<?php
/**
 * Admin API — Add a SKU to a product
 *
 *   POST /api/v1/admin/products/:id/skus
 *
 * Accepts either an existing variant_id, OR variant_label + option_values
 * to create a brand-new variant on the fly, then attaches a SKU to it.
 * Satisfies CAT03 (unique SKU code, own price/stock) and CAT04 (a variant
 * combination only exists once someone actually creates a SKU for it —
 * we never auto-generate a fake zero-stock SKU for a combination nobody asked for).
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['error' => 'Method not allowed.']);
}

$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;
if (!$productId) {
    json_response(400, ['error' => 'Product id is required in the URL.']);
}

$productCheck = $pdo->prepare('SELECT id FROM products WHERE id = ?');
$productCheck->execute([$productId]);
if (!$productCheck->fetch()) {
    json_response(404, ['error' => 'Product not found.']);
}

$data = json_input();
$skuCode = trim($data['sku_code'] ?? '');
$price = $data['price'] ?? null;
$stock = $data['stock_quantity'] ?? 0;

if ($skuCode === '' || $price === null || (float) $price < 0) {
    json_response(422, ['error' => 'sku_code and a non-negative price are required.']);
}
if ((int) $stock < 0) {
    json_response(422, ['error' => 'stock_quantity cannot be negative.']);
}

$dupeSku = $pdo->prepare('SELECT id FROM skus WHERE sku_code = ?');
$dupeSku->execute([$skuCode]);
if ($dupeSku->fetch()) {
    json_response(409, ['error' => "SKU code '{$skuCode}' already exists. Codes must be unique."]);
}

// Resolve the variant: reuse an existing one, or create it now.
$variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;

if ($variantId) {
    $variantCheck = $pdo->prepare('SELECT id FROM variants WHERE id = ? AND product_id = ?');
    $variantCheck->execute([$variantId, $productId]);
    if (!$variantCheck->fetch()) {
        json_response(422, ['error' => 'variant_id does not belong to this product.']);
    }
} else {
    $variantLabel = trim($data['variant_label'] ?? '');
    $optionValues = $data['option_values'] ?? null;

    if ($variantLabel === '' || $optionValues === null) {
        json_response(422, ['error' => 'Provide either variant_id, or variant_label + option_values to create a new variant.']);
    }

    $dupeVariant = $pdo->prepare('SELECT id FROM variants WHERE product_id = ? AND variant_label = ?');
    $dupeVariant->execute([$productId, $variantLabel]);
    if ($dupeVariant->fetch()) {
        json_response(409, ['error' => "Variant '{$variantLabel}' already exists for this product."]);
    }

    $insertVariant = $pdo->prepare('INSERT INTO variants (product_id, variant_label, option_values) VALUES (?, ?, ?)');
    $insertVariant->execute([$productId, $variantLabel, json_encode($optionValues)]);
    $variantId = (int) $pdo->lastInsertId();
}

$insertSku = $pdo->prepare('
    INSERT INTO skus (variant_id, sku_code, price, stock_quantity, is_active)
    VALUES (?, ?, ?, ?, ?)
');
$insertSku->execute([
    $variantId,
    $skuCode,
    (float) $price,
    (int) $stock,
    isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
]);

json_response(201, [
    'id' => (int) $pdo->lastInsertId(),
    'variant_id' => $variantId,
    'sku_code' => $skuCode,
    'price' => (float) $price,
    'stock_quantity' => (int) $stock,
]);
