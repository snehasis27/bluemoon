-- ============================================================
--  Bluemoon – Database Schema
--  MySQL 8.0+
--  Import via phpMyAdmin or: mysql -u root -p bluemoon_db < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `bluemoon_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bluemoon_db`;

-- ─── USERS ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)     NOT NULL,
    `email`         VARCHAR(191)     NOT NULL,
    `phone`         VARCHAR(20)      DEFAULT NULL,
    `password`      VARCHAR(255)     NOT NULL,
    `role`          ENUM('customer','admin','delivery') NOT NULL DEFAULT 'customer',
    `avatar`        VARCHAR(255)     DEFAULT NULL,
    `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
    `email_verified`TINYINT(1)       NOT NULL DEFAULT 0,
    `otp`           VARCHAR(10)      DEFAULT NULL,
    `otp_expires_at`DATETIME         DEFAULT NULL,
    `reset_token`   VARCHAR(128)     DEFAULT NULL,
    `reset_token_expires`DATETIME    DEFAULT NULL,
    `remember_token`VARCHAR(255)     DEFAULT NULL,
    `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── CATEGORIES ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80)   NOT NULL,
    `slug`        VARCHAR(100)  NOT NULL,
    `icon`        VARCHAR(10)   DEFAULT '🍽️',
    `image`       VARCHAR(255)  DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  INT           NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PRODUCTS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
    `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `category_id`   INT UNSIGNED   NOT NULL,
    `name`          VARCHAR(191)   NOT NULL,
    `slug`          VARCHAR(220)   NOT NULL,
    `description`   TEXT           DEFAULT NULL,
    `price`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `original_price`DECIMAL(10,2)  DEFAULT NULL COMMENT 'For strike-through display',
    `image`         VARCHAR(255)   DEFAULT NULL,
    `is_veg`        TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1=veg, 0=non-veg',
    `is_featured`   TINYINT(1)    NOT NULL DEFAULT 0,
    `is_popular`    TINYINT(1)    NOT NULL DEFAULT 0,
    `stock_status`  ENUM('in_stock','out_of_stock','limited') NOT NULL DEFAULT 'in_stock',
    `rating`        DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    `rating_count`  INT           NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_is_veg` (`is_veg`),
    INDEX `idx_is_featured` (`is_featured`),
    INDEX `idx_stock_status` (`stock_status`),
    FULLTEXT KEY `ft_name_desc` (`name`, `description`),
    CONSTRAINT `fk_product_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ADDRESSES ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `addresses` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED  NOT NULL,
    `label`       VARCHAR(50)   NOT NULL DEFAULT 'Home',
    `name`        VARCHAR(120)  NOT NULL,
    `phone`       VARCHAR(20)   NOT NULL,
    `line1`       VARCHAR(255)  NOT NULL,
    `line2`       VARCHAR(255)  DEFAULT NULL,
    `city`        VARCHAR(80)   NOT NULL,
    `state`       VARCHAR(80)   NOT NULL DEFAULT 'West Bengal',
    `pincode`     VARCHAR(10)   NOT NULL,
    `is_default`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── COUPONS ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupons` (
    `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `code`            VARCHAR(30)    NOT NULL,
    `description`     VARCHAR(255)   DEFAULT NULL,
    `discount_type`   ENUM('percent','flat') NOT NULL DEFAULT 'percent',
    `discount_value`  DECIMAL(10,2)  NOT NULL,
    `min_order_amount`DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `max_discount`    DECIMAL(10,2)  DEFAULT NULL COMMENT 'Cap for percent discounts',
    `usage_limit`     INT            DEFAULT NULL COMMENT 'NULL = unlimited',
    `usage_count`     INT            NOT NULL DEFAULT 0,
    `valid_from`      DATE           DEFAULT NULL,
    `valid_until`     DATE           DEFAULT NULL,
    `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ORDERS ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
    `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED   NOT NULL,
    `delivery_user_id` INT UNSIGNED   DEFAULT NULL,
    `address_id`       INT UNSIGNED   DEFAULT NULL,
    `delivery_address` TEXT           DEFAULT NULL COMMENT 'Snapshot at order time',
    `subtotal`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `delivery_fee`     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `discount_amount`  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `total_amount`     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `coupon_code`      VARCHAR(30)    DEFAULT NULL,
    `payment_method`   ENUM('upi','card','cod','screenshot') NOT NULL DEFAULT 'cod',
    `payment_status`   ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `order_status`     ENUM('placed','confirmed','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'placed',
    `notes`            TEXT           DEFAULT NULL,
    `estimated_time`   INT            DEFAULT 30 COMMENT 'Minutes',
    `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_order_status` (`order_status`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_delivery_user` (`delivery_user_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_order_delivery` FOREIGN KEY (`delivery_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ORDER ITEMS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED   NOT NULL,
    `product_id`  INT UNSIGNED   NOT NULL,
    `product_name`VARCHAR(191)   NOT NULL COMMENT 'Snapshot',
    `quantity`    INT            NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(10,2)  NOT NULL,
    `total_price` DECIMAL(10,2)  NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_product_id` (`product_id`),
    CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PAYMENTS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`       INT UNSIGNED  NOT NULL,
    `method`         VARCHAR(50)   NOT NULL,
    `transaction_id` VARCHAR(191)  DEFAULT NULL,
    `screenshot`     VARCHAR(255)  DEFAULT NULL,
    `amount`         DECIMAL(10,2) NOT NULL,
    `status`         ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    `notes`          TEXT          DEFAULT NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_id` (`order_id`),
    CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── REVIEWS ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reviews` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED  NOT NULL,
    `product_id`  INT UNSIGNED  NOT NULL,
    `order_id`    INT UNSIGNED  DEFAULT NULL,
    `rating`      TINYINT       NOT NULL DEFAULT 5 COMMENT '1-5',
    `comment`     TEXT          DEFAULT NULL,
    `image`       VARCHAR(255)  DEFAULT NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_product` (`user_id`, `product_id`),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_rev_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_rev_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ORDER STATUS HISTORY ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED  NOT NULL,
    `status`      VARCHAR(50)   NOT NULL,
    `note`        VARCHAR(255)  DEFAULT NULL,
    `changed_by`  INT UNSIGNED  DEFAULT NULL,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_id` (`order_id`),
    CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
--  SEED DATA
-- ═══════════════════════════════════════════════════════════════════════════════

-- Admin user  (password: Admin@123)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`, `is_active`, `email_verified`) VALUES
('Bluemoon Admin', 'admin@bluemoon.com', '9876543210',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1);

-- Delivery staff (password: Delivery@123)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`, `is_active`, `email_verified`) VALUES
('Ravi Kumar', 'delivery@bluemoon.com', '9123456780',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'delivery', 1, 1);

-- Sample customer (password: Customer@123)
INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`, `is_active`, `email_verified`) VALUES
('Snehasis Das', 'customer@bluemoon.com', '9000000001',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 1, 1);

-- Categories
INSERT INTO `categories` (`name`, `slug`, `icon`, `sort_order`) VALUES
('Burgers',     'burgers',     '🍔', 1),
('Pizzas',      'pizzas',      '🍕', 2),
('Biryani',     'biryani',     '🍛', 3),
('Chinese',     'chinese',     '🥢', 4),
('Rolls & Wraps','rolls-wraps','🌯', 5),
('Desserts',    'desserts',    '🍰', 6),
('Beverages',   'beverages',   '🥤', 7),
('Starters',    'starters',    '🥗', 8);

-- Products (burgers)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(1,'Classic Veg Burger','classic-veg-burger','Crispy veggie patty with fresh lettuce, tomato, and our signature sauce in a toasted sesame bun.',129,149,'assets/images/food/classic-veg-burger.jpg',1,1,1,'in_stock',4.5,120),
(1,'Spicy Chicken Burger','spicy-chicken-burger','Juicy grilled chicken with spicy jalapeño sauce, coleslaw, and pickles.',169,199,'assets/images/food/spicy-chicken-burger.jpg',0,0,1,'in_stock',4.7,98),
(1,'Double Decker Burger','double-decker-burger','Two flame-grilled patties with double cheese, caramelised onions, and smoky BBQ sauce.',249,299,'assets/images/food/double-decker-burger.jpg',0,1,0,'in_stock',4.6,75),
(1,'Mushroom Swiss Burger','mushroom-swiss-burger','Loaded with sautéed mushrooms and melted Swiss cheese on a brioche bun.',199,0,'assets/images/food/mushroom-swiss-burger.jpg',1,0,0,'in_stock',4.3,45);

-- Products (pizzas)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(2,'Margherita Pizza','margherita-pizza','Classic tomato base with fresh mozzarella and basil leaves. Simply perfect.',249,299,'assets/images/food/margherita-pizza.jpg',1,0,1,'in_stock',4.4,200),
(2,'Pepperoni Blast','pepperoni-blast','Loaded with premium pepperoni slices, mozzarella, and zesty tomato sauce.',349,399,'assets/images/food/pepperoni-blast.jpg',0,1,1,'in_stock',4.8,180),
(2,'Paneer Tikka Pizza','paneer-tikka-pizza','Tandoori-spiced paneer with bell peppers, onions, and creamy makhani sauce.',329,379,'assets/images/food/paneer-tikka-pizza.jpg',1,1,0,'in_stock',4.6,140),
(2,'BBQ Chicken Pizza','bbq-chicken-pizza','Smoky BBQ sauce base with grilled chicken, red onion, and cheddar.',369,419,'assets/images/food/bbq-chicken-pizza.jpg',0,0,0,'in_stock',4.5,90);

-- Products (biryani)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(3,'Chicken Dum Biryani','chicken-dum-biryani','Traditionally cooked dum biryani with aromatic spices, saffron, and tender chicken.',299,349,'assets/images/food/chicken-dum-biryani.jpg',0,1,1,'in_stock',4.9,350),
(3,'Veg Biryani','veg-biryani','Fragrant basmati rice cooked with seasonal vegetables and whole spices.',219,249,'assets/images/food/veg-biryani.jpg',1,0,1,'in_stock',4.2,210),
(3,'Mutton Biryani','mutton-biryani','Slow-cooked mutton with premium basmati and traditional Kolkata spices.',399,449,'assets/images/food/mutton-biryani.jpg',0,0,0,'in_stock',4.8,180),
(3,'Egg Biryani','egg-biryani','Flavorful biryani topped with masala-coated boiled eggs.',239,269,'assets/images/food/egg-biryani.jpg',0,0,0,'in_stock',4.3,130);

-- Products (Chinese)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(4,'Veg Fried Rice','veg-fried-rice','Wok-tossed basmati with fresh veggies, soy sauce, and sesame oil.',149,179,'assets/images/food/veg-fried-rice.jpg',1,0,1,'in_stock',4.1,160),
(4,'Chicken Chow Mein','chicken-chow-mein','Stir-fried noodles with shredded chicken, cabbage, and Indo-Chinese sauces.',199,229,'assets/images/food/chicken-chow-mein.jpg',0,0,1,'in_stock',4.4,140),
(4,'Chilli Paneer','chilli-paneer','Restaurant-style dry chilli paneer with bell peppers and tossed in spicy sauce.',219,249,'assets/images/food/chilli-paneer.jpg',1,1,0,'in_stock',4.6,110),
(4,'Manchurian Gravy','manchurian-gravy','Crispy vegetable balls in a rich, tangy Manchurian sauce. Best with fried rice.',179,199,'assets/images/food/manchurian-gravy.jpg',1,0,0,'in_stock',4.3,95);

-- Products (Rolls)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(5,'Egg Roll','egg-roll','Kolkata-style egg roll with crispy paratha, onion, and green chutney.',89,99,'assets/images/food/egg-roll.jpg',0,0,1,'in_stock',4.5,300),
(5,'Chicken Kathi Roll','chicken-kathi-roll','Spicy chicken tikka wrapped in flaky paratha with onions and lime.',129,149,'assets/images/food/chicken-kathi-roll.jpg',0,1,1,'in_stock',4.7,250),
(5,'Paneer Wrap','paneer-wrap','Grilled paneer with mint chutney, pickled vegetables in a whole-wheat wrap.',119,139,'assets/images/food/paneer-wrap.jpg',1,0,0,'in_stock',4.3,150);

-- Products (Desserts)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(6,'Gulab Jamun','gulab-jamun','Soft milk-solid dumplings soaked in rose-flavoured sugar syrup. Served warm.',79,99,'assets/images/food/gulab-jamun.jpg',1,0,1,'in_stock',4.8,400),
(6,'Chocolate Lava Cake','chocolate-lava-cake','Warm dark chocolate cake with a molten centre, served with vanilla ice cream.',149,179,'assets/images/food/chocolate-lava-cake.jpg',1,1,0,'in_stock',4.9,280),
(6,'Mango Kulfi','mango-kulfi','Traditional Indian ice cream with natural mango flavour on a stick.',69,89,'assets/images/food/mango-kulfi.jpg',1,0,0,'in_stock',4.6,200);

-- Products (Beverages)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(7,'Mango Lassi','mango-lassi','Thick and creamy sweet lassi blended with fresh Alphonso mango pulp.',99,119,'assets/images/food/mango-lassi.jpg',1,0,1,'in_stock',4.7,220),
(7,'Cold Coffee','cold-coffee','Chilled coffee blended with milk, ice cream, and a hint of vanilla.',89,109,'assets/images/food/cold-coffee.jpg',1,0,0,'in_stock',4.5,180),
(7,'Fresh Lime Soda','fresh-lime-soda','Refreshing lime juice with fizzy soda, mint, and black salt.',59,69,'assets/images/food/fresh-lime-soda.jpg',1,0,1,'in_stock',4.4,190),
(7,'Strawberry Shake','strawberry-shake','Thick strawberry milkshake made with real berries and vanilla ice cream.',109,129,'assets/images/food/strawberry-shake.jpg',1,1,0,'in_stock',4.6,140);

-- Products (Starters)
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`price`,`original_price`,`image`,`is_veg`,`is_featured`,`is_popular`,`stock_status`,`rating`,`rating_count`) VALUES
(8,'Veg Spring Rolls','veg-spring-rolls','Crispy fried rolls stuffed with cabbage, carrots, and glass noodles. Served with dipping sauce.',99,129,'assets/images/food/veg-spring-rolls.jpg',1,0,1,'in_stock',4.4,170),
(8,'Chicken Tikka','chicken-tikka','Tandoor-cooked boneless chicken marinated in yogurt and spices. Served with green chutney.',229,269,'assets/images/food/chicken-tikka.jpg',0,1,1,'in_stock',4.8,310),
(8,'Crispy Paneer Fingers','crispy-paneer-fingers','Golden-fried paneer strips coated in seasoned breadcrumbs. Addictively crunchy.',149,179,'assets/images/food/crispy-paneer-fingers.jpg',1,0,0,'in_stock',4.5,130);

-- Coupons
INSERT INTO `coupons` (`code`,`description`,`discount_type`,`discount_value`,`min_order_amount`,`max_discount`,`usage_limit`,`valid_from`,`valid_until`,`is_active`) VALUES
('WELCOME10','10% off your first order',       'percent', 10.00, 100.00, 50.00, 1000, '2024-01-01', '2030-12-31', 1),
('FLAT50',   '₹50 flat off on orders above ₹300','flat', 50.00, 300.00, NULL,  500,  '2024-01-01', '2030-12-31', 1),
('BLUEMOON20','20% off for Bluemoon members',  'percent', 20.00, 200.00, 100.00,200, '2024-01-01', '2030-12-31', 1),
('FREEDEL',  'Free delivery on any order',     'flat',    40.00, 0.00,   NULL,  NULL,'2024-01-01', '2030-12-31', 1);
