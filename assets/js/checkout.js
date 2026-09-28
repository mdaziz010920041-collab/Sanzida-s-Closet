document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.checkoutServerError !== '1') return;

    let items;
    try {
        items = JSON.parse(localStorage.getItem('sc_demo_cart') || '[]');
    } catch (error) {
        items = [];
    }
    const emptyState = document.querySelector('.empty-state');
    if (!emptyState) return;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));

    if (!Array.isArray(items) || items.length === 0) {
        try {
            const demoOrder = JSON.parse(localStorage.getItem('sc_demo_order') || 'null');
            if (demoOrder?.orderNumber) {
                document.querySelector('.checkout-main > .alert')?.remove();
                emptyState.outerHTML = `<section class="empty-state"><p class="eyebrow">Demo order received</p><h1>Thank you for your order.</h1><p>Your demo order number is <strong>${escapeHtml(demoOrder.orderNumber)}</strong>.</p><p class="muted">Connect MySQL and Razorpay to turn this preview into a live order and payment.</p><a class="button" href="../products/">Continue shopping <span aria-hidden="true">&rarr;</span></a></section>`;
            }
        } catch (error) {
            localStorage.removeItem('sc_demo_order');
        }
        return;
    }

    const total = items.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.quantity || 0)), 0);
    const orderNumber = `DEMO-${Date.now().toString(36).toUpperCase()}`;
    document.querySelector('.checkout-main > .alert')?.remove();

    emptyState.outerHTML = `<section class="checkout-layout demo-checkout" aria-labelledby="demo-checkout-title"><section class="checkout-form"><div class="checkout-panel"><p class="eyebrow">Local demo checkout</p><h1 id="demo-checkout-title">Delivery details.</h1><p class="muted">This preview checkout works without MySQL. Live order creation and Razorpay payment activate after the database is connected.</p><form class="demo-checkout-form"><div class="field"><label class="field__label" for="demo-email">Email address</label><input class="input" id="demo-email" type="email" required autocomplete="email"></div><div class="form-grid"><div class="field"><label class="field__label" for="demo-name">Recipient name</label><input class="input" id="demo-name" required autocomplete="name"></div><div class="field"><label class="field__label" for="demo-phone">Phone</label><input class="input" id="demo-phone" required autocomplete="tel"></div><div class="field field--wide"><label class="field__label" for="demo-address">Address</label><input class="input" id="demo-address" required autocomplete="street-address"></div><div class="field"><label class="field__label" for="demo-city">City</label><input class="input" id="demo-city" required autocomplete="address-level2"></div></div><button class="button" type="submit">Place demo order <span aria-hidden="true">&rarr;</span></button><p class="form-message" data-demo-checkout-message aria-live="polite"></p></form></div></section><aside class="cart-summary"><p class="eyebrow">Your bag</p><h2>Order summary.</h2><div class="cart-summary__rows">${items.map((item) => `<div><span>${escapeHtml(item.name)} &times; ${Number(item.quantity || 0)}</span><strong>₹ ${(Number(item.price || 0) * Number(item.quantity || 0)).toFixed(2)}</strong></div>`).join('')}<div class="cart-summary__total"><span>Total</span><strong>₹ ${total.toFixed(2)}</strong></div></div></aside></section>`;

    document.querySelector('.demo-checkout-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const message = form.querySelector('[data-demo-checkout-message]');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        localStorage.setItem('sc_demo_order', JSON.stringify({ orderNumber, items, total, createdAt: new Date().toISOString() }));
        localStorage.removeItem('sc_demo_cart');
        form.innerHTML = `<div class="confirmation-hero"><p class="eyebrow">Demo order received</p><h2>Thank you for your order.</h2><p>Your demo order number is <strong>${escapeHtml(orderNumber)}</strong>.</p><p class="muted">Connect MySQL and Razorpay to turn this preview into a live order and payment.</p><a class="button" href="../products/">Continue shopping <span aria-hidden="true">&rarr;</span></a></div>`;
        if (message) message.textContent = '';
    });
});
