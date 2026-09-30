<?php
/**
 * Auth guard for admin pages.
 * Include this at the very top of every protected admin page/endpoint.
 * Satisfies CAT06: administrative write/read operations reject
 * unauthenticated or unauthorized (non-admin) requests.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function checkAdminAuthorization(array $session): string
{
    $isLoggedIn = isset($session['user_id']);

    if (!$isLoggedIn) {
        return 'unauthenticated';
    }

    if (($session['role'] ?? '') !== 'admin') {
        return 'forbidden';
    }

    return 'ok';
}
function require_admin(): void
{
    $isLoggedIn = isset($_SESSION['user_id']);
    $isAdmin    = $isLoggedIn && ($_SESSION['role'] ?? '') === 'admin';

    if (!$isLoggedIn) {
        // For HTML pages, redirect to login.
        // For JSON API endpoints, this same check is reused (see api/admin/*.php)
        // which responds with a 401 instead of redirecting.
        header('Location: /nestdecor/admin/login.php');
        exit;
    }

    if (!$isAdmin) {
        http_response_code(403);
        die('403 Forbidden: admin access only.');
    }
}

/**
 * Variant used by JSON API endpoints instead of require_admin(),
 * since an API should return a status code, not redirect a browser.
 */
function require_admin_api(): void
{
    $isLoggedIn = isset($_SESSION['user_id']);
    $isAdmin    = $isLoggedIn && ($_SESSION['role'] ?? '') === 'admin';

    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthenticated. Please log in.']);
        exit;
    }

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden. Admin role required.']);
        exit;
    }
}
