const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
}[character]));

function imageUrl(imagePath) {
    const value = String(imagePath || '').trim();
    if (/^https:\/\//i.test(value)) return value;
    const localPath = value.replace(/^\/+/, '');
    if (/^uploads\/[A-Za-z0-9_./-]+$/.test(localPath) && !localPath.split('/').some((segment) => segment === '.' || segment === '..')) return `/${localPath}`;
    return '';
}

function productCard(product) {
    const image = imageUrl(product.image_path);
    const price = Number(product.selling_price ?? product.base_price ?? 0);
    const symbol = ['INR', 'BDT'].includes(String(product.currency || 'INR').toUpperCase()) ? '₹' : `${product.currency || 'INR'} `;
    const priceText = `${symbol} ${price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const media = image
        ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(product.image_alt || product.name)}" loading="lazy" decoding="async" width="640" height="800">`
        : '<div class="catalogue-image-placeholder"><span>Image coming soon</span></div>';
    return `<article class="product-card" data-product-slug="${escapeHtml(product.slug)}"><a class="product-card__link" href="/products/view.php?slug=${encodeURIComponent(product.slug)}" data-recent-product="${escapeHtml(product.slug)}"><div class="product-card__media">${media}${product.is_featured ? '<span class="badge product-card__badge">Featured</span>' : ''}</div><div class="product-card__body"><p class="label">${escapeHtml(product.brand_name || "Sanzida's Closet")}</p><h3 class="product-card__title">${escapeHtml(product.name)}</h3><p class="product-card__price">${escapeHtml(priceText)}</p></div></a></article>`;
}

function categoryTiles(categories) {
    const defaultTiles = [
        ['dress', 'Dresses', 'dresses'],
        ['top', 'Tops', 'tops'],
        ['accessory', 'Accessories', 'accessories'],
        ['collection', 'Collections', ''],
    ];
    if (!categories.length) {
        return defaultTiles.map(([type, title, slug], index) => `<a class="category-tile category-tile--${type}" href="${slug ? `/products/?category=${slug}` : '/categories/'}"><span class="category-tile__number">${String(index + 1).padStart(2, '0')}</span><span>${title}</span><span aria-hidden="true">&nearr;</span></a>`).join('');
    }
    return categories.slice(0, 4).map((category, index) => {
        const image = imageUrl(category.image_path);
        return `<a class="category-tile" href="/products/?category=${encodeURIComponent(category.slug)}">${image ? `<img class="category-tile__image" src="${escapeHtml(image)}" alt="${escapeHtml(category.name)}" loading="lazy">` : ''}<span class="category-tile__number">${String(index + 1).padStart(2, '0')}</span><span>${escapeHtml(category.name)}</span><span aria-hidden="true">&nearr;</span></a>`;
    }).join('');
}

function renderHome(newArrivals = [], bestSellers = [], categories = [], canonicalOrigin = process.env.APP_URL || 'http://localhost:3000') {
    const arrivals = newArrivals.length
        ? `<div class="product-grid product-grid--home">${newArrivals.map(productCard).join('')}</div>`
        : '<div class="alert alert--soft catalogue-state"><strong>The first edit is taking shape.</strong><p>New arrivals will appear here when active products are added to the catalogue.</p></div>';
    const featured = bestSellers.length
        ? `<div class="product-grid product-grid--home product-grid--three">${bestSellers.map(productCard).join('')}</div>`
        : '<div class="alert alert--soft catalogue-state"><strong>Featured pieces are coming soon.</strong><p>Featured products will appear here when they are published in the catalogue.</p></div>';
    const categoryLinks = categoryTiles(categories);

    return `<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sanzida's Closet | Contemporary fashion, thoughtfully chosen</title>
    <meta name="description" content="Discover a refined collection of feminine fashion at Sanzida's Closet.">
    <meta name="theme-color" content="#FDF5F3">
    <link rel="canonical" href="${escapeHtml(canonicalOrigin)}/">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
    <div class="announcement-bar" role="status"><div class="announcement-bar__viewport"><div class="announcement-bar__track"><span>Complimentary delivery on orders over ₹ 5,000</span><span>Complimentary delivery on orders over ₹ 5,000</span></div></div><button class="announcement-bar__close" type="button" aria-label="Dismiss announcement">&times;</button></div>
    <header class="site-header" data-header>
        <button class="icon-button mobile-menu-trigger" type="button" aria-label="Open menu" aria-controls="mobile-drawer" aria-expanded="false"><span class="ui-icon ui-icon--menu" aria-hidden="true"></span></button>
        <a class="brand" href="/" aria-label="Sanzida's Closet home"><img class="brand-logo" src="/assets/images/brand-logo.jpg" alt="" width="52" height="52"><span class="brand-name">Sanzida's Closet</span></a>
        <nav class="desktop-navigation" aria-label="Primary navigation"><a class="nav-link is-active" href="/">Home</a><a class="nav-link" href="/products/">Shop</a><a class="nav-link" href="/new-arrivals/">New Arrivals</a><a class="nav-link" href="/categories/">Collections</a><a class="nav-link" href="/products/?category=dresses">Dresses</a><a class="nav-link" href="/products/?category=tops">Tops</a><a class="nav-link" href="/products/?category=accessories">Accessories</a><a class="nav-link nav-link--sale" href="/sale/">Sale</a></nav>
        <div class="header-actions" aria-label="Shopping tools"><button class="icon-button" type="button" aria-label="Search" aria-controls="search-panel" aria-expanded="false" data-search-trigger><span class="ui-icon ui-icon--search" aria-hidden="true"></span></button><a class="icon-button" href="/account/" aria-label="Account"><span class="ui-icon ui-icon--account" aria-hidden="true"></span></a><a class="icon-button icon-button--count" href="/account/#wishlist-title" aria-label="Wishlist"><span class="ui-icon ui-icon--heart" aria-hidden="true"></span><span class="count-badge">0</span></a><a class="icon-button icon-button--count" href="/cart/" aria-label="Cart"><span class="ui-icon ui-icon--bag" aria-hidden="true"></span><span class="count-badge">0</span></a></div>
    </header>
    <aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true" aria-label="Mobile navigation"><div class="mobile-drawer__header"><span class="label">Menu</span><button class="icon-button" type="button" aria-label="Close menu" data-menu-close><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div><nav class="mobile-navigation" aria-label="Mobile primary navigation"><a href="/">Home</a><a href="/products/">Shop</a><a href="/new-arrivals/">New Arrivals</a><a href="/categories/">Collections</a><a href="/products/?category=dresses">Dresses</a><a href="/products/?category=tops">Tops</a><a href="/products/?category=accessories">Accessories</a><a class="nav-link--sale" href="/sale/">Sale</a></nav><div class="mobile-drawer__footer"><a href="/account/">Account</a><a href="/account/#wishlist-title">Wishlist <span class="badge">0</span></a><a href="/cart/">Cart <span class="badge">0</span></a></div></aside>
    <div class="drawer-backdrop" data-menu-close hidden></div>
    <div class="search-panel" id="search-panel" role="dialog" aria-modal="true" aria-labelledby="search-title" hidden><div class="search-panel__inner"><div class="search-panel__top"><p class="label" id="search-title">Search the closet</p><button class="icon-button" type="button" aria-label="Close search" data-search-close><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div><form class="search-form catalogue-search" role="search" method="get" action="/products/"><label class="sr-only" for="site-search">Search products</label><input class="input" id="site-search" type="search" name="q" placeholder="Search dresses, tops, accessories..." autocomplete="off" data-search-input data-suggestions-url="/api/search-suggestions.php"><button class="button button--icon" type="submit" aria-label="Submit search"><span class="ui-icon ui-icon--search" aria-hidden="true"></span></button><div class="search-suggestions" data-search-suggestions hidden></div></form><p class="muted search-panel__hint">Try “silk”, “occasion”, or “new arrivals”.</p></div></div>
    <main>
        <section class="hero-carousel" data-hero-carousel aria-label="Sanzida's Closet featured banners"><div class="hero-carousel__track"><a class="hero-carousel__slide is-active" href="#new-arrivals" data-hero-slide aria-hidden="false"><picture><img src="/assets/images/brand-banner.jpg" alt="Sanzida's Closet fashion collection" width="1920" height="1080" fetchpriority="high" decoding="async"></picture><span class="sr-only">Sanzida's Closet featured collection</span></a></div></section>
        <section class="trust-strip" aria-label="Sanzida's Closet promises"><div><span class="trust-strip__number">01</span><span>Thoughtful selection</span></div><div><span class="trust-strip__number">02</span><span>Premium details</span></div><div><span class="trust-strip__number">03</span><span>Made for your way</span></div><div><span class="trust-strip__number">04</span><span>Coming to you soon</span></div></section>
        <section class="section home-section" id="new-arrivals" aria-labelledby="new-arrivals-title"><div class="section__header"><div><p class="eyebrow">Just in / preview</p><h2 id="new-arrivals-title">New arrivals,<br><em>in the making.</em></h2></div><p class="section-intro">Our first collection is being carefully assembled. These catalogue spaces are ready for real products once the MySQL catalogue is connected.</p></div>${arrivals}</section>
        <section class="section section--soft category-section" aria-labelledby="category-title"><div class="container"><div class="section__header"><div><p class="eyebrow">Explore the edit</p><h2 id="category-title">The collections</h2></div><a class="text-action" href="/categories/">View all <span aria-hidden="true">&rarr;</span></a></div><div class="category-grid">${categoryLinks}</div></div></section>
        <section class="featured-collection" id="collection" aria-labelledby="featured-title"><div class="featured-collection__image"><img src="/assets/images/brand-logo.jpg" alt="Sanzida's Closet emblem" width="1280" height="1280" loading="lazy"></div><div class="featured-collection__copy"><p class="eyebrow">Featured collection / 01</p><h2 id="featured-title">A softer kind<br><em>of statement.</em></h2><p>From the first sketch to the final detail, every piece begins with how you want to feel in it: confident, comfortable, unmistakably yourself.</p><a class="button button--outline" href="#story">Read the story <span aria-hidden="true">&rarr;</span></a></div></section>
        <section class="section home-section" aria-labelledby="best-sellers-title"><div class="section__header"><div><p class="eyebrow">Most wanted / catalogue</p><h2 id="best-sellers-title">Featured pieces,<br><em>soon to be yours.</em></h2></div><p class="section-intro">This selection is populated from featured catalogue records. Sales-ranked products will be connected when order data is available.</p></div>${featured}</section>
        <section class="promo-banner" aria-labelledby="promo-title"><div><p class="eyebrow">A little welcome</p><h2 id="promo-title">Your wardrobe,<br><em>with intention.</em></h2></div><p>Join our list for the first look at the collection and thoughtful notes from the closet.</p><a class="button button--soft" href="#newsletter">Stay close <span aria-hidden="true">&rarr;</span></a></section>
        <section class="story-section" id="story" aria-labelledby="story-title"><div class="story-section__copy"><p class="eyebrow">The Sanzida's Closet edit</p><h2 id="story-title">Pieces that stay<br><em>with you.</em></h2><p>We believe style is found in the details: a beautiful line, a gentle texture, an unexpected softness. Sanzida's Closet is being built as a considered space for the modern woman and the many lives she leads.</p><a class="text-action" href="#newsletter">Keep in touch <span aria-hidden="true">&rarr;</span></a></div><div class="story-section__mark" aria-hidden="true"><span>SC</span><small>Style your way</small></div></section>
        <section class="section section--soft values-section" aria-labelledby="values-title"><div class="container"><div class="section__header"><div><p class="eyebrow">Why choose us</p><h2 id="values-title">A better kind<br><em>of beautiful.</em></h2></div></div><div class="values-grid"><article><span class="value-number">01</span><h3>Curated, not crowded</h3><p>Every future piece earns its place through a clear point of view and careful consideration.</p></article><article><span class="value-number">02</span><h3>Details with feeling</h3><p>Soft textures, graceful lines, and small touches that make a piece feel like yours.</p></article><article><span class="value-number">03</span><h3>Fashion with intention</h3><p>A wardrobe should support your life, not compete with it. That is the edit we are building.</p></article></div></div></section>
        <section class="reviews-section" aria-labelledby="reviews-title"><div class="reviews-section__heading"><p class="eyebrow">From the closet</p><h2 id="reviews-title">Your words,<br><em>when we open.</em></h2></div><div class="review-placeholder"><span class="review-placeholder__quote" aria-hidden="true">“</span><p>Customer reviews will appear here after verified orders are completed. We will keep this space for real experiences, not invented ones.</p><span class="label">Verified customer stories / coming soon</span></div></section>
        <section class="social-section" aria-labelledby="social-title"><div class="container"><div class="section__header"><div><p class="eyebrow">Follow the edit</p><h2 id="social-title">Inside<br><em>the closet.</em></h2></div><p class="section-intro">Our social gallery will appear here when the brand channels are connected.</p></div><div class="social-grid"><div class="social-tile social-tile--wide"><img src="/assets/images/brand-banner.jpg" alt="Sanzida's Closet fashion preview" loading="lazy"><span>@sanzidascloset</span></div><div class="social-tile social-tile--mark"><span>SC</span><small>Style your way</small></div><div class="social-tile social-tile--note"><span>New pieces.<br>Quietly arriving.</span></div></div></div></section>
        <section class="newsletter-section" id="newsletter" aria-labelledby="newsletter-title"><div class="newsletter-section__inner"><p class="eyebrow">The first look</p><h2 id="newsletter-title">Stay close to<br><em>the story.</em></h2><p>Newsletter signup will be connected once the email service is configured. No address is collected yet.</p><span class="button is-disabled" aria-disabled="true">Signup coming soon <span aria-hidden="true">&rarr;</span></span></div></section>
    </main>
    <footer class="site-footer"><div class="footer-main"><div class="footer-brand"><a class="brand" href="/"><span class="brand-mark">SC</span><span class="brand-name">Sanzida's Closet</span></a><p>Thoughtful fashion for every version of you.</p><div class="social-links" aria-label="Social media"><a href="#instagram" aria-label="Instagram">ig</a><a href="#facebook" aria-label="Facebook">f</a><a href="#pinterest" aria-label="Pinterest">p</a></div></div><div class="footer-column"><p class="label">About</p><a href="#story">Our story</a><a href="#new-arrivals">New arrivals</a><a href="#collection">Collections</a></div><div class="footer-column"><p class="label">Customer care</p><a href="/returns-exchanges.php">Shipping &amp; delivery</a><a href="/returns-exchanges.php">Returns &amp; exchanges</a><a href="mailto:hello@sanzidascloset.com">Contact us</a></div><div class="footer-column"><p class="label">Information</p><a href="#privacy">Privacy policy</a><a href="#terms">Terms &amp; conditions</a><a href="#faq">FAQs</a></div><div class="footer-newsletter"><p class="label">Stay in the know</p><p>Quiet notes, new pieces, and first access.</p><form class="newsletter-form"><label class="sr-only" for="newsletter-email">Email address</label><input class="input" id="newsletter-email" type="email" placeholder="Your email address" autocomplete="email"><button class="button button--icon" type="submit" aria-label="Subscribe"><span aria-hidden="true">&rarr;</span></button></form></div></div><div class="footer-bottom"><span>&copy; ${new Date().getFullYear()} Sanzida's Closet</span><span>We accept <strong class="payment-icons" aria-label="Visa, Mastercard and bKash">VISA &middot; MC &middot; bKash</strong></span><span>Made with intention</span></div></footer>
    <script src="/assets/js/site.js" defer></script><script src="/assets/js/catalogue.js" defer></script>
</body>
</html>`;
}

module.exports = { renderHome };