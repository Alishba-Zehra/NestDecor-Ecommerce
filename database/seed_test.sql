-- =====================================================================
-- NestDecor: Sprint 2 Seed Data
-- Run this AFTER schema.sql. Provides demo data for the catalog +
-- one admin account and one customer account for testing login.
-- =====================================================================

USE nestdecor_test;

-- ---------------------------------------------------------------------
-- Test accounts
-- Admin login  -> email: admin@nestdecor.com   | password: admin123
-- Customer login -> email: sara@nestdecor.com  | password: customer123
-- (Passwords are bcrypt-hashed below — never store plain text passwords.)
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, address, role) VALUES
('Admin User', 'admin@nestdecor.com', '$2b$12$1S0/j5cIjMKMTzvLXsUGmuZ9deKq8RMkpT3p2yMPFrK5Qw1piFthG', NULL, 'admin'),
('Sara Ahmed', 'sara@nestdecor.com', '$2b$12$AH3ktDhpgZwvXYk5MX3MROPuQQoN4f2OslmsdRDvlo04V1wI8Y9cG', 'Hyderabad, Sindh', 'customer');

-- ---------------------------------------------------------------------
-- Categories (2 levels: a parent category and a child category)
-- ---------------------------------------------------------------------
INSERT INTO categories (parent_id, name, slug, is_active) VALUES
(NULL, 'Wedding & Engagement Decor', 'wedding-engagement-decor', 1),
(NULL, 'Home Decor', 'home-decor', 1);

INSERT INTO categories (parent_id, name, slug, is_active) VALUES
(1, 'Stage Decor', 'stage-decor', 1);
-- Category tree is now: Wedding & Engagement Decor (id 1) -> Stage Decor (id 3, child)
--                        Home Decor (id 2, top-level, no children)

-- ---------------------------------------------------------------------
-- Products (3 products; Product 1 has multiple variants)
-- ---------------------------------------------------------------------
INSERT INTO products (category_id, name, slug, description, status, occasion, specifications) VALUES
(3, 'Floral Stage Backdrop', 'floral-stage-backdrop',
 'A full-width floral backdrop panel for wedding and engagement stages.',
 'published', 'Wedding',
 JSON_OBJECT('material', 'Artificial silk flowers', 'width_ft', 10, 'height_ft', 8)),

(2, 'Fairy Light String (10m)', 'fairy-light-string-10m',
 'Warm white fairy lights for room or entrance decoration.',
 'published', 'Home',
 JSON_OBJECT('length_meters', 10, 'bulb_color', 'Warm White', 'power_source', 'USB')),

(1, 'Mangni Rings Tray', 'mangni-rings-tray',
 'Decorative velvet tray for presenting engagement rings.',
 'published', 'Engagement',
 JSON_OBJECT('material', 'Velvet & Brass', 'tray_shape', 'Round'));

-- ---------------------------------------------------------------------
-- Variants
-- Product 1 (Floral Stage Backdrop) gets THREE color variants.
-- Only two of them will get a SKU below — the third ("White") is left
-- without a SKU on purpose: it is a valid combination that is simply
-- not sellable yet, per CAT04 (we do NOT fake a zero-stock SKU for it).
-- ---------------------------------------------------------------------
INSERT INTO variants (product_id, variant_label, option_values) VALUES
(1, 'Color: Red',  JSON_OBJECT('color', 'Red')),
(1, 'Color: Gold', JSON_OBJECT('color', 'Gold')),
(1, 'Color: White', JSON_OBJECT('color', 'White')),   -- intentionally left without a SKU
(2, 'Standard',    JSON_OBJECT('length_meters', 10)),
(3, 'Standard',    JSON_OBJECT('tray_shape', 'Round'));

-- ---------------------------------------------------------------------
-- SKUs (4 sellable SKUs — variant id 3 "Color: White" has none)
-- ---------------------------------------------------------------------
INSERT INTO skus (variant_id, sku_code, price, stock_quantity, is_active) VALUES
(1, 'BACKDROP-RED-001',  8500.00, 12, 1),
(2, 'BACKDROP-GOLD-001', 9200.00, 8,  1),
(4, 'FAIRYLIGHT-STD-001', 1200.00, 40, 1),
(5, 'RINGSTRAY-STD-001',  2500.00, 15, 1);

-- ---------------------------------------------------------------------
-- Assets (one sample image reference per product)
-- ---------------------------------------------------------------------
INSERT INTO assets (product_id, variant_id, storage_url, role, alt_text, sort_order) VALUES
(1, NULL, '/uploads/products/floral-stage-backdrop-main.jpg', 'primary', 'Floral stage backdrop', 0),
(2, NULL, '/uploads/products/fairy-light-string-main.jpg', 'primary', 'Fairy light string', 0),
(3, NULL, '/uploads/products/mangni-rings-tray-main.jpg', 'primary', 'Mangni rings tray', 0);
