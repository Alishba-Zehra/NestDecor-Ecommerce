<?php
/**
 * Admin API — Products
 *
 *   GET    /api/v1/admin/products      -> list all products (admin view, includes drafts)
 *   GET    /api/v1/admin/products/:id  -> single product with its variants + SKUs
 *   POST   /api/v1/admin/products      -> create a draft product
 *   PATCH  /api/v1/admin/products/:id  -> update content or status
 *
 * Satisfies CAT02: unique slug, category assignment, editable status/content.
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_admin_api();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$validStatuses = ['draft', 'published', 'archived'];

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $product = $stmt->fetch();

            if (!$product) {
                json_response(404, ['error' => 'Product not found.']);
            }

            // Attach variants, and each variant's SKUs, so an admin can see
            // the full picture (including a variant with no SKU yet).
            $variantsStmt = $pdo->prepare('SELECT * FROM variants WHERE product_id = ?');
            $variantsStmt->execute([$id]);
            $variants = $variantsStmt->fetchAll();

            foreach ($variants as &$variant) {
                $skuStmt = $pdo->prepare('SELECT * FROM skus WHERE variant_id = ?');
                $skuStmt->execute([$variant['id']]);
                $variant['skus'] = $skuStmt->fetchAll();
            }

            $product['variants'] = $variants;
            json_response(200, $product);
        }

        $stmt = $pdo->query('SELECT * FROM products ORDER BY created_at DESC');
        json_response(200, $stmt->fetchAll());
        break;

    case 'POST':
        $data = json_input();
        $name       = trim($data['name'] ?? '');
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $description = $data['description'] ?? null;
        $occasion   = $data['occasion'] ?? 'General';
        $status     = $data['status'] ?? 'draft';
        $specs      = isset($data['specifications']) ? json_encode($data['specifications']) : null;

        if ($name === '' || !$categoryId) {
            json_response(422, ['error' => '"name" and "category_id" are required.']);
        }

        if (!in_array($status, $validStatuses, true)) {
            json_response(422, ['error' => 'Invalid status. Use draft, published, or archived.']);
        }

        $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $catCheck->execute([$categoryId]);
        if (!$catCheck->fetch()) {
            json_response(422, ['error' => 'category_id does not reference an existing category.']);
        }

        $slug = slugify($name);
        $dupe = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
        $dupe->execute([$slug]);
        if ($dupe->fetch()) {
            json_response(409, ['error' => "A product with slug '{$slug}' already exists. Choose a different name."]);
        }

        $insert = $pdo->prepare('
            INSERT INTO products (category_id, name, slug, description, status, occasion, specifications)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([$categoryId, $name, $slug, $description, $status, $occasion, $specs]);

        json_response(201, [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
        ]);
        break;

    case 'PATCH':
        if (!$id) {
            json_response(400, ['error' => 'Product id is required in the URL.']);
        }

        $existing = $pdo->prepare('SELECT id FROM products WHERE id = ?');
        $existing->execute([$id]);
        if (!$existing->fetch()) {
            json_response(404, ['error' => 'Product not found.']);
        }

        $data = json_input();
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
            if (!in_array($data['status'], $validStatuses, true)) {
                json_response(422, ['error' => 'Invalid status. Use draft, published, or archived.']);
            }

            // Business rule: a product cannot be published if it has no
            // active sellable SKU (see docs/SPRINT_2.md Q1).
            if ($data['status'] === 'published') {
                $skuCheck = $pdo->prepare('
                    SELECT s.id FROM skus s
                    INNER JOIN variants v ON v.id = s.variant_id
                    WHERE v.product_id = ? AND s.is_active = 1
                    LIMIT 1
                ');
                $skuCheck->execute([$id]);
                if (!$skuCheck->fetch()) {
                    json_response(422, ['error' => 'Cannot publish a product with no active SKU.']);
                }
            }

            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }

        if (empty($fields)) {
            json_response(422, ['error' => 'No valid fields provided to update.']);
        }

        $params[] = $id;
        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute($params);

        json_response(200, ['message' => 'Product updated.']);
        break;

    default:
        json_response(405, ['error' => 'Method not allowed.']);
}
