-- =====================================================================
-- NestDecor: Sprint 2 Database Schema
-- Extends the Sprint 1 ERD (Users, Cart, Cart_Items, Orders, Order_Items)
-- with the Sprint 2 catalog foundation (Categories, Products, Variants, SKUs, Assets)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS nestdecor;
USE nestdecor;

-- ---------------------------------------------------------------------
-- 1. USERS (from Sprint 1, extended with a role for admin authentication)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    address         VARCHAR(255) NULL,
    role            ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- 2. CATEGORIES (new in Sprint 2 — self-referencing tree)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT NULL,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(120) NOT NULL UNIQUE,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_category_parent
        FOREIGN KEY (parent_id) REFERENCES categories(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 3. PRODUCTS (new in Sprint 2 — replaces the flat Sprint 1 products idea)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT NOT NULL,
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(160) NOT NULL UNIQUE,
    description     TEXT NULL,
    status          ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    occasion        ENUM('Home', 'Wedding', 'Engagement', 'Birthday', 'General') NOT NULL DEFAULT 'General',
    specifications  JSON NULL COMMENT 'Validated key-value product attributes, e.g. {"material":"Wood","color_family":"Neutral"}',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 4. VARIANTS (new in Sprint 2 — a specific option combination of a product)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS variants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    variant_label   VARCHAR(150) NOT NULL COMMENT 'Human-readable combination, e.g. "Color: Gold"',
    option_values   JSON NOT NULL COMMENT 'Structured options, e.g. {"color":"Gold"}',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_variant_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT uq_variant_per_product UNIQUE (product_id, variant_label)
);

-- ---------------------------------------------------------------------
-- 5. SKUS (new in Sprint 2 — the actual sellable unit, price + stock live here)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS skus (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    variant_id      INT NOT NULL,
    sku_code        VARCHAR(50) NOT NULL UNIQUE,
    price           DECIMAL(10,2) NOT NULL,
    stock_quantity  INT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sku_variant
        FOREIGN KEY (variant_id) REFERENCES variants(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT chk_sku_price_non_negative CHECK (price >= 0),
    CONSTRAINT chk_sku_stock_non_negative CHECK (stock_quantity >= 0)
);

-- ---------------------------------------------------------------------
-- 6. ASSETS (new in Sprint 2 — images tied to a product or a specific variant)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS assets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NULL,
    variant_id      INT NULL,
    storage_url     VARCHAR(255) NOT NULL,
    role            ENUM('primary', 'gallery', 'thumbnail') NOT NULL DEFAULT 'gallery',
    alt_text        VARCHAR(150) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_asset_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_asset_variant
        FOREIGN KEY (variant_id) REFERENCES variants(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT chk_asset_owner CHECK (product_id IS NOT NULL OR variant_id IS NOT NULL)
);

-- ---------------------------------------------------------------------
-- 7. CART & CART_ITEMS (from Sprint 1 — cart still selects at PRODUCT level;
--    the shopper picks a specific SKU only at checkout, see Sprint 2 ERD note)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS cart_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    cart_id         INT NOT NULL,
    product_id      INT NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,

    CONSTRAINT fk_cart_item_cart
        FOREIGN KEY (cart_id) REFERENCES cart(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_cart_item_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ---------------------------------------------------------------------
-- 8. ORDERS & ORDER_ITEMS (from Sprint 1 — order_items now sell a SKU,
--    since that is the exact priced/stocked unit that was actually bought)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    total_amount        DECIMAL(10,2) NOT NULL,
    delivery_address    VARCHAR(255) NOT NULL,
    event_date          DATE NULL,
    status              ENUM('Pending', 'Meetup Scheduled', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS order_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    sku_id          INT NOT NULL,
    quantity        INT NOT NULL,
    unit_price      DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_order_item_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_order_item_sku
        FOREIGN KEY (sku_id) REFERENCES skus(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);
