# Database Relationships

The schema uses InnoDB and `utf8mb4`. Monetary values use `DECIMAL(12,2)` and timestamps are stored in UTC. Application code should convert timestamps to the configured display timezone.

## Identity and access

- `admins.role_id` links administrators to `roles`.
- `role_permissions` is the many-to-many bridge between `roles` and `permissions`.
- `users` stores customer accounts separately from privileged administrator accounts.

## Catalogue

- `products.brand_id` optionally links a product to `brands`.
- `categories.parent_id` supports nested categories.
- `product_categories` allows a product to appear in multiple categories.
- `product_variants` stores the sellable SKU and optional `sizes` and `colors` combination.
- `product_images` and `product_videos` support product-level or variant-level media.
- `inventory` has one current balance per variant; `inventory_transactions` is its audit trail.

## Customer commerce

- `carts` may belong to a logged-in `users` row or an anonymous session token.
- `cart_items` stores one row per cart and variant.
- `wishlists` belong to users and `wishlist_items` stores their selected variants.
- `addresses` belong to users. Orders retain JSON address snapshots so historical addresses do not change when a customer edits an address.

## Orders and fulfilment

- `orders` optionally link to a user, coupon, and saved addresses.
- `order_items` retain product, variant, SKU, name, price, and quantity snapshots; catalogue deletion cannot erase order history.
- `payments` and `shipments` may have multiple records per order to support retries, partial payments, and fulfilment workflows.
- `coupon_usage` records a coupon redemption per order.
- `returns` contain `return_items` linked to order lines; `refunds` optionally link to a payment and return.

## Content and communication

- `pages`, `banners`, and `settings` support future CMS/admin work.
- `seo_metadata` uses a controlled `(entity_type, entity_id)` key for pages, products, categories, and brands.
- `notifications` can target either a user or an admin.
- `newsletter_subscribers` stores subscription state and a hashed confirmation token, never a raw token.

The schema intentionally does not store payment secrets, raw card data, passwords, or API credentials.