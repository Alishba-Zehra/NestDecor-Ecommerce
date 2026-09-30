<?php

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['pdo'];
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    protected function makeCategory(string $name = 'Test Category'): int
    {
        $slug = strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, slug, is_active) VALUES (?, ?, 1)'
        );

        $stmt->execute([$name, $slug]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function makeProduct(
        int $categoryId,
        string $name = 'Test Product'
    ): int {
        $slug = strtolower(str_replace(' ', '-', $name)) . '-' . uniqid();

        $stmt = $this->pdo->prepare('
            INSERT INTO products (category_id, name, slug, status, occasion)
            VALUES (?, ?, ?, "draft", "General")
        ');

        $stmt->execute([$categoryId, $name, $slug]);

        return (int) $this->pdo->lastInsertId();
    }
}