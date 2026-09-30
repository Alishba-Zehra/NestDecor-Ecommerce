<?php
/**
 * Admin API — Categories
 *
 *   GET    /api/v1/admin/categories        -> list the full category tree
 *   GET    /api/v1/admin/categories/:id    -> single category
 *   POST   /api/v1/admin/categories        -> create a category
 *   PATCH  /api/v1/admin/categories/:id    -> update (rename, move, or deactivate)
 *
 * Satisfies CAT01: unique slug, optional parent, cannot become its own ancestor.
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_admin_api(); // CAT06: reject unauthenticated / non-admin requests

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch();

            if (!$category) {
                json_response(404, ['error' => 'Category not found.']);
            }
            json_response(200, $category);
        }

        // Full tree, ordered so parents are easy to group with their children.
        $stmt = $pdo->query('SELECT * FROM categories ORDER BY parent_id IS NULL DESC, parent_id, name');
        json_response(200, $stmt->fetchAll());
        break;

    case 'POST':
        $data = json_input();
        $name = trim($data['name'] ?? '');
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;

        if ($name === '') {
            json_response(422, ['error' => 'The "name" field is required.']);
        }

        if ($parentId !== null) {
            $check = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
            $check->execute([$parentId]);
            if (!$check->fetch()) {
                json_response(422, ['error' => 'parent_id does not reference an existing category.']);
            }
        }

        $slug = slugify($name);
        $dupe = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
        $dupe->execute([$slug]);
        if ($dupe->fetch()) {
            json_response(409, ['error' => "A category with slug '{$slug}' already exists."]);
        }

        $insert = $pdo->prepare('INSERT INTO categories (parent_id, name, slug, is_active) VALUES (?, ?, ?, 1)');
        $insert->execute([$parentId, $name, $slug]);

        json_response(201, [
            'id' => (int) $pdo->lastInsertId(),
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => 1,
        ]);
        break;

    case 'PATCH':
        if (!$id) {
            json_response(400, ['error' => 'Category id is required in the URL.']);
        }

        $existing = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $existing->execute([$id]);
        $category = $existing->fetch();
        if (!$category) {
            json_response(404, ['error' => 'Category not found.']);
        }

        $data = json_input();
        $fields = [];
        $params = [];

        if (isset($data['name']) && trim($data['name']) !== '') {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }

        if (array_key_exists('parent_id', $data)) {
            $newParentId = $data['parent_id'] !== null ? (int) $data['parent_id'] : null;

            if ($newParentId !== null) {
                if (wouldCreateCategoryCycle($pdo, $id, $newParentId)) {
                    json_response(422, ['error' => 'A category cannot become its own ancestor.']);
                }
            }
            $fields[] = 'parent_id = ?';
            $params[] = $newParentId;
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            json_response(422, ['error' => 'No valid fields provided to update.']);
        }

        $params[] = $id;
        $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute($params);

        json_response(200, ['message' => 'Category updated.']);
        break;

    default:
        json_response(405, ['error' => 'Method not allowed.']);
}
