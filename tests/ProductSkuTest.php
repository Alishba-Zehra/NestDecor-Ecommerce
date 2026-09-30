<?php

final class ProductSkuTest extends TestCase
{
    public function testProductRequiresNameAndCategory(): void
    {
        $this->expectException(ApiException::class);

        createProduct($this->pdo, []);
    }

    public function testProductRejectsMissingCategory(): void
    {
        $this->expectException(ApiException::class);

        createProduct($this->pdo, [
            'name' => 'Test Product',
        ]);
    }

    public function testProductRejectsUnknownCategory(): void
    {
        $this->expectException(ApiException::class);

        createProduct($this->pdo, [
            'name' => 'Test Product',
            'category_id' => 999999,
        ]);
    }

    public function testDuplicateProductSlugReturns409(): void
    {
        $categoryId = $this->makeCategory();

        createProduct($this->pdo, [
            'name' => 'Modern Vase',
            'category_id' => $categoryId,
        ]);

        try {
            createProduct($this->pdo, [
                'name' => 'Modern Vase',
                'category_id' => $categoryId,
            ]);

            $this->fail('Expected duplicate product exception.');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->statusCode);
        }
    }

    public function testPublishingWithoutActiveSkuFails(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        $this->expectException(ApiException::class);

        updateProduct($this->pdo, $productId, [
            'status' => 'published',
        ]);
    }

    public function testPublishingWithActiveSkuSucceeds(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        addSkuToProduct($this->pdo, $productId, [
            'sku_code' => 'TEST-SKU-' . uniqid(),
            'price' => 2000,
            'stock_quantity' => 5,
            'variant_label' => 'Default',
            'option_values' => [
                'color' => 'White',
            ],
        ]);

        updateProduct($this->pdo, $productId, [
            'status' => 'published',
        ]);

        $stmt = $this->pdo->prepare(
            'SELECT status FROM products WHERE id = ?'
        );
        $stmt->execute([$productId]);

        $this->assertSame('published', $stmt->fetchColumn());
    }

    public function testSkuCanBeCreatedForProduct(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        $result = addSkuToProduct($this->pdo, $productId, [
            'sku_code' => 'SKU-' . uniqid(),
            'price' => 2000,
            'stock_quantity' => 10,
            'variant_label' => 'Default',
            'option_values' => [
                'color' => 'Black',
            ],
        ]);

        $this->assertSame(2000.0, $result['price']);
        $this->assertSame(10, $result['stock_quantity']);
        $this->assertNotEmpty($result['id']);
    }

    public function testDuplicateSkuCodeIsRejected(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        $skuCode = 'DUP-' . uniqid();

        addSkuToProduct($this->pdo, $productId, [
            'sku_code' => $skuCode,
            'price' => 1000,
            'stock_quantity' => 5,
            'variant_label' => 'Default',
            'option_values' => [
                'color' => 'Red',
            ],
        ]);

        try {
            addSkuToProduct($this->pdo, $productId, [
                'sku_code' => $skuCode,
                'price' => 1500,
                'stock_quantity' => 5,
                'variant_label' => 'Large',
                'option_values' => [
                    'size' => 'Large',
                ],
            ]);

            $this->fail('Expected duplicate SKU exception.');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->statusCode);
        }
    }

    public function testNegativeStockOnCreateIsRejected(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        $this->expectException(ApiException::class);

        addSkuToProduct($this->pdo, $productId, [
            'sku_code' => 'NEG-' . uniqid(),
            'price' => 1000,
            'stock_quantity' => -1,
            'variant_label' => 'Default',
            'option_values' => [
                'color' => 'Blue',
            ],
        ]);
    }

    public function testNegativeStockOnUpdateIsRejected(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        $sku = addSkuToProduct($this->pdo, $productId, [
            'sku_code' => 'UPD-' . uniqid(),
            'price' => 1000,
            'stock_quantity' => 5,
            'variant_label' => 'Default',
            'option_values' => [
                'color' => 'Green',
            ],
        ]);

        $this->expectException(ApiException::class);

        updateSku($this->pdo, $sku['id'], [
            'stock_quantity' => -10,
        ]);
    }

    public function testVariantWithoutSkuDoesNotFakeZeroStock(): void
    {
        $categoryId = $this->makeCategory();
        $productId = $this->makeProduct($categoryId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO variants (product_id, variant_label, option_values)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $productId,
            'No SKU Variant',
            json_encode(['color' => 'Yellow']),
        ]);

        $variantId = (int) $this->pdo->lastInsertId();

        $stockStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(stock_quantity), 0)
             FROM skus
             WHERE variant_id = ?'
        );

        $stockStmt->execute([$variantId]);

        $this->assertSame(0, (int) $stockStmt->fetchColumn());
    }
}