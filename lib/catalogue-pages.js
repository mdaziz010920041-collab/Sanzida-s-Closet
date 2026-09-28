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
    const media = image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(product.image_alt || product.name)}" loading="lazy" decoding="async" width="640" height="800">` : '<div class="catalogue-image-placeholder"><span>Image coming soon</span></div>';
    return `<article class="product-card"><a href="/products/view.php?slug=${encodeURIComponent(product.slug)}"><div class="product-card__media">${media}${product.is_featured ? '<span class="badge product-card__badge">Featured</span>' : ''}</div><div class="product-card__body"><p class="label">${escapeHtml(product.brand_name || "Sanzida's Closet")}</p><h2 class="product-card__title">${escapeHtml(product.name)}</h2><p class="product-card__price">${escapeHtml(priceText)}</p><p class="product-card__availability ${Number(product.available_stock) > 0 ? 'is-available' : 'is-unavailable'}">${Number(product.available_stock) > 0 ? 'Available' : 'Currently unavailable'}</p></div></a></article>`;
}

function pageShell(title, content) {
    const isCategories = title === 'Categories';
    return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${escapeHtml(title)} | Sanzida's Closet</title><link rel="stylesheet" href="/assets/css/site.css"></head>
<body class="catalogue-page">
    <div class="announcement-bar" role="status"><div class="announcement-bar__viewport"><div class="announcement-bar__track"><span>Complimentary delivery on orders over ₹ 5,000</span><span>Complimentary delivery on orders over ₹ 5,000</span></div></div><button class="announcement-bar__close" type="button" aria-label="Dismiss announcement">&times;</button></div>
    <header class="site-header" data-header>
        <button class="icon-button mobile-menu-trigger" type="button" aria-label="Open menu" aria-controls="mobile-drawer" aria-expanded="false"><span class="ui-icon ui-icon--menu" aria-hidden="true"></span></button>
        <a class="brand" href="/" aria-label="Sanzida's Closet home"><img class="brand-logo" src="/assets/images/brand-logo.jpg" alt="" width="52" height="52"><span class="brand-name">Sanzida's Closet</span></a>
        <nav class="desktop-navigation" aria-label="Primary navigation"><a class="nav-link" href="/">Home</a><a class="nav-link ${isCategories ? '' : 'is-active'}" href="/products/">Shop</a><a class="nav-link" href="/new-arrivals/">New Arrivals</a><a class="nav-link ${isCategories ? 'is-active' : ''}" href="/categories/">Collections</a><a class="nav-link" href="/products/?category=dresses">Dresses</a><a class="nav-link" href="/products/?category=tops">Tops</a><a class="nav-link" href="/products/?category=accessories">Accessories</a><a class="nav-link nav-link--sale" href="/sale/">Sale</a></nav>
        <div class="header-actions" aria-label="Shopping tools"><button class="icon-button" type="button" aria-label="Search" aria-controls="search-panel" aria-expanded="false" data-search-trigger><span class="ui-icon ui-icon--search" aria-hidden="true"></span></button><a class="icon-button" href="/account/" aria-label="Account"><span class="ui-icon ui-icon--account" aria-hidden="true"></span></a><a class="icon-button icon-button--count" href="/account/#wishlist-title" aria-label="Wishlist"><span class="ui-icon ui-icon--heart" aria-hidden="true"></span><span class="count-badge">0</span></a><a class="icon-button icon-button--count" href="/cart/" aria-label="Cart"><span class="ui-icon ui-icon--bag" aria-hidden="true"></span><span class="count-badge">0</span></a></div>
    </header>
    <aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true" aria-label="Mobile navigation"><div class="mobile-drawer__header"><span class="label">Menu</span><button class="icon-button" type="button" aria-label="Close menu" data-menu-close><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div><nav class="mobile-navigation" aria-label="Mobile primary navigation"><a href="/">Home</a><a href="/products/">Shop</a><a href="/new-arrivals/">New Arrivals</a><a href="/categories/">Collections</a><a href="/products/?category=dresses">Dresses</a><a href="/products/?category=tops">Tops</a><a href="/products/?category=accessories">Accessories</a><a class="nav-link--sale" href="/sale/">Sale</a></nav><div class="mobile-drawer__footer"><a href="/account/">Account</a><a href="/account/#wishlist-title">Wishlist <span class="badge">0</span></a><a href="/cart/">Cart <span class="badge">0</span></a></div></aside>
    <div class="drawer-backdrop" data-menu-close hidden></div>
    <div class="search-panel" id="search-panel" role="dialog" aria-modal="true" aria-labelledby="search-title" hidden><div class="search-panel__inner"><div class="search-panel__top"><p class="label" id="search-title">Search the closet</p><button class="icon-button" type="button" aria-label="Close search" data-search-close><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div><form class="search-form catalogue-search" role="search" method="get" action="/products/"><label class="sr-only" for="site-search">Search products</label><input class="input" id="site-search" type="search" name="q" placeholder="Search dresses, tops, accessories..." autocomplete="off" data-search-input data-suggestions-url="/api/search-suggestions.php"><button class="button button--icon" type="submit" aria-label="Submit search"><span class="ui-icon ui-icon--search" aria-hidden="true"></span></button><div class="search-suggestions" data-search-suggestions hidden></div></form><p class="muted search-panel__hint">Try “silk”, “occasion”, or “new arrivals”.</p></div></div>
    <main class="catalogue-main">${content}</main>
    <footer class="site-footer"><a href="/">Sanzida's Closet</a><a href="/returns-exchanges.php">Returns &amp; exchanges</a><span>&copy; ${new Date().getFullYear()}</span></footer>
    <script src="/assets/js/site.js"></script><script src="/assets/js/catalogue.js"></script>
</body></html>`;
}

function renderCatalogue(data, filters, title) {
    const cards = data.items.map(productCard).join('');
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries({ q: filters.search, category: filters.category, brand: filters.brand, sort: filters.sort })) {
        if (value) query.set(key, value);
    }
    const pagination = data.pages > 1 ? `<nav class="pagination" aria-label="Catalogue pages">${Array.from({ length: data.pages }, (_, index) => index + 1).map((page) => {
        const pageQuery = new URLSearchParams(query);
        pageQuery.set('page', String(page));
        return `<a class="${page === data.page ? 'is-active' : ''}" href="?${escapeHtml(pageQuery.toString())}" aria-label="Page ${page}">${page}</a>`;
    }).join('')}</nav>` : '';
    const heading = title === 'Sale' ? 'Special offers' : title === 'New arrivals' ? 'Just in' : 'The catalogue';
    const content = `<section class="catalogue-heading"><p class="eyebrow">${heading}</p><h1>${escapeHtml(title)}</h1><p>Thoughtful pieces, presented as they arrive from the catalogue.</p></section><section class="catalogue-toolbar"><form class="catalogue-search" method="get" action="/products/"><label class="sr-only" for="catalogue-query">Search products</label><input class="input" id="catalogue-query" type="search" name="q" value="${escapeHtml(filters.search)}" placeholder="Search name or SKU"><button class="button" type="submit">Search</button></form><label class="sr-only" for="catalogue-sort">Sort products</label><select class="select" id="catalogue-sort" onchange="const u=new URL(location.href);u.searchParams.set('sort',this.value);location.href=u"><option value="newest" ${filters.sort === 'newest' ? 'selected' : ''}>Newest</option><option value="featured" ${filters.sort === 'featured' ? 'selected' : ''}>Featured</option><option value="popular" ${filters.sort === 'popular' ? 'selected' : ''}>Popularity</option><option value="price-low" ${filters.sort === 'price-low' ? 'selected' : ''}>Price: low to high</option><option value="price-high" ${filters.sort === 'price-high' ? 'selected' : ''}>Price: high to low</option></select></section>${cards ? `<p class="muted" aria-live="polite">${data.total} ${data.total === 1 ? 'piece' : 'pieces'}</p><div class="product-grid product-grid--catalogue">${cards}</div>${pagination}` : '<div class="empty-state"><p class="eyebrow">No pieces found</p><h2>The edit is still taking shape.</h2><p>Try another search or return to the full catalogue.</p><a class="button" href="/products/">View all pieces</a></div>'}`;
    return pageShell(title, content);
}

function renderCategories(categories) {
    const cards = categories.map((category) => {
        const image = imageUrl(category.image_path);
        const media = image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(category.name)}" loading="lazy" width="800" height="600">` : '<div class="catalogue-image-placeholder"><span>Category image coming soon</span></div>';
        return `<a class="category-list-card" href="/products/?category=${encodeURIComponent(category.slug)}">${media}<div><p class="label">${Number(category.product_count)} pieces</p><h2>${escapeHtml(category.name)}</h2><span class="text-action">Explore &rarr;</span></div></a>`;
    }).join('');
    const content = `<section class="catalogue-heading"><p class="eyebrow">Browse by feeling</p><h1>The collections</h1><p>Explore categories populated from the active catalogue.</p></section>${cards ? `<div class="category-list-grid">${cards}</div>` : '<div class="empty-state"><p class="eyebrow">Coming soon</p><h2>Our categories are taking shape.</h2><p>Active catalogue categories will appear here once they are added.</p></div>'}`;
    return pageShell('Categories', content);
}

function renderProductGallery(product) {
    const images = product.images.map((image) => ({ ...image, source: imageUrl(image.file_path) })).filter((image) => image.source);
    const first = images[0];
    const main = first
        ? `<button class="product-gallery__zoom" type="button" data-gallery-zoom aria-label="Zoom product image"><img id="product-main-image" src="${escapeHtml(first.source)}" alt="${escapeHtml(first.alt_text || product.name)}" width="1000" height="1250" fetchpriority="high" decoding="async"></button>`
        : '<div class="catalogue-image-placeholder catalogue-image-placeholder--large"><span>Product imagery coming soon</span></div>';
    const thumbs = images.length > 1 ? `<div class="product-gallery__thumbs" aria-label="Product images">${images.map((image, index) => `<button class="gallery-thumb ${index === 0 ? 'is-active' : ''}" type="button" data-gallery-thumb data-gallery-src="${escapeHtml(image.source)}" data-gallery-alt="${escapeHtml(image.alt_text || product.name)}" aria-label="View image ${index + 1}"><img src="${escapeHtml(image.source)}" alt="" width="120" height="150" loading="lazy"></button>`).join('')}</div>` : '';
    return `<div class="product-gallery"><div class="product-gallery__main">${main}</div>${thumbs}</div>`;
}

function renderVariantForm(product, csrfToken, price, stock) {
    if (!product.variants.length) return '<p class="muted">This piece is not currently available.</p>';
    const sizeOptions = new Map();
    const colorOptions = new Map();
    for (const variant of product.variants) {
        if (variant.size_id !== null && variant.size_id !== undefined) sizeOptions.set(String(variant.size_id), variant.size_name || variant.size_code || 'Size');
        else sizeOptions.set('null', 'One size');
        if (variant.color_id !== null && variant.color_id !== undefined) colorOptions.set(String(variant.color_id), variant);
        else colorOptions.set('null', { color_name: 'Natural', hex_code: '#D5B29A' });
    }
    const sizes = [...sizeOptions].map(([id, label]) => `<button class="size-option" type="button" data-size-id="${escapeHtml(id)}">${escapeHtml(label)}</button>`).join('');
    const colors = [...colorOptions].map(([id, color]) => {
        const hex = /^#[0-9a-f]{6}$/i.test(String(color.hex_code || '')) ? color.hex_code : '#D79E75';
        return `<button class="color-option" type="button" data-color-id="${escapeHtml(id)}" style="--swatch:${hex}" aria-label="${escapeHtml(color.color_name || 'Colour')}"><span class="sr-only">${escapeHtml(color.color_name || 'Colour')}</span></button>`;
    }).join('');
    const variants = escapeHtml(JSON.stringify(product.variants));
    return `<form class="variant-form" data-variant-form data-cart-form data-default-price="${price.toFixed(2)}" data-currency="${escapeHtml(product.currency || 'INR')}" data-variants="${variants}" action="/api/cart.php" method="post"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="action" value="add"><input type="hidden" name="variant_id" data-selected-variant><div class="variant-group" data-variant-group="size"><div class="variant-group__header"><span class="label">Size</span><span class="muted" data-selected-size>Select a size</span></div><div class="variant-options">${sizes}</div></div>${colors ? `<div class="variant-group" data-variant-group="color"><div class="variant-group__header"><span class="label">Colour</span><span class="muted" data-selected-color>Select a colour</span></div><div class="variant-options color-options">${colors}</div></div>` : ''}<label class="field product-quantity"><span class="field__label">Quantity</span><input class="input" type="number" name="quantity" value="1" min="1" max="99" required></label><div class="product-actions"><button class="button" type="submit" data-add-to-cart disabled>Add to bag <span aria-hidden="true">&rarr;</span></button><button class="link-button" type="button" data-wishlist-action="add" disabled>Save to wishlist</button></div><p class="form-message" data-cart-message aria-live="polite"></p></form>`;
}

function renderProduct(product, csrfToken) {
    const basePrice = Number(product.base_price || 0);
    const discount = Number(product.discount_value || 0);
    const price = product.discount_type === 'percentage'
        ? Math.max(0, basePrice - basePrice * discount / 100)
        : product.discount_type === 'fixed' ? Math.max(0, basePrice - discount) : basePrice;
    const symbol = ['INR', 'BDT'].includes(String(product.currency || 'INR').toUpperCase()) ? '₹' : `${product.currency || 'INR'} `;
    const priceText = `${symbol} ${price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const stock = product.variants.reduce((total, variant) => total + Number(variant.available_stock || 0), 0);
    const firstImage = product.images[0];
    const firstImageUrl = firstImage ? imageUrl(firstImage.file_path) : '';
    const images = firstImageUrl ? `<button class="product-gallery__zoom" type="button" data-gallery-zoom aria-label="Zoom product image"><img id="product-main-image" src="${escapeHtml(firstImageUrl)}" alt="${escapeHtml(firstImage.alt_text || product.name)}" width="1000" height="1250" fetchpriority="high" decoding="async"></button>` : '';
    const variantOptions = product.variants.map((variant) => {
        const label = [variant.size_name || variant.size_code, variant.color_name].filter(Boolean).join(' / ') || variant.sku;
        const unavailable = Number(variant.available_stock) < 1;
        return `<option value="${Number(variant.id)}" ${unavailable ? 'disabled' : ''}>${escapeHtml(label)}${unavailable ? ' - unavailable' : ''}</option>`;
    }).join('');
    const description = escapeHtml(product.description || product.short_description || '').replace(/\r?\n/g, '<br>');
    const jsonLd = JSON.stringify({ '@context': 'https://schema.org', '@type': 'Product', name: product.name, description: product.short_description || product.name, image: product.images.map((image) => imageUrl(image.file_path)).filter(Boolean), brand: { '@type': 'Brand', name: product.brand_name || "Sanzida's Closet" }, offers: { '@type': 'Offer', priceCurrency: product.currency || 'INR', price: price.toFixed(2), availability: stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', url: `/products/${encodeURIComponent(product.slug)}/` } }).replace(/</g, '\\u003c');
    const content = `<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><a href="/products/">Shop</a><span aria-hidden="true">/</span><span aria-current="page">${escapeHtml(product.name)}</span></nav><section class="product-detail"><div class="product-gallery"><div class="product-gallery__main">${images || '<div class="catalogue-image-placeholder catalogue-image-placeholder--large"><span>Product imagery coming soon</span></div>'}</div></div><div class="product-detail__info"><p class="eyebrow">${escapeHtml(product.brand_name || "Sanzida's Closet")}</p><h1>${escapeHtml(product.name)}</h1><p class="product-detail__short">${escapeHtml(product.short_description || '')}</p><div class="product-detail__price"><strong>${escapeHtml(priceText)}</strong>${Number(product.compare_at_price) > price ? `<del>${escapeHtml(`${symbol} ${Number(product.compare_at_price).toFixed(2)}`)}</del>` : ''}</div><p class="product-stock ${stock > 0 ? 'is-available' : 'is-unavailable'}">${stock > 0 ? 'In stock' : 'Currently unavailable'}</p><div class="product-description">${description}</div>${variantOptions ? `<form class="variant-form" data-cart-form action="/api/cart.php" method="post"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="action" value="add"><label class="field"><span class="field__label">Size / colour</span><select class="select" name="variant_id" data-selected-variant required><option value="">Choose an option</option>${variantOptions}</select></label><label class="field"><span class="field__label">Quantity</span><input class="input" type="number" name="quantity" value="1" min="1" max="99" required></label><button class="button" type="submit" data-add-to-cart ${stock < 1 ? 'disabled' : ''}>Add to bag <span aria-hidden="true">&rarr;</span></button><button class="link-button" type="button" data-wishlist-action="add">Save to wishlist</button><p class="form-message" data-cart-message aria-live="polite"></p></form>` : '<p class="muted">This piece is not currently available.</p>'}${product.categories.length ? `<div class="product-tags">${product.categories.map((category) => `<a class="badge" href="/products/?category=${encodeURIComponent(category.slug)}">${escapeHtml(category.name)}</a>`).join('')}</div>` : ''}</div></section><script type="application/ld+json">${jsonLd}</script>`;
    const galleryMarkup = renderProductGallery(product);
    const variantForm = renderVariantForm(product, csrfToken, price, stock)
        .replace('data-variant-form data-cart-form', 'data-product-variant-form data-product-cart-form')
        .replace('data-wishlist-action="add"', 'data-product-wishlist');
    const improvedContent = content
        .replace(/<div class="product-gallery">[\s\S]*?<\/div><div class="product-detail__info">/, `${galleryMarkup}<div class="product-detail__info">`)
        .replace(/<form class="variant-form" data-cart-form[\s\S]*?<\/form>/, variantForm)
        .replace('<p class="muted">This piece is not currently available.</p>', variantForm);
    const productView = JSON.stringify({ slug: product.slug, name: product.name, price, currency: product.currency || 'INR', url: `/products/${encodeURIComponent(product.slug)}/`, image: firstImageUrl }).replace(/</g, '\\u003c');
    const zoomDialog = '<dialog class="image-zoom-dialog" data-image-dialog><button class="icon-button" type="button" data-image-dialog-close aria-label="Close enlarged image"><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button><button class="image-zoom-dialog__arrow image-zoom-dialog__arrow--prev" type="button" data-image-dialog-prev aria-label="Previous product image">&larr;</button><img data-image-dialog-image alt=""><button class="image-zoom-dialog__arrow image-zoom-dialog__arrow--next" type="button" data-image-dialog-next aria-label="Next product image">&rarr;</button></dialog>';
    return pageShell(product.name, improvedContent)
        .replace('<script src="/assets/js/catalogue.js"></script>', '')
        .replace('<body class="catalogue-page">', `<body class="catalogue-page product-detail-page" data-cart-api="/api/cart.php" data-wishlist-api="/api/wishlist.php" data-product-view="${escapeHtml(productView)}">`)
        .replace('</body>', `${zoomDialog}<script src="/assets/js/product-page.js"></script></body>`);
}

function renderErrorPage(title, message) {
    return pageShell(title, `<div class="alert alert--soft"><strong>${escapeHtml(title)}.</strong><p>${escapeHtml(message)}</p></div>`);
}

module.exports = { renderCatalogue, renderCategories, renderErrorPage, renderProduct };