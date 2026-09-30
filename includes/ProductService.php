<?php
require_once __DIR__ . '/exceptions.php';
require_once __DIR__ . '/helpers.php';

const VALID_PRODUCT_STATUSES = ['draft', 'published', 'archived'];

function createProduct(PDO $pdo, array $data): array
{
    $name       = trim($data['name'] ?? '');
    $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
    $description = $data['description'] ?? null;
    $occasion   = $data['occasion'] ?? 'General';
    $status     = $data['status'] ?? 'draft';
    $specs      = isset($data['specifications']) ? json_encode($data['specifications']) : null;

    if ($name === '' || !$categoryId) {
        throw new ApiException('"name" and "category_id" are required.', 422);
    }

    if (!in_array($status, VALID_PRODUCT_STATUSES, true)) {
        throw new ApiException('Invalid status. Use draft, published, or archived.', 422);
    }

    $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $catCheck->execute([$categoryId]);
    if (!$catCheck->fetch()) {
        throw new ApiException('category_id does not reference an existing category.', 422);
    }

    $slug = slugify($name);
    $dupe = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
    $dupe->execute([$slug]);
    if ($dupe->fetch()) {
        throw new ApiException("A product with slug '{$slug}' already exists. Choose a different name.", 409);
    }

    $insert = $pdo->prepare('
        INSERT INTO products (category_id, name, slug, description, status, occasion, specifications)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $insert->execute([$categoryId, $name, $slug, $description, $status, $occasion, $specs]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'name' => $name,
        'slug' => $slug,
        'status' => $status,
    ];
}

function updateProduct(PDO $pdo, int $id, array $data): void
{
    $existing = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $existing->execute([$id]);
    if (!$existing->fetch()) {
        throw new ApiException('Product not found.', 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['name']) && trim($data['name']) !== '') {
        $fields[] = 'name = ?';
        $params[] = trim($data['name']);
    }

    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = $data['description'];
    }

    if (isset($data['occasion'])) {
        $fields[] = 'occasion = ?';
        $params[] = $data['occasion'];
    }

    if (isset($data['category_id'])) {
        $fields[] = 'category_id = ?';
        $params[] = (int) $data['category_id'];
    }

    if (isset($data['specifications'])) {
        $fields[] = 'specifications = ?';
        $params[] = json_encode($data['specifications']);
    }

    if (isset($data['status'])) {
        if (!in_array($data['status'], VALID_PRODUCT_STATUSES, true)) {
            throw new ApiException('Invalid status. Use draft, published, or archived.', 422);
        }

        if ($data['status'] === 'published') {
            $skuCheck = $pdo->prepare('
                SELECT s.id FROM skus s
                INNER JOIN variants v ON v.id = s.variant_id
                WHERE v.product_id = ? AND s.is_active = 1
                LIMIT 1
            ');
            $skuCheck->execute([$id]);

            if (!$skuCheck->fetch()) {
                throw new ApiException('Cannot publish a product with no active SKU.', 422);
            }
        }

        $fields[] = 'status = ?';
        $params[] = $data['status'];
    }

    if (empty($fields)) {
        throw new ApiException('No valid fields provided to update.', 422);
    }

    $params[] = $id;
    $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?';

    $pdo->prepare($sql)->execute($params);
}