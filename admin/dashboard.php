<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_admin(); // blocks anyone not logged in as admin
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard — NestDecor</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark px-3">
        <span class="navbar-brand mb-0 h1">NestDecor Admin</span>
        <div class="d-flex align-items-center">
            <span class="text-white me-3">Hi, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Log Out</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h4>Dashboard</h4>
        <p class="text-muted">You are logged in as an administrator. Catalog management links will go here in the next step (Categories, Products, Variants, SKUs).</p>

        <div class="row g-3 mt-2">
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <strong>Categories</strong>
                    <span class="text-muted small">Coming next step</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <strong>Products</strong>
                    <span class="text-muted small">Coming next step</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <strong>Variants & SKUs</strong>
                    <span class="text-muted small">Coming next step</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
