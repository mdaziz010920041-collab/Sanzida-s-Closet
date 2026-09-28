<?php

declare(strict_types=1);

function catalogue_seed_demo_products(int $limit = 20): array
{
    $names = ['Aurora Silk Dress', 'Sofia Wrap Blouse', 'Mira Pleated Skirt', 'Nadia Knit Set', 'Iris Satin Co-ord', 'Luna Evening Gown', 'Elara Tailored Blazer', 'Hana Cotton Set', 'Zara Flowery Midi', 'Leah Utility Tote', 'Noor Linen Shirt', 'Ayla Statement Heels', 'Rhea Striped Tee', 'Naomi Layered Necklace', 'Talia Wide-leg Trouser', 'Eden Soft Knit Cardigan', 'Ivy Minimal Sandals', 'Selene Evening Clutch', 'Kira Pleated Dress', 'Olive Satin Shirt'];
    $imagePaths = [
        'uploads/products/catalogue-aurora-silk-dress.svg',
        'uploads/products/catalogue-sofia-wrap-blouse.svg',
        'uploads/products/catalogue-mira-pleated-skirt.svg',
        'uploads/products/catalogue-nadia-knit-set.svg',
        'uploads/products/catalogue-iris-satin-coord.svg',
        'uploads/products/catalogue-luna-evening-gown.svg',
        'uploads/products/catalogue-elara-blazer.svg',
        'uploads/products/catalogue-hana-cotton-set.svg',
        'uploads/products/catalogue-zara-flowery-midi.svg',
        'uploads/products/catalogue-leah-utility-tote.svg',
        'uploads/products/catalogue-noor-linen-shirt.svg',
        'uploads/products/catalogue-ayla-heels.svg',
        'uploads/products/catalogue-rhea-striped-tee.svg',
        'uploads/products/catalogue-naomi-necklace.svg',
        'uploads/products/catalogue-talia-trouser.svg',
        'uploads/products/catalogue-eden-cardigan.svg',
        'uploads/products/catalogue-ivy-sandals.svg',
        'uploads/products/catalogue-selene-clutch.svg',
        'uploads/products/catalogue-kira-pleated-dress.svg',
        'uploads/products/catalogue-olive-satin-shirt.svg',
    ];
    $items = [];

    for ($index = 1; $index <= $limit; $index++) {
        $basePrice = 1290 + (($index * 195) % 5200);
        $discountValue = $index % 4 === 0 ? 20 : ($index % 3 === 0 ? 15 : 0);
        $discountType = $discountValue > 0 ? 'percentage' : 'none';
        $sellingPrice = $discountValue > 0 ? max(0, $basePrice - ($basePrice * $discountValue / 100)) : $basePrice;
        $compareAt = $basePrice + 650;

        $items[] = [
            'id' => $index,
            'name' => $names[($index - 1) % count($names)] . ' ' . (($index % 5) + 1),
            'slug' => 'demo-product-' . $index,
            'short_description' => 'A thoughtfully designed piece for effortless everyday styling.',
            'description' => 'Crafted for occasions that deserve a little more polish, this piece layers softness, structure, and deliberate detail into one signature silhouette.',
            'base_price' => (float) $basePrice,
            'compare_at_price' => (float) $compareAt,
            'discount_type' => $discountType,
            'discount_value' => (float) $discountValue,
            'currency' => 'INR',
            'is_featured' => $index % 3 === 0 ? 1 : 0,
            'brand_name' => 'Sanzida\'s Closet',
            'image_path' => $imagePaths[$index - 1] ?? null,
            'image_alt' => $names[($index - 1) % count($names)] . ' product image',
            'selling_price' => (float) $sellingPrice,
            'available_stock' => 8 + ($index % 12),
            'meta_title' => $names[($index - 1) % count($names)] . ' ' . (($index % 5) + 1) . ' | Sanzida\'s Closet',
            'meta_description' => 'Shop the refined ' . $names[($index - 1) % count($names)] . ' from Sanzida\'s Closet.',
            'canonical_url' => seo_absolute_url('products/' . 'demo-product-' . $index . '/'),
        ];
    }

    return $items;
}

function catalogue_demo_catalogue_data(array $filters = []): array
{
    $items = catalogue_seed_demo_products((int) ($filters['per_page'] ?? 20));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(20, max(1, (int) ($filters['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $sort = (string) ($filters['sort'] ?? 'newest');

    if ($sort === 'price-low') {
        usort($items, static fn (array $a, array $b): int => ((float) ($a['selling_price'] ?? 0)) <=> ((float) ($b['selling_price'] ?? 0)));
    } elseif ($sort === 'price-high') {
        usort($items, static fn (array $a, array $b): int => ((float) ($b['selling_price'] ?? 0)) <=> ((float) ($a['selling_price'] ?? 0)));
    } else {
        usort($items, static fn (array $a, array $b): int => ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));
    }

    $pagedItems = array_slice($items, $offset, $perPage);

    return [
        'items' => $pagedItems,
        'total' => count($items),
        'page' => $page,
        'per_page' => $perPage,
        'pages' => max(1, (int) ceil(count($items) / $perPage)),
    ];
}

function catalogue_demo_categories(): array
{
    return [
        ['id' => 1, 'name' => 'Dresses', 'slug' => 'dresses', 'description' => 'Easy silhouettes for everyday plans and special moments.', 'image_path' => '', 'product_count' => 1],
        ['id' => 2, 'name' => 'Tops', 'slug' => 'tops', 'description' => 'Polished separates with a soft point of view.', 'image_path' => '', 'product_count' => 1],
        ['id' => 3, 'name' => 'Accessories', 'slug' => 'accessories', 'description' => 'Finishing touches for the considered wardrobe.', 'image_path' => '', 'product_count' => 1],
    ];
}

function catalogue_demo_filter_options(): array
{
    return [
        'sizes' => [
            ['id' => 1, 'name' => 'Small', 'code' => 'S'],
            ['id' => 2, 'name' => 'Medium', 'code' => 'M'],
            ['id' => 3, 'name' => 'Large', 'code' => 'L'],
        ],
        'colors' => [
            ['id' => 1, 'name' => 'Ivory', 'hex_code' => '#F4EEE7'],
            ['id' => 2, 'name' => 'Rose', 'hex_code' => '#C98F91'],
            ['id' => 3, 'name' => 'Black', 'hex_code' => '#1D1B1B'],
            ['id' => 4, 'name' => 'Sage', 'hex_code' => '#A7B5A0'],
        ],
        'brands' => [
            ['id' => 1, 'name' => "Sanzida's Closet", 'slug' => 'sanzidas-closet'],
        ],
    ];
}

function catalogue_demo_product_by_slug(string $slug): ?array
{
    foreach (catalogue_seed_demo_products(20) as $product) {
        if (($product['slug'] ?? '') === $slug) {
            $basePrice = (float) ($product['base_price'] ?? 0);
            $sellingPrice = (float) ($product['selling_price'] ?? $basePrice);
            $productId = (int) ($product['id'] ?? 1);
            $angleSets = [
                1 => ['uploads/products/demo-satin-slip-dress-left.svg', 'uploads/products/demo-satin-slip-dress-right.svg'],
                2 => ['uploads/products/demo-satin-slip-dress-back.svg', 'uploads/products/demo-satin-slip-dress-top.svg'],
                3 => ['uploads/products/demo-satin-slip-dress-right.svg', 'uploads/products/demo-satin-slip-dress-bottom.svg'],
                4 => ['uploads/products/demo-satin-slip-dress-left.svg', 'uploads/products/demo-satin-slip-dress-back.svg'],
                5 => ['uploads/products/demo-satin-slip-dress-top.svg', 'uploads/products/demo-satin-slip-dress-bottom.svg'],
                6 => ['uploads/products/demo-satin-slip-dress-back.svg', 'uploads/products/demo-satin-slip-dress-right.svg'],
                7 => ['uploads/products/demo-linen-wrap-blouse-left.svg', 'uploads/products/demo-linen-wrap-blouse-right.svg'],
                8 => ['uploads/products/demo-linen-wrap-blouse-back.svg', 'uploads/products/demo-linen-wrap-blouse-top.svg'],
                9 => ['uploads/products/demo-linen-wrap-blouse-right.svg', 'uploads/products/demo-linen-wrap-blouse-bottom.svg'],
                10 => ['uploads/products/demo-linen-wrap-blouse-left.svg', 'uploads/products/demo-linen-wrap-blouse-back.svg'],
                11 => ['uploads/products/demo-linen-wrap-blouse-top.svg', 'uploads/products/demo-linen-wrap-blouse-bottom.svg'],
                12 => ['uploads/products/demo-linen-wrap-blouse-back.svg', 'uploads/products/demo-linen-wrap-blouse-right.svg'],
                13 => ['uploads/products/demo-structured-shoulder-bag-left.svg', 'uploads/products/demo-structured-shoulder-bag-right.svg'],
                14 => ['uploads/products/demo-structured-shoulder-bag-back.svg', 'uploads/products/demo-structured-shoulder-bag-top.svg'],
                15 => ['uploads/products/demo-structured-shoulder-bag-right.svg', 'uploads/products/demo-structured-shoulder-bag-bottom.svg'],
                16 => ['uploads/products/demo-structured-shoulder-bag-left.svg', 'uploads/products/demo-structured-shoulder-bag-back.svg'],
                17 => ['uploads/products/demo-structured-shoulder-bag-top.svg', 'uploads/products/demo-structured-shoulder-bag-bottom.svg'],
                18 => ['uploads/products/demo-structured-shoulder-bag-back.svg', 'uploads/products/demo-structured-shoulder-bag-right.svg'],
                19 => ['uploads/products/demo-minimal-sandals-side.svg', 'uploads/products/demo-minimal-sandals-top.svg'],
                20 => ['uploads/products/demo-satin-shirt-side.svg', 'uploads/products/demo-satin-shirt-back.svg'],
            ];
            $galleryPaths = array_merge([(string) ($product['image_path'] ?? '')], $angleSets[$productId] ?? []);
            $product['images'] = [];
            foreach ($galleryPaths as $imageIndex => $imagePath) {
                $product['images'][] = [
                    'id' => $imageIndex + 1,
                    'file_path' => $imagePath,
                    'alt_text' => (string) ($product['name'] ?? 'Product image') . ' view ' . ($imageIndex + 1),
                    'sort_order' => $imageIndex + 1,
                    'is_primary' => $imageIndex === 0 ? 1 : 0,
                ];
            }
            $product['videos'] = [];
            $product['variants'] = [[
                'id' => 1,
                'sku' => 'DEMO-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($product['name'] ?? 'PRD')), 0, 8)) . '-' . ($product['id'] ?? 1),
                'size_id' => 1,
                'color_id' => 1,
                'price_override' => null,
                'compare_at_price_override' => null,
                'size_name' => 'One Size',
                'size_code' => 'OS',
                'color_name' => 'Natural',
                'hex_code' => '#D5B29A',
                'available_stock' => (int) ($product['available_stock'] ?? 10),
            ]];
            $product['categories'] = [['id' => 1, 'name' => 'Featured', 'slug' => 'featured']];
            $product['review_count'] = 0;
            $product['rating_value'] = null;
            $product['structured_data'] = null;
            $product['meta_title'] = (string) ($product['meta_title'] ?? ($product['name'] ?? 'Product'));
            $product['meta_description'] = (string) ($product['meta_description'] ?? ($product['description'] ?? ''));
            $product['canonical_url'] = (string) ($product['canonical_url'] ?? seo_absolute_url('products/' . rawurlencode($slug) . '/'));
            $product['base_price'] = $basePrice;
            $product['selling_price'] = $sellingPrice;
            return $product;
        }
    }

    return null;
}

function catalogue_list_products(PDO $connection, array $filters = []): array
{
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(20, max(1, (int) ($filters['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $where = ["p.status = 'active'"];
    $parameters = [];
    $priceExpression = "CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0) WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END";

    if (($filters['category'] ?? '') !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM product_categories filter_pc INNER JOIN categories filter_c ON filter_c.id = filter_pc.category_id WHERE filter_pc.product_id = p.id AND filter_c.slug = :category_slug)';
        $parameters['category_slug'] = (string) $filters['category'];
    }

    if (($filters['brand'] ?? '') !== '') {
        $where[] = 'b.slug = :brand_slug';
        $parameters['brand_slug'] = (string) $filters['brand'];
    }

    if (($filters['size'] ?? '') !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM product_variants filter_size_variant WHERE filter_size_variant.product_id = p.id AND filter_size_variant.size_id = :size_id AND filter_size_variant.status = \'active\')';
        $parameters['size_id'] = (int) $filters['size'];
    }

    if (($filters['color'] ?? '') !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM product_variants filter_color_variant WHERE filter_color_variant.product_id = p.id AND filter_color_variant.color_id = :color_id AND filter_color_variant.status = \'active\')';
        $parameters['color_id'] = (int) $filters['color'];
    }

    if (($filters['min_price'] ?? '') !== '' && is_numeric($filters['min_price'])) {
        $where[] = "{$priceExpression} >= :min_price";
        $parameters['min_price'] = max(0, (float) $filters['min_price']);
    }

    if (($filters['max_price'] ?? '') !== '' && is_numeric($filters['max_price'])) {
        $where[] = "{$priceExpression} <= :max_price";
        $parameters['max_price'] = max(0, (float) $filters['max_price']);
    }

    if (($filters['availability'] ?? '') === 'in-stock') {
        $where[] = 'EXISTS (SELECT 1 FROM product_variants filter_stock_variant INNER JOIN inventory filter_stock_inventory ON filter_stock_inventory.variant_id = filter_stock_variant.id WHERE filter_stock_variant.product_id = p.id AND filter_stock_variant.status = \'active\' AND filter_stock_inventory.quantity_on_hand > filter_stock_inventory.quantity_reserved)';
    }

    if (($filters['discount'] ?? '') === 'on-sale') {
        $where[] = "(p.discount_type <> 'none' AND p.discount_value > 0)";
    }

    if (($filters['search'] ?? '') !== '') {
        $where[] = '(p.name LIKE :search_name OR p.short_description LIKE :search_name OR EXISTS (SELECT 1 FROM product_variants search_variant WHERE search_variant.product_id = p.id AND search_variant.sku LIKE :search_sku))';
        $searchTerm = '%' . trim((string) $filters['search']) . '%';
        $parameters['search_name'] = $searchTerm;
        $parameters['search_sku'] = $searchTerm;
    }

    $sortOptions = [
        'newest' => 'p.published_at DESC, p.id DESC',
        'price-low' => 'selling_price ASC, p.id DESC',
        'price-high' => 'selling_price DESC, p.id DESC',
        'popular' => 'popularity DESC, p.published_at DESC, p.id DESC',
        'featured' => 'p.is_featured DESC, p.published_at DESC, p.id DESC',
    ];
    $sort = $sortOptions[(string) ($filters['sort'] ?? 'newest')] ?? $sortOptions['newest'];
    $whereSql = implode(' AND ', $where);

    $countStatement = $connection->prepare("SELECT COUNT(*) FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE {$whereSql}");
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();

    $query = <<<SQL
        SELECT
            p.id, p.name, p.slug, p.short_description, p.base_price, p.compare_at_price,
            p.discount_type, p.discount_value, p.currency, p.is_featured,
            b.name AS brand_name,
            (SELECT image.file_path FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order ASC, image.id ASC LIMIT 1) AS image_path,
            (SELECT image.alt_text FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order ASC, image.id ASC LIMIT 1) AS image_alt,
            CASE
                WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0)
                WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0)
                ELSE p.base_price
            END AS selling_price,
            (SELECT COALESCE(SUM(popularity_item.quantity), 0) FROM order_items popularity_item INNER JOIN orders popularity_order ON popularity_order.id = popularity_item.order_id WHERE popularity_item.product_id = p.id AND popularity_order.status IN ('confirmed', 'processing', 'shipped', 'delivered', 'completed')) AS popularity,
            COALESCE(SUM(GREATEST(inventory.quantity_on_hand - inventory.quantity_reserved, 0)), 0) AS available_stock
        FROM products p
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_variants variant ON variant.product_id = p.id AND variant.status = 'active'
        LEFT JOIN inventory ON inventory.variant_id = variant.id
        WHERE {$whereSql}
        GROUP BY p.id, p.name, p.slug, p.short_description, p.base_price, p.compare_at_price,
            p.discount_type, p.discount_value, p.currency, p.is_featured, b.name
        ORDER BY {$sort}
        LIMIT :limit OFFSET :offset
    SQL;

    $statement = $connection->prepare($query);
    foreach ($parameters as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $items = $statement->fetchAll();
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

function catalogue_list_categories(PDO $connection): array
{
    $statement = $connection->query("SELECT c.id, c.name, c.slug, c.description, c.image_path, COUNT(DISTINCT p.id) AS product_count FROM categories c LEFT JOIN product_categories pc ON pc.category_id = c.id LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'active' WHERE c.status = 'active' GROUP BY c.id, c.name, c.slug, c.description, c.image_path ORDER BY c.sort_order ASC, c.name ASC");

    return $statement->fetchAll();
}

function catalogue_filter_options(PDO $connection): array
{
    return [
        'sizes' => $connection->query("SELECT id, name, code FROM sizes WHERE status = 'active' ORDER BY sort_order ASC, name ASC")->fetchAll(),
        'colors' => $connection->query("SELECT id, name, hex_code FROM colors WHERE status = 'active' ORDER BY sort_order ASC, name ASC")->fetchAll(),
        'brands' => $connection->query("SELECT id, name, slug FROM brands WHERE status = 'active' ORDER BY name ASC")->fetchAll(),
    ];
}

function catalogue_find_product(PDO $connection, string $slug): ?array
{
    $statement = $connection->prepare("SELECT p.*, b.name AS brand_name, b.slug AS brand_slug, seo.meta_title, seo.meta_description, seo.meta_keywords, seo.canonical_url, seo.structured_data, (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') AS review_count, (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') AS rating_value FROM products p LEFT JOIN brands b ON b.id = p.brand_id LEFT JOIN seo_metadata seo ON seo.entity_type = 'product' AND seo.entity_id = p.id WHERE p.slug = :slug AND p.status = 'active' LIMIT 1");
    $statement->execute(['slug' => $slug]);
    $product = $statement->fetch();

    if (!$product) {
        return null;
    }

    $mediaStatement = $connection->prepare('SELECT id, file_path, alt_text, sort_order, is_primary FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, sort_order ASC, id ASC');
    $mediaStatement->execute(['product_id' => $product['id']]);
    $product['images'] = $mediaStatement->fetchAll();

    $videoStatement = $connection->prepare('SELECT id, video_url, thumbnail_path, alt_text, sort_order FROM product_videos WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');
    $videoStatement->execute(['product_id' => $product['id']]);
    $product['videos'] = $videoStatement->fetchAll();

    $variantStatement = $connection->prepare("SELECT variant.id, variant.sku, variant.size_id, variant.color_id, variant.price_override, variant.compare_at_price_override, size.name AS size_name, size.code AS size_code, color.name AS color_name, color.hex_code, COALESCE(GREATEST(inventory.quantity_on_hand - inventory.quantity_reserved, 0), 0) AS available_stock FROM product_variants variant LEFT JOIN sizes size ON size.id = variant.size_id LEFT JOIN colors color ON color.id = variant.color_id LEFT JOIN inventory ON inventory.variant_id = variant.id WHERE variant.product_id = :product_id AND variant.status = 'active' ORDER BY size.sort_order ASC, color.sort_order ASC, variant.id ASC");
    $variantStatement->execute(['product_id' => $product['id']]);
    $product['variants'] = $variantStatement->fetchAll();

    $categoryStatement = $connection->prepare('SELECT c.id, c.name, c.slug FROM product_categories pc INNER JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = :product_id AND c.status = \'active\' ORDER BY c.sort_order ASC, c.name ASC');
    $categoryStatement->execute(['product_id' => $product['id']]);
    $product['categories'] = $categoryStatement->fetchAll();

    return $product;
}

function catalogue_related_products(PDO $connection, int $productId, int $limit = 4): array
{
    $limit = min(8, max(1, $limit));
    $query = <<<SQL
        SELECT p.id, p.name, p.slug, p.base_price, p.compare_at_price, p.discount_type, p.discount_value, p.currency,
            (SELECT image.file_path FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order ASC, image.id ASC LIMIT 1) AS image_path,
            (SELECT image.alt_text FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order ASC, image.id ASC LIMIT 1) AS image_alt,
            CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0) WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END AS selling_price
        FROM products p
        INNER JOIN product_categories related_pc ON related_pc.product_id = p.id
        INNER JOIN product_categories source_pc ON source_pc.category_id = related_pc.category_id AND source_pc.product_id = :product_id
        WHERE p.status = 'active' AND p.id <> :excluded_id
        GROUP BY p.id, p.name, p.slug, p.base_price, p.compare_at_price, p.discount_type, p.discount_value, p.currency
        ORDER BY p.is_featured DESC, p.published_at DESC, p.id DESC
        LIMIT {$limit}
    SQL;
    $statement = $connection->prepare($query);
    $statement->execute(['product_id' => $productId, 'excluded_id' => $productId]);

    $related = $statement->fetchAll();
    if ($related !== []) {
        return $related;
    }

    return [];
}