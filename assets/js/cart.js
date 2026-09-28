document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.querySelector('[data-cart-drawer]');
    const backdrop = document.querySelector('.cart-backdrop');
    const body = document.body;
    const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
    const api = document.body.dataset.cartApi || 'api/cart.php';
    const wishlistApi = document.body.dataset.wishlistApi || 'api/wishlist.php';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>\'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));

    const formatMoney = (value) => `₹ ${Number(value || 0).toFixed(2)}`;
    let demoProduct = null;
    try {
        const productView = JSON.parse(document.body.dataset.productView || 'null');
        if (productView?.slug?.startsWith('demo-product-')) demoProduct = productView;
    } catch (error) {
        demoProduct = null;
    }
    const setDrawer = (open) => {
        if (!drawer) return;
        drawer.classList.toggle('is-open', open);
        drawer.setAttribute('aria-hidden', String(!open));
        if (backdrop) backdrop.hidden = !open;
        body.classList.toggle('is-locked', open);
        if (open) drawer.querySelector('[data-cart-close]')?.focus();
    };
    document.querySelector('[data-cart-open]')?.addEventListener('click', () => setDrawer(true));
    document.querySelectorAll('[data-cart-close]').forEach((button) => button.addEventListener('click', () => setDrawer(false)));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setDrawer(false); });

    const renderDrawerItems = (cart) => {
        const target = document.querySelector('[data-cart-items]');
        if (!target) return;
        target.innerHTML = cart.items.length ? cart.items.map((item) => `<div class="cart-line"><div class="cart-line__image">${item.image_path ? `<img src="${escapeHtml(item.image_path)}" alt="${escapeHtml(item.image_alt || item.name)}" width="80" height="100" loading="lazy" decoding="async">` : '<span>SC</span>'}</div><div class="cart-line__content"><strong>${escapeHtml(item.name)}</strong><span>${escapeHtml(item.size_name || item.color_name || '')}</span><span>${formatMoney(item.unit_price)} &times; ${escapeHtml(item.quantity)}</span></div></div>`).join('') : '<p class="muted cart-empty">Your bag is waiting for its first piece.</p>';
        const subtotal = document.querySelector('[data-cart-subtotal]');
        if (subtotal) subtotal.textContent = formatMoney(cart.subtotal);
        document.querySelectorAll('[data-cart-count]').forEach((item) => { item.textContent = `${cart.item_count} items`; });
        document.querySelector('.header-cart-link .count-badge')?.replaceChildren(document.createTextNode(String(cart.item_count)));
    };

    const cartPage = document.querySelector('[data-cart-page]');
    let demoCartPage = false;
    if (cartPage?.dataset.cartServerError === '1') {
        try {
            const storedItems = JSON.parse(localStorage.getItem('sc_demo_cart') || '[]');
            if (storedItems.length > 0) {
                demoCartPage = true;
                const total = storedItems.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.quantity || 0)), 0);
                const alert = cartPage.querySelector('.alert');
                if (alert) alert.outerHTML = `<div class="cart-layout"><section class="cart-items" aria-labelledby="demo-cart-items-title"><h2 class="sr-only" id="demo-cart-items-title">Bag items</h2><div data-cart-page-items>${storedItems.map((item) => `<article class="cart-page-line" data-cart-line data-demo-slug="${escapeHtml(item.slug)}"><div class="cart-page-line__image"><img src="${escapeHtml(item.image || '')}" alt="${escapeHtml(item.alt || item.name)}" width="160" height="200"></div><div class="cart-page-line__content"><p class="label">Sanzida's Closet</p><h2>${escapeHtml(item.name)}</h2><p class="muted">Demo product</p><div class="cart-page-line__bottom"><strong>${formatMoney(item.price)}</strong><div class="quantity-control" aria-label="Quantity"><button type="button" data-cart-quantity="decrease" aria-label="Decrease quantity">&minus;</button><span data-cart-quantity-value>${escapeHtml(item.quantity)}</span><button type="button" data-cart-quantity="increase" aria-label="Increase quantity">+</button></div><button class="link-button" type="button" data-cart-remove>Remove</button></div><p class="cart-line__stock is-available">Available</p></div></article>`).join('')}</div></section><aside class="cart-summary" aria-labelledby="demo-summary-title"><p class="eyebrow">Summary</p><h2 id="demo-summary-title">Your total.</h2><div class="cart-summary__rows"><div class="cart-summary__total"><span>Total</span><strong>${formatMoney(total)}</strong></div></div><a class="button button--full" href="${escapeHtml(`${window.location.origin}/checkout/`)}">Proceed to checkout <span aria-hidden="true">&rarr;</span></a><p class="muted cart-summary__note">Connect MySQL to place a live order.</p></aside></div>`;
                document.querySelector('[data-cart-count]')?.replaceChildren(document.createTextNode(`${storedItems.reduce((sum, item) => sum + Number(item.quantity || 0), 0)} items`));
            }
        } catch (error) {
            localStorage.removeItem('sc_demo_cart');
        }
    }

    const requestCart = async (formData) => {
        formData.append('csrf_token', csrf);
        const response = await fetch(api, { method: 'POST', body: formData, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Cart update failed.');
        return data;
    };

    document.querySelector('[data-cart-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const message = form.querySelector('[data-cart-message]');
        const button = form.querySelector('[data-add-to-cart]');
        button.disabled = true;
        form.classList.add('is-submitting');
        try {
            if (demoProduct) {
                const quantity = Number(form.querySelector('[name="quantity"]')?.value || 1);
                const demoCart = JSON.parse(localStorage.getItem('sc_demo_cart') || '[]');
                const existing = demoCart.find((item) => item.slug === demoProduct.slug);
                if (existing) existing.quantity += quantity;
                else demoCart.push({ ...demoProduct, quantity });
                localStorage.setItem('sc_demo_cart', JSON.stringify(demoCart));
                const demoItems = demoCart.map((item) => ({ ...item, unit_price: Number(item.price || 0), image_path: item.image || '', image_alt: item.alt || item.name }));
                renderDrawerItems({ items: demoItems, item_count: demoItems.reduce((total, item) => total + item.quantity, 0), subtotal: demoItems.reduce((total, item) => total + (item.unit_price * item.quantity), 0) });
                if (message) message.textContent = 'Added to your bag.';
                body.classList.add('cart-added');
                window.setTimeout(() => body.classList.remove('cart-added'), 500);
                setDrawer(true);
                return;
            }
            const data = await requestCart(new FormData(form));
            if (window.analytics_event && data.added?.item) window.analytics_event('add_to_cart', {currency: data.added.item.currency || 'INR', value: Number(data.added.item.unit_price || 0) * Number(form.querySelector('[name="quantity"]')?.value || 1), items: [{item_id: data.added.item.sku || data.added.item.variant_id, item_name: data.added.item.name, quantity: Number(form.querySelector('[name="quantity"]')?.value || 1)}]});
            if (message) message.textContent = data.message;
            renderDrawerItems(data.cart);
            body.classList.add('cart-added');
            window.setTimeout(() => body.classList.remove('cart-added'), 500);
            setDrawer(true);
        } catch (error) {
            if (message) message.textContent = error.message;
        } finally {
            form.classList.remove('is-submitting');
            if (form.querySelector('[data-selected-variant]')?.value) button.disabled = false;
        }
    });

    const updateCart = async (action, variantId, quantity) => {
        const data = new FormData();
        data.append('action', action); data.append('variant_id', variantId); if (quantity !== undefined) data.append('quantity', quantity);
        return requestCart(data);
    };
    document.querySelectorAll('[data-cart-line]').forEach((line) => {
        const variantId = line.dataset.variantId;
        line.querySelectorAll('[data-cart-quantity]').forEach((button) => button.addEventListener('click', async () => {
            const current = Number(line.querySelector('[data-cart-quantity-value]')?.textContent || 1);
            const next = current + (button.dataset.cartQuantity === 'increase' ? 1 : -1);
            if (next < 1) return;
            if (demoCartPage) {
                const items = JSON.parse(localStorage.getItem('sc_demo_cart') || '[]');
                const item = items.find((entry) => entry.slug === line.dataset.demoSlug);
                if (item) item.quantity = next;
                localStorage.setItem('sc_demo_cart', JSON.stringify(items));
                window.location.reload();
                return;
            }
            try { const data = await updateCart('update', variantId, next); renderDrawerItems(data.cart); window.location.reload(); } catch (error) { window.alert(error.message); }
        }));
        line.querySelector('[data-cart-remove]')?.addEventListener('click', async () => {
            if (demoCartPage) {
                const items = JSON.parse(localStorage.getItem('sc_demo_cart') || '[]').filter((entry) => entry.slug !== line.dataset.demoSlug);
                localStorage.setItem('sc_demo_cart', JSON.stringify(items));
                window.location.reload();
                return;
            }
            try { await updateCart('remove', variantId, 0); if (window.analytics_event) window.analytics_event('remove_from_cart', {items: [{item_id: variantId, quantity: 1}]}); window.location.reload(); } catch (error) { window.alert(error.message); }
        });
    });

    document.querySelector('[data-coupon-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const message = form.querySelector('[data-coupon-message]');
        const data = new FormData(form); data.append('action', 'apply_coupon');
        try { const response = await requestCart(data); if (message) message.textContent = response.message; window.location.reload(); } catch (error) { if (message) message.textContent = error.message; }
    });

    document.querySelectorAll('[data-wishlist-action]').forEach((button) => button.addEventListener('click', async () => {
        if (demoProduct) {
            const saved = JSON.parse(localStorage.getItem('sc_demo_wishlist') || '[]');
            if (!saved.some((item) => item.slug === demoProduct.slug)) saved.push(demoProduct);
            localStorage.setItem('sc_demo_wishlist', JSON.stringify(saved));
            button.textContent = 'Saved to wishlist';
            button.disabled = true;
            return;
        }
        const data = new FormData(); data.append('action', button.dataset.wishlistAction || 'add'); data.append('variant_id', button.dataset.variantId || document.querySelector('[data-selected-variant]')?.value || ''); data.append('csrf_token', csrf);
        try { const response = await fetch(wishlistApi, { method: 'POST', body: data, headers: { Accept: 'application/json' } }); const result = await response.json(); if (response.status === 401 && result.login_url) window.location.href = result.login_url; else if (!response.ok || !result.success) throw new Error(result.message); if (window.analytics_event) window.analytics_event('add_to_wishlist', {items: [{item_id: button.dataset.variantId || document.querySelector('[data-selected-variant]')?.value || ''}]}); button.textContent = button.dataset.wishlistAction === 'move_to_cart' ? 'Moved to bag' : 'Saved to wishlist'; } catch (error) { button.insertAdjacentHTML('afterend', `<p class="form-message">${escapeHtml(error.message)}</p>`); }
    }));
});