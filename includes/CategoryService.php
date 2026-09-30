<?php
require_once __DIR__ . '/exceptions.php';
require_once __DIR__ . '/helpers.php';

function createCategory(PDO $pdo, array $data): array
{
    $name = trim($data['name'] ?? '');
    $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;

    if ($name === '') {
        throw new ApiException('The "name" field is required.', 422);
    }

    if ($parentId !== null) {
        $check = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $check->execute([$parentId]);
        if (!$check->fetch()) {
            throw new ApiException('parent_id does not reference an existing category.', 422);
        }
    }

    $slug = slugify($name);
    $dupe = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $dupe->execute([$slug]);
    if ($dupe->fetch()) {
        throw new ApiException("A category with slug '{$slug}' already exists.", 409);
    }

    $insert = $pdo->prepare('INSERT INTO categories (parent_id, name, slug, is_active) VALUES (?, ?, ?, 1)');
    $insert->execute([$parentId, $name, $slug]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'parent_id' => $parentId,
        'name' => $name,
        'slug' => $slug,
        'is_active' => 1,
    ];
}

function updateCategory(PDO $pdo, int $id, array $data): void
{
    $existing = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $existing->execute([$id]);
    if (!$existing->fetch()) {
        throw new ApiException('Category not found.', 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['name']) && trim($data['name']) !== '') {
        $fields[] = 'name = ?';
        $params[] = trim($data['name']);
    }

    if (array_key_exists('parent_id', $data)) {
        $newParentId = $data['parent_id'] !== null ? (int) $data['parent_id'] : null;

        if ($newParentId !== null) {
    $parentCheck = $pdo->prepare(
        'SELECT id FROM categories WHERE id = ?'
    );
    $parentCheck->execute([$newParentId]);

    if (!$parentCheck->fetch()) {
        throw new ApiException(
            'parent_id does not reference an existing category.',
            422
        );
    }

    if (wouldCreateCategoryCycle($pdo, $id, $newParentId)) {
        throw new ApiException(
            'A category cannot become its own ancestor.',
            422
        );
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
        throw new ApiException('No valid fields provided to update.', 422);
    }

    $params[] = $id;
    $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $pdo->prepare($sql)->execute($params);
}