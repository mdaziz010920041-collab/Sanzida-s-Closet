const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
}[character]));

function money(currency, amount) {
    const symbol = ['INR', 'BDT'].includes(String(currency || 'INR').toUpperCase()) ? '₹' : `${currency || 'INR'} `;
    return `${symbol} ${Number(amount || 0).toFixed(2)}`;
}

function renderCartPage(cart, csrfToken) {
    const lines = cart.items.map((item) => {
        const source = String(item.image_path || '');
        const localPath = source.replace(/^\/+/, '');
        const image = /^https:\/\//i.test(source) ? source : /^uploads\/[A-Za-z0-9_./-]+$/.test(localPath) && !localPath.split('/').some((segment) => segment === '.' || segment === '..') ? `/${localPath}` : '';
        return `<article class="cart-page-line" data-cart-line data-variant-id="${Number(item.variant_id)}"><div class="cart-page-line__image">${image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(item.image_alt || item.name)}" width="160" height="200" loading="lazy">` : '<div class="catalogue-image-placeholder"><span>Image coming soon</span></div>'}</div><div class="cart-page-line__content"><p class="label">${escapeHtml(item.size_name || item.color_name || 'Selected piece')}</p><h2>${escapeHtml(item.name)}</h2><p class="muted">${escapeHtml(item.currency)} / ${escapeHtml(item.variant_id)}</p><div class="cart-page-line__bottom"><strong>${escapeHtml(money(item.currency, item.unit_price))}</strong><div class="quantity-control" aria-label="Quantity"><button type="button" data-cart-quantity="decrease" aria-label="Decrease quantity">&minus;</button><span data-cart-quantity-value>${item.quantity}</span><button type="button" data-cart-quantity="increase" aria-label="Increase quantity">+</button></div><button class="link-button" type="button" data-cart-remove>Remove</button></div><p class="cart-line__stock ${item.available_stock > 0 ? 'is-available' : 'is-unavailable'}">${item.available_stock > 0 ? `${item.available_stock} available` : 'Currently unavailable'}</p></div></article>`;
    }).join('');
    const content = cart.items.length
        ? `<div class="cart-layout"><section class="cart-items" aria-label="Bag items"><div data-cart-page-items>${lines}</div></section><aside class="cart-summary"><p class="eyebrow">Summary</p><h2>Your total.</h2><div class="cart-summary__rows"><div><span>Subtotal</span><strong>${escapeHtml(money('INR', cart.subtotal))}</strong></div>${cart.discount ? `<div><span>Discount</span><strong>- ${escapeHtml(money('INR', cart.discount))}</strong></div>` : ''}<div><span>Delivery</span><strong>${cart.shipping ? escapeHtml(money('INR', cart.shipping)) : 'Complimentary'}</strong></div><div class="cart-summary__total"><span>Total</span><strong>${escapeHtml(money('INR', cart.total))}</strong></div></div><form class="coupon-form" data-coupon-form><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><label class="field__label" for="coupon-code">Promo code</label><input class="input" id="coupon-code" name="code" autocomplete="off"><button class="button button--outline" type="submit">Apply</button><p data-coupon-message aria-live="polite"></p></form><a class="button button--full" href="/checkout/">Proceed to checkout <span aria-hidden="true">&rarr;</span></a><p class="muted cart-summary__note">Shipping and discounts are confirmed at checkout.</p></aside></div>`
        : '<section class="empty-state"><p class="eyebrow">Nothing here yet</p><h2>Your bag is waiting.</h2><p>Explore the catalogue and save a piece for later.</p><a class="button" href="/products/">Browse the edit <span aria-hidden="true">&rarr;</span></a></section>';
    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your bag | Sanzida's Closet</title><link rel="stylesheet" href="/assets/css/site.css"></head><body class="cart-page" data-cart-page data-cart-api="/api/cart.php" data-wishlist-api="/api/wishlist.php"><header class="site-header"><a class="brand" href="/"><img src="/assets/images/brand-logo.jpg" width="44" height="44" alt=""><span class="brand-name">Sanzida's Closet</span></a><nav aria-label="Primary navigation"><a href="/products/">Shop</a><a href="/new-arrivals/">New arrivals</a><a href="/categories/">Collections</a><a href="/sale/">Sale</a></nav><a class="header-link header-cart-link" href="/cart/">Cart <span class="count-badge">${cart.item_count}</span></a></header><main class="cart-main"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><section class="cart-heading"><p class="eyebrow">Your edit</p><h1>Your shopping<br><em>bag.</em></h1><p class="muted" data-cart-count>${cart.item_count} items</p></section>${content}</main><footer class="site-footer"><a href="/">Sanzida's Closet</a><a href="/returns-exchanges/">Returns &amp; exchanges</a><span>&copy; ${new Date().getFullYear()}</span></footer><script src="/assets/js/cart.js" defer></script></body></html>`;
}

module.exports = { renderCartPage };