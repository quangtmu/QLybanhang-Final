CREATE OR REPLACE VIEW `order_user` AS
SELECT
    o.id AS order_id,
    o.order_code,
    o.buyer_id AS user_id,
    u.username,
    u.email,
    u.full_name,
    u.phone,
    o.status,
    o.final_amount,
    o.created_at
FROM orders o
JOIN users u ON u.id = o.buyer_id;

CREATE OR REPLACE VIEW `order_store` AS
SELECT
    o.id AS order_id,
    o.order_code,
    o.store_id,
    u.email AS store_email,
    COALESCE(sp.store_name, u.full_name, u.email) AS store_name,
    sp.store_slug,
    o.status,
    o.final_amount,
    o.created_at
FROM orders o
JOIN users u ON u.id = o.store_id
LEFT JOIN store_profiles sp ON sp.user_id = o.store_id;
