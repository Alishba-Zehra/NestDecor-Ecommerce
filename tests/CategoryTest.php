<?php

final class CategoryTest extends TestCase
{
    public function testCreatingACategoryWithAValidNameSucceeds(): void
    {
        $result = createCategory($this->pdo, [
            'name' => 'Wall Decor',
        ]);

        $this->assertSame('Wall Decor', $result['name']);
        $this->assertSame('wall-decor', $result['slug']);
        $this->assertSame(1, $result['is_active']);
    }

    public function testCreatingACategoryWithoutANameFails(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);

        createCategory($this->pdo, []);
    }

    public function testDuplicateSlugIsRejected(): void
    {
        createCategory($this->pdo, [
            'name' => 'Wall Decor',
        ]);

        $this->expectException(ApiException::class);

        createCategory($this->pdo, [
            'name' => 'Wall Decor',
        ]);
    }

    public function testDuplicateSlugReturnsA409ConflictStatus(): void
    {
        createCategory($this->pdo, [
            'name' => 'Wall Decor',
        ]);

        try {
            createCategory($this->pdo, [
                'name' => 'Wall Decor',
            ]);

            $this->fail('Expected duplicate category exception.');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->statusCode);
        }
    }

    public function testCategoryCannotBeMovedUnderANonExistentParent(): void
    {
        $categoryId = $this->makeCategory('Child Category');

        $this->expectException(ApiException::class);

        updateCategory($this->pdo, $categoryId, [
            'parent_id' => 999999,
        ]);
    }

    public function testACategoryCannotBecomeItsOwnAncestor(): void
    {
        $parentId = $this->makeCategory('Parent Category');
        $childId = $this->makeCategory('Child Category');

        updateCategory($this->pdo, $childId, [
            'parent_id' => $parentId,
        ]);

        $this->expectException(ApiException::class);

        updateCategory($this->pdo, $parentId, [
            'parent_id' => $childId,
        ]);
    }

    public function testACategoryCanBeDeactivated(): void
    {
        $categoryId = $this->makeCategory('Decor Category');

        updateCategory($this->pdo, $categoryId, [
            'is_active' => false,
        ]);

        $stmt = $this->pdo->prepare(
            'SELECT is_active FROM categories WHERE id = ?'
        );
        $stmt->execute([$categoryId]);

        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}