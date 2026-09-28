<?php

declare(strict_types=1);

ob_start();

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalogue.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cart.php';
require_once dirname(__DIR__) . '/includes/content.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/analytics.php';
require_once dirname(__DIR__) . '/components/product-card.php';

$page_title = APP_NAME . ' | Contemporary fashion, thoughtfully chosen';
$page_description = 'Discover a refined collection of feminine fashion at Sanzida\'s Closet.';
$homepage_new_arrivals = [];
$homepage_best_sellers = [];
$homepage_cart_count = 0;
$homepage_content = [
    'hero' => ['heading' => 'Quiet luxury, made personal.', 'description' => 'Feminine pieces with a little more feeling. A thoughtful edit for days that deserve to be remembered.', 'cta_text' => 'Discover the edit', 'cta_url' => '#new-arrivals', 'image_path' => "Sanzida's Closet_banner.jpg", 'alt_text' => "Sanzida's Closet fashion collection", 'enabled' => true],
    'announcement' => ['items' => ['Complimentary delivery on orders over ₹ 5,000'], 'enabled' => true],
    'newsletter' => ['eyebrow' => 'The first look', 'heading' => 'Stay close to the story.', 'description' => 'Quiet notes, new pieces, and first access.', 'enabled' => true],
    'footer' => ['tagline' => 'Thoughtful fashion for every version of you.', 'copyright' => 'Made with intention'],
    'social' => ['instagram' => '#instagram', 'facebook' => '#facebook', 'pinterest' => '#pinterest', 'tiktok' => '#tiktok'],
];
$homepage_story = [];
$homepage_banners = [];
$homepage_category_banners = [];
try {
    $homepage_connection = database_connection();
    foreach (['hero', 'announcement', 'newsletter', 'footer', 'social'] as $content_key) $homepage_content[$content_key] = array_merge($homepage_content[$content_key], content_setting($homepage_connection, 'cms.' . $content_key));
    $homepage_story = content_page($homepage_connection, 'brand-story');
    $homepage_banners = content_banners($homepage_connection, 'homepage');
    $homepage_category_banners = array_values(array_filter(array_map(static function (array $category): array {
        return [
            'title' => (string) $category['name'],
            'image_path' => (string) ($category['image_path'] ?? ''),
            'alt_text' => (string) $category['name'],
            'link_url' => base_url('products/?category=' . rawurlencode((string) $category['slug'])),
        ];
    }, catalogue_list_categories($homepage_connection)), static fn (array $category): bool => $category['image_path'] !== ''));
    if (!empty($homepage_banners) && ($homepage_content['hero']['image_path'] ?? '') === "Sanzida's Closet_banner.jpg") $homepage_content['hero']['image_path'] = $homepage_banners[0]['image_path'];
    $homepage_new_arrivals = catalogue_list_products($homepage_connection, ['sort' => 'newest', 'per_page' => 4])['items'];
    $homepage_best_sellers = catalogue_list_products($homepage_connection, ['sort' => 'featured', 'per_page' => 3])['items'];
    $homepage_user = auth_current_user($homepage_connection);
    $homepage_cart_count = (int) cart_summary($homepage_connection, $homepage_user ? (int) $homepage_user['id'] : null)['item_count'];
} catch (Throwable $exception) {
    $homepage_new_arrivals = catalogue_demo_catalogue_data(['sort' => 'newest', 'per_page' => 4])['items'];
    $homepage_best_sellers = catalogue_demo_catalogue_data(['sort' => 'featured', 'per_page' => 3])['items'];
    error_log($exception->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= seo_meta_tags($page_title, $page_description, seo_absolute_url('/'), media_url($homepage_content['hero']['image_path'])) ?>
    <meta name="theme-color" content="#FDF5F3">
    <?= seo_jsonld(seo_organization_schema()) ?><?= seo_jsonld(seo_website_schema()) ?>
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
</head>
<body>
    <div class="announcement-bar" role="status">
        <div class="announcement-bar__viewport"><div class="announcement-bar__track"><?php $announcementItems = array_values(array_filter(array_map('strval', $homepage_content['announcement']['items'] ?? []))); if ($announcementItems === []) $announcementItems = ['Complimentary delivery on orders over ₹ 5,000']; ?><?php foreach (array_merge($announcementItems, $announcementItems) as $announcementItem): ?><span><?= escape_html($announcementItem) ?></span><?php endforeach; ?></div></div>
        <button class="announcement-bar__close" type="button" aria-label="Dismiss announcement">&times;</button>
    </div>

    <header class="site-header" data-header>
        <button class="icon-button mobile-menu-trigger" type="button" aria-label="Open menu" aria-controls="mobile-drawer" aria-expanded="false">
            <span class="ui-icon ui-icon--menu" aria-hidden="true"></span>
        </button>
        <a class="brand" href="<?= escape_html(base_url('/')) ?>" aria-label="Sanzida's Closet home">
            <img class="brand-logo" src="<?= escape_html(base_url("Sanzida's Closet_logo.jpg")) ?>" alt="" width="52" height="52">
            <span class="brand-name">Sanzida's Closet</span>
        </a>
        <nav class="desktop-navigation" aria-label="Primary navigation">
            <a class="nav-link is-active" href="<?= escape_html(base_url('/')) ?>">Home</a>
            <a class="nav-link" href="<?= escape_html(base_url('products/')) ?>">Shop</a>
            <a class="nav-link" href="<?= escape_html(base_url('new-arrivals/')) ?>">New Arrivals</a>
            <a class="nav-link" href="<?= escape_html(base_url('categories/')) ?>">Collections</a>
            <a class="nav-link" href="<?= escape_html(base_url('products/?category=dresses')) ?>">Dresses</a>
            <a class="nav-link" href="<?= escape_html(base_url('products/?category=tops')) ?>">Tops</a>
            <a class="nav-link" href="<?= escape_html(base_url('products/?category=accessories')) ?>">Accessories</a>
            <a class="nav-link nav-link--sale" href="<?= escape_html(base_url('sale/')) ?>">Sale</a>
        </nav>
        <div class="header-actions" aria-label="Shopping tools">
            <button class="icon-button" type="button" aria-label="Search" aria-controls="search-panel" aria-expanded="false" data-search-trigger>
                <span class="ui-icon ui-icon--search" aria-hidden="true"></span>
            </button>
            <a class="icon-button" href="<?= escape_html(base_url('account/')) ?>" aria-label="Account">
                <span class="ui-icon ui-icon--account" aria-hidden="true"></span>
            </a>
            <a class="icon-button icon-button--count" href="<?= escape_html(base_url('account/#wishlist-title')) ?>" aria-label="Wishlist, 0 items">
                <span class="ui-icon ui-icon--heart" aria-hidden="true"></span><span class="count-badge">0</span>
            </a>
            <a class="icon-button icon-button--count" href="<?= escape_html(base_url('cart/')) ?>" aria-label="Cart, <?= escape_html((string) $homepage_cart_count) ?> items">
                <span class="ui-icon ui-icon--bag" aria-hidden="true"></span><span class="count-badge"><?= escape_html((string) $homepage_cart_count) ?></span>
            </a>
        </div>
    </header>

    <aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true" aria-label="Mobile navigation">
        <div class="mobile-drawer__header"><span class="label">Menu</span><button class="icon-button" type="button" aria-label="Close menu" data-menu-close><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div>
        <nav class="mobile-navigation" aria-label="Mobile primary navigation">
            <a href="<?= escape_html(base_url('/')) ?>">Home</a><a href="<?= escape_html(base_url('products/')) ?>">Shop</a><a href="<?= escape_html(base_url('new-arrivals/')) ?>">New Arrivals</a><a href="<?= escape_html(base_url('categories/')) ?>">Collections</a><a href="<?= escape_html(base_url('products/?category=dresses')) ?>">Dresses</a><a href="<?= escape_html(base_url('products/?category=tops')) ?>">Tops</a><a href="<?= escape_html(base_url('products/?category=accessories')) ?>">Accessories</a><a class="nav-link--sale" href="<?= escape_html(base_url('sale/')) ?>">Sale</a>
        </nav>
        <div class="mobile-drawer__footer"><a href="<?= escape_html(base_url('account/')) ?>">Account</a><a href="<?= escape_html(base_url('account/#wishlist-title')) ?>">Wishlist <span class="badge">0</span></a><a href="<?= escape_html(base_url('cart/')) ?>">Cart <span class="badge">0</span></a></div>
    </aside>
    <div class="drawer-backdrop" data-menu-close hidden></div>

    <div class="search-panel" id="search-panel" role="dialog" aria-modal="true" aria-labelledby="search-title" hidden>
        <div class="search-panel__inner"><div class="search-panel__top"><p class="label" id="search-title">Search the closet</p><button class="icon-button" type="button" aria-label="Close search" data-search-close><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div><form class="search-form" role="search"><label class="sr-only" for="site-search">Search products</label><input class="input" id="site-search" type="search" placeholder="Search dresses, tops, accessories..." autocomplete="off"><button class="button button--icon" type="submit" aria-label="Submit search"><span class="ui-icon ui-icon--search" aria-hidden="true"></span></button></form><p class="muted search-panel__hint">Try “silk”, “occasion”, or “new arrivals”.</p></div>
    </div>

    <main>
        <section class="hero-carousel" data-hero-carousel aria-label="Sanzida's Closet featured banners">
            <?php if ($homepage_banners !== []): ?>
                <div class="hero-carousel__track">
                    <?php foreach ($homepage_banners as $index => $banner): ?><a class="hero-carousel__slide <?= $index === 0 ? 'is-active' : '' ?>" href="<?= escape_html((string) ($banner['link_url'] ?: '#new-arrivals')) ?>" data-hero-slide aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"><picture><?php if (!empty($banner['mobile_image_path'])): ?><source media="(max-width: 767px)" srcset="<?= escape_html(media_url($banner['mobile_image_path'])) ?>"><?php endif; ?><img src="<?= escape_html(media_url($banner['image_path'])) ?>" alt="<?= escape_html((string) ($banner['alt_text'] ?: $banner['title'])) ?>" width="1920" height="1080" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"></picture><span class="sr-only"><?= escape_html((string) $banner['title']) ?></span></a><?php endforeach; ?>
                </div>
                <?php if (count($homepage_banners) > 1): ?><div class="hero-carousel__controls" aria-label="Banner controls"><button type="button" data-hero-prev aria-label="Previous banner">&larr;</button><span data-hero-status>1 / <?= count($homepage_banners) ?></span><button type="button" data-hero-next aria-label="Next banner">&rarr;</button></div><?php endif; ?>
            <?php else: ?><div class="hero-carousel__empty"><img src="<?= escape_html(base_url("Sanzida's Closet_banner.jpg")) ?>" alt="Sanzida's Closet fashion collection" width="1920" height="1080" fetchpriority="high" decoding="async"></div><?php endif; ?>
        </section>

        <section class="trust-strip" aria-label="Sanzida's Closet promises"><div><span class="trust-strip__number">01</span><span>Thoughtful selection</span></div><div><span class="trust-strip__number">02</span><span>Premium details</span></div><div><span class="trust-strip__number">03</span><span>Made for your way</span></div><div><span class="trust-strip__number">04</span><span>Coming to you soon</span></div></section>

        <section class="section home-section" id="new-arrivals" aria-labelledby="new-arrivals-title">
            <div class="section__header"><div><p class="eyebrow">Just in / preview</p><h2 id="new-arrivals-title">New arrivals,<br><em>in the making.</em></h2></div><p class="section-intro">Our first collection is being carefully assembled. These catalogue spaces are ready for real products once the MySQL catalogue is connected.</p></div>
            <?php if ($homepage_new_arrivals !== []): ?><div class="product-grid product-grid--home"><?php foreach ($homepage_new_arrivals as $product) { render_product_card($product); } ?></div><?php else: ?><div class="alert alert--soft catalogue-state"><strong>The first edit is taking shape.</strong><p>New arrivals will appear here when active products are added to the catalogue.</p></div><?php endif; ?>
        </section>

        <section class="section section--soft category-section" aria-labelledby="category-title">
            <div class="container"><div class="section__header"><div><p class="eyebrow">Explore the edit</p><h2 id="category-title">The collections</h2></div><a class="text-action" href="<?= escape_html(base_url('categories/')) ?>">View all <span aria-hidden="true">&rarr;</span></a></div><div class="category-grid"><?php if ($homepage_category_banners !== []): ?><?php foreach ($homepage_category_banners as $index => $banner): ?><a class="category-tile" href="<?= escape_html((string) ($banner['link_url'] ?: base_url('categories/'))) ?>"><img class="category-tile__image" src="<?= escape_html(media_url((string) $banner['image_path'])) ?>"<?php if (!empty($banner['mobile_image_path'])): ?> data-mobile-image="<?= escape_html(media_url((string) $banner['mobile_image_path'])) ?>"<?php endif; ?> alt="<?= escape_html((string) ($banner['alt_text'] ?: $banner['title'])) ?>" loading="lazy"><span class="category-tile__number"><?= escape_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span><span><?= escape_html((string) $banner['title']) ?></span><span aria-hidden="true">&nearr;</span></a><?php endforeach; ?><?php else: ?><a class="category-tile category-tile--dress" href="<?= escape_html(base_url('products/?category=dresses')) ?>"><span class="category-tile__number">01</span><span>Dresses</span><span aria-hidden="true">&nearr;</span></a><a class="category-tile category-tile--top" href="<?= escape_html(base_url('products/?category=tops')) ?>"><span class="category-tile__number">02</span><span>Tops</span><span aria-hidden="true">&nearr;</span></a><a class="category-tile category-tile--accessory" href="<?= escape_html(base_url('products/?category=accessories')) ?>"><span class="category-tile__number">03</span><span>Accessories</span><span aria-hidden="true">&nearr;</span></a><a class="category-tile category-tile--collection" href="<?= escape_html(base_url('categories/')) ?>"><span class="category-tile__number">04</span><span>Collections</span><span aria-hidden="true">&nearr;</span></a><?php endif; ?></div></div>
        </section>

        <section class="featured-collection" id="collection" aria-labelledby="featured-title"><div class="featured-collection__image"><img src="<?= escape_html(base_url("Sanzida's Closet_logo.jpg")) ?>" alt="Sanzida's Closet emblem" width="1280" height="1280" loading="lazy"></div><div class="featured-collection__copy"><p class="eyebrow">Featured collection / 01</p><h2 id="featured-title">A softer kind<br><em>of statement.</em></h2><p>From the first sketch to the final detail, every piece begins with how you want to feel in it: confident, comfortable, unmistakably yourself.</p><a class="button button--outline" href="#story">Read the story <span aria-hidden="true">&rarr;</span></a></div></section>

        <section class="section home-section" aria-labelledby="best-sellers-title"><div class="section__header"><div><p class="eyebrow">Most wanted / catalogue</p><h2 id="best-sellers-title">Featured pieces,<br><em>soon to be yours.</em></h2></div><p class="section-intro">This selection is populated from featured catalogue records. Sales-ranked products will be connected when order data is available.</p></div><?php if ($homepage_best_sellers !== []): ?><div class="product-grid product-grid--home product-grid--three"><?php foreach ($homepage_best_sellers as $product) { render_product_card($product); } ?></div><?php else: ?><div class="alert alert--soft catalogue-state"><strong>Featured pieces are coming soon.</strong><p>Featured products will appear here when they are published in the catalogue.</p></div><?php endif; ?></section>

        <section class="promo-banner" aria-labelledby="promo-title"><div><p class="eyebrow">A little welcome</p><h2 id="promo-title">Your wardrobe,<br><em>with intention.</em></h2></div><p>Join our list for the first look at the collection and thoughtful notes from the closet.</p><a class="button button--soft" href="#newsletter">Stay close <span aria-hidden="true">&rarr;</span></a></section>

        <section class="story-section" id="story" aria-labelledby="story-title"><div class="story-section__copy"><p class="eyebrow">The Sanzida's Closet edit</p><h2 id="story-title">Pieces that stay<br><em>with you.</em></h2><p>We believe style is found in the details: a beautiful line, a gentle texture, an unexpected softness. Sanzida's Closet is being built as a considered space for the modern woman and the many lives she leads.</p><a class="text-action" href="#newsletter">Keep in touch <span aria-hidden="true">&rarr;</span></a></div><div class="story-section__mark" aria-hidden="true"><span>SC</span><small>Style your way</small></div></section>

        <section class="section section--soft values-section" aria-labelledby="values-title"><div class="container"><div class="section__header"><div><p class="eyebrow">Why choose us</p><h2 id="values-title">A better kind<br><em>of beautiful.</em></h2></div></div><div class="values-grid"><article><span class="value-number">01</span><h3>Curated, not crowded</h3><p>Every future piece earns its place through a clear point of view and careful consideration.</p></article><article><span class="value-number">02</span><h3>Details with feeling</h3><p>Soft textures, graceful lines, and small touches that make a piece feel like yours.</p></article><article><span class="value-number">03</span><h3>Fashion with intention</h3><p>A wardrobe should support your life, not compete with it. That is the edit we are building.</p></article></div></div></section>

        <section class="reviews-section" aria-labelledby="reviews-title"><div class="reviews-section__heading"><p class="eyebrow">From the closet</p><h2 id="reviews-title">Your words,<br><em>when we open.</em></h2></div><div class="review-placeholder"><span class="review-placeholder__quote" aria-hidden="true">“</span><p>Customer reviews will appear here after verified orders are completed. We will keep this space for real experiences, not invented ones.</p><span class="label">Verified customer stories / coming soon</span></div></section>

        <section class="social-section" aria-labelledby="social-title"><div class="container"><div class="section__header"><div><p class="eyebrow">Follow the edit</p><h2 id="social-title">Inside<br><em>the closet.</em></h2></div><p class="section-intro">Our social gallery will appear here when the brand channels are connected.</p></div><div class="social-grid"><div class="social-tile social-tile--wide"><img src="<?= escape_html(base_url("Sanzida's Closet_banner.jpg")) ?>" alt="Sanzida's Closet fashion preview" loading="lazy"><span>@sanzidascloset</span></div><div class="social-tile social-tile--mark"><span>SC</span><small>Style your way</small></div><div class="social-tile social-tile--note"><span>New pieces.<br>Quietly arriving.</span></div></div></div></section>

        <section class="newsletter-section" id="newsletter" aria-labelledby="newsletter-title"><div class="newsletter-section__inner"><p class="eyebrow">The first look</p><h2 id="newsletter-title">Stay close to<br><em>the story.</em></h2><p>Newsletter signup will be connected once the email service is configured. No address is collected yet.</p><span class="button is-disabled" aria-disabled="true">Signup coming soon <span aria-hidden="true">&rarr;</span></span></div></section>
    </main>

    <footer class="site-footer">
        <div class="footer-main"><div class="footer-brand"><a class="brand" href="<?= escape_html(base_url('/')) ?>"><span class="brand-mark">SC</span><span class="brand-name">Sanzida's Closet</span></a><p>Thoughtful fashion for every version of you.</p><div class="social-links" aria-label="Social media"><a href="#instagram" aria-label="Instagram">ig</a><a href="#facebook" aria-label="Facebook">f</a><a href="#pinterest" aria-label="Pinterest">p</a></div></div><div class="footer-column"><p class="label">About</p><a href="#story">Our story</a><a href="#new-arrivals">New arrivals</a><a href="#collection">Collections</a></div><div class="footer-column"><p class="label">Customer care</p><a href="#shipping">Shipping &amp; delivery</a><a href="#returns">Returns &amp; exchanges</a><a href="#contact">Contact us</a></div><div class="footer-column"><p class="label">Information</p><a href="#privacy">Privacy policy</a><a href="#terms">Terms &amp; conditions</a><a href="#faq">FAQs</a></div><div class="footer-newsletter"><p class="label">Stay in the know</p><p>Quiet notes, new pieces, and first access.</p><form class="newsletter-form"><label class="sr-only" for="newsletter-email">Email address</label><input class="input" id="newsletter-email" type="email" placeholder="Your email address" autocomplete="email"><button class="button button--icon" type="submit" aria-label="Subscribe"><span aria-hidden="true">&rarr;</span></button></form></div></div><div class="footer-bottom"><span>&copy; <?= date('Y') ?> <?= escape_html(APP_NAME) ?></span><span>We accept <strong class="payment-icons" aria-label="Visa, Mastercard and bKash">VISA &middot; MC &middot; bKash</strong></span><span>Made with intention</span></div>
    </footer>
    <script src="<?= escape_html(asset_url('js/site.js')) ?>" defer></script>
    <?= analytics_script() ?>
</body>
</html>
<?php
$homepage_output = ob_get_clean();
$homepage_output = str_replace('Thoughtful fashion for every version of you.', escape_html($homepage_content['footer']['tagline']), $homepage_output);
$homepage_output = str_replace('Newsletter signup will be connected once the email service is configured. No address is collected yet.', escape_html($homepage_content['newsletter']['description']), $homepage_output);
$homepage_output = str_replace('The first look</p><h2 id="newsletter-title">Stay close to<br><em>the story.', escape_html($homepage_content['newsletter']['eyebrow']) . '</p><h2 id="newsletter-title">' . escape_html($homepage_content['newsletter']['heading']), $homepage_output);
$homepage_output = str_replace('href="#instagram"', 'href="' . escape_html($homepage_content['social']['instagram']) . '"', $homepage_output);
$homepage_output = str_replace('href="#facebook"', 'href="' . escape_html($homepage_content['social']['facebook']) . '"', $homepage_output);
$homepage_output = str_replace('href="#pinterest"', 'href="' . escape_html($homepage_content['social']['pinterest']) . '"', $homepage_output);
if ($homepage_story !== []) {
    $homepage_output = str_replace('Pieces that stay<br><em>with you.</em>', escape_html((string) $homepage_story['title']), $homepage_output);
    $homepage_output = str_replace('We believe style is found in the details: a beautiful line, a gentle texture, an unexpected softness. Sanzida\'s Closet is being built as a considered space for the modern woman and the many lives she leads.', escape_html((string) $homepage_story['content']), $homepage_output);
}
echo $homepage_output;