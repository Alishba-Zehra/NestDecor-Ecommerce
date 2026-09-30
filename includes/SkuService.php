<?php
require_once __DIR__ . '/exceptions.php';

/**
 * Adds a SKU to a product — reusing an existing variant, or creating a new
 * one on the fly from variant_label + option_values.
 * Satisfies CAT03 (unique code, own price/stock) and CAT04 (a variant
 * combination is only created when someone actually asks for a sellable SKU).
 */
function addSkuToProduct(PDO $pdo, int $productId, array $data): array
{
    $productCheck = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $productCheck->execute([$productId]);

    if (!$productCheck->fetch()) {
        throw new ApiException('Product not found.', 404);
    }

    $skuCode = trim($data['sku_code'] ?? '');
    $price   = $data['price'] ?? null;
    $stock   = $data['stock_quantity'] ?? 0;

    if ($skuCode === '' || $price === null || (float) $price < 0) {
        throw new ApiException('sku_code and a non-negative price are required.', 422);
    }

    if ((int) $stock < 0) {
        throw new ApiException('stock_quantity cannot be negative.', 422);
    }

    $dupeSku = $pdo->prepare('SELECT id FROM skus WHERE sku_code = ?');
    $dupeSku->execute([$skuCode]);

    if ($dupeSku->fetch()) {
        throw new ApiException("SKU code '{$skuCode}' already exists. Codes must be unique.", 409);
    }

    $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;

    if ($variantId) {
        $variantCheck = $pdo->prepare('SELECT id FROM variants WHERE id = ? AND product_id = ?');
        $variantCheck->execute([$variantId, $productId]);

        if (!$variantCheck->fetch()) {
            throw new ApiException('variant_id does not belong to this product.', 422);
        }
    } else {
        $variantLabel = trim($data['variant_label'] ?? '');
        $optionValues = $data['option_values'] ?? null;

        if ($variantLabel === '' || $optionValues === null) {
            throw new ApiException(
                'Provide either variant_id, or variant_label + option_values to create a new variant.',
                422
            );
        }

        $dupeVariant = $pdo->prepare(
            'SELECT id FROM variants WHERE product_id = ? AND variant_label = ?'
        );
        $dupeVariant->execute([$productId, $variantLabel]);

        if ($dupeVariant->fetch()) {
            throw new ApiException("Variant '{$variantLabel}' already exists for this product.", 409);
        }

        $insertVariant = $pdo->prepare(
            'INSERT INTO variants (product_id, variant_label, option_values) VALUES (?, ?, ?)'
        );
        $insertVariant->execute([
            $productId,
            $variantLabel,
            json_encode($optionValues)
        ]);

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

    return [
        'id' => (int) $pdo->lastInsertId(),
        'variant_id' => $variantId,
        'sku_code' => $skuCode,
        'price' => (float) $price,
        'stock_quantity' => (int) $stock,
    ];
}

/**
 * Updates a SKU's price, stock, or active status.
 * Satisfies CAT05: stock quantity must never become negative.
 */
function updateSku(PDO $pdo, int $id, array $data): void
{
    $existing = $pdo->prepare('SELECT id FROM skus WHERE id = ?');
    $existing->execute([$id]);

    if (!$existing->fetch()) {
        throw new ApiException('SKU not found.', 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['price'])) {
        if ((float) $data['price'] < 0) {
            throw new ApiException('price cannot be negative.', 422);
        }

        $fields[] = 'price = ?';
        $params[] = (float) $data['price'];
    }

    if (isset($data['stock_quantity'])) {
        if ((int) $data['stock_quantity'] < 0) {
            throw new ApiException('stock_quantity cannot be negative.', 422);
        }

        $fields[] = 'stock_quantity = ?';
        $params[] = (int) $data['stock_quantity'];
    }

    if (isset($data['is_active'])) {
        $fields[] = 'is_active = ?';
        $params[] = (int) (bool) $data['is_active'];
    }

    if (empty($fields)) {
        throw new ApiException('No valid fields provided to update.', 422);
    }

    $params[] = $id;

    $sql = 'UPDATE skus SET ' . implode(', ', $fields) . ' WHERE id = ?';

    $pdo->prepare($sql)->execute($params);
}