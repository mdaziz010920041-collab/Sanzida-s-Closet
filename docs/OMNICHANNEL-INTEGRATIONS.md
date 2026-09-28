# Omnichannel integration architecture

Phase 19 establishes the website as the system of record. Product names, descriptions, images, variants, SKUs, prices, orders, and central inventory remain in the existing catalogue/order tables. Marketplace-specific identifiers and synchronization state live in the `marketplace_*` tables added by migration `012_omnichannel_architecture.sql`.

## Adapter boundary

`includes/omnichannel.php` defines `MarketplaceAdapter` with operations for:

- Product publication/update
- Inventory push
- Price push
- Order import
- Order status synchronization

`MarketplaceSyncService` creates queued or blocked jobs without calling a provider. `MarketplaceRegistry` owns channel selection. Amazon, Flipkart, Meesho, and Myntra currently use explicit unavailable adapters; credentials alone do not claim a connection or implement an API. The website adapter reports the central source-of-truth role.

Future provider adapters should implement `MarketplaceAdapter` in their own file and be registered in `MarketplaceRegistry`. They should translate external payloads into the central product/variant/order model, use idempotency keys, validate provider signatures/webhooks, and write mapping/error state rather than placing provider logic in catalogue or checkout code.

## Required credentials and approvals

The exact names and commercial prerequisites vary by account and region. Store secrets only in server environment variables or a secret manager; never in the database, templates, JavaScript, or repository.

### Amazon

- Seller/vendor account and marketplace/region approval
- Selling Partner API application approval
- Seller ID
- AWS access key ID and secret access key, if required by the selected SP-API signing flow
- SP-API application client ID, client secret, and refresh token
- Role approval for catalog, listings, inventory, orders, and reports as applicable
- Marketplace IDs and tax/shipping configuration

Environment placeholders: `AMAZON_SELLER_ID`, `AMAZON_CLIENT_ID`, `AMAZON_CLIENT_SECRET`, `AMAZON_REFRESH_TOKEN`.

### Flipkart

- Active Flipkart seller account and API access approval
- Seller ID
- Partner/API key and secret issued for the approved integration
- Marketplace region, warehouse, tax, shipping, and returns configuration
- Approved permissions for listings, inventory, orders, and shipment/status updates

Environment placeholders: `FLIPKART_SELLER_ID`, `FLIPKART_API_KEY`, `FLIPKART_API_SECRET`.

### Meesho

- Active Meesho supplier account and supplier API/partner approval
- Supplier ID
- API key/secret or OAuth credentials supplied by Meesho
- Catalog, inventory, order, shipping, and returns permissions
- Warehouse, GST/tax, and pickup configuration

Environment placeholders: `MEESHO_SUPPLIER_ID`, `MEESHO_API_KEY`, `MEESHO_API_SECRET`.

### Myntra

- Myntra partner/seller onboarding and written API approval
- Partner/seller ID
- API credentials or signed integration credentials supplied by Myntra
- Catalog, inventory, order, shipment, cancellation, and return permissions
- Warehouse, tax, brand, and fulfillment configuration

Environment placeholders: `MYNTRA_PARTNER_ID`, `MYNTRA_API_KEY`, `MYNTRA_API_SECRET`.

## Synchronization rules

1. Website inventory is the central available quantity: `quantity_on_hand - quantity_reserved`.
2. Marketplace quantities are snapshots in `marketplace_inventory`; they are not treated as authoritative.
3. Product and variant mapping must exist before inventory or price jobs run.
4. Every external order must have one unique `(channel_code, external_order_id)` mapping.
5. Sync jobs must be retryable and idempotent; failures belong in job/mapping error fields.
6. No channel is considered connected until its adapter is implemented, credentials are present, approvals are complete, and sandbox reconciliation succeeds.

Apply the migration before using the tables. No marketplace API calls are made by this phase.
