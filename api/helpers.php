<?php
/**
 * Shared helpers for the admin API endpoints.
 */

/**
 * Turns "Floral Stage Backdrop" into "floral-stage-backdrop".
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Reads and decodes a JSON request body (used for POST/PATCH endpoints
 * since this API accepts application/json rather than form-encoded data).
 */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Sends a JSON response with the given HTTP status code and stops execution.
 */
function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Returns true if $candidateParentId is $categoryId itself, or one of its
 * descendants — used to block a category from becoming its own ancestor.
 */
function wouldCreateCategoryCycle(PDO $pdo, int $categoryId, int $candidateParentId): bool
{
    if ($categoryId === $candidateParentId) {
        return true;
    }

    // Recursive CTE: get every descendant of $categoryId.
    $stmt = $pdo->prepare('
        WITH RECURSIVE descendants AS (
            SELECT id FROM categories WHERE parent_id = :root
            UNION ALL
            SELECT c.id FROM categories c
            INNER JOIN descendants d ON c.parent_id = d.id
        )
        SELECT id FROM descendants WHERE id = :candidate
    ');
    $stmt->execute(['root' => $categoryId, 'candidate' => $candidateParentId]);

    return (bool) $stmt->fetch();
}
