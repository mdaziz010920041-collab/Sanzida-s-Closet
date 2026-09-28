(() => {
    const body = document.body;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[character]));

    const drawer = document.querySelector('#mobile-drawer');
    const backdrop = document.querySelector('.drawer-backdrop');
    const setDrawer = (open) => {
        if (!drawer) return;
        drawer.classList.toggle('is-open', open);
        drawer.setAttribute('aria-hidden', String(!open));
        drawer.inert = !open;
        document.querySelector('.mobile-menu-trigger')?.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
        body.classList.toggle('is-locked', open);
    };
    document.querySelector('.mobile-menu-trigger')?.addEventListener('click', () => setDrawer(true));
    document.querySelectorAll('[data-menu-close]').forEach((button) => button.addEventListener('click', () => setDrawer(false)));
    backdrop?.addEventListener('click', () => setDrawer(false));

    const searchPanel = document.querySelector('#search-panel');
    const searchTrigger = document.querySelector('[data-search-trigger]');
    const closeSearch = () => {
        if (!searchPanel) return;
        searchPanel.hidden = true;
        searchTrigger?.setAttribute('aria-expanded', 'false');
        body.classList.remove('is-locked');
    };
    searchTrigger?.addEventListener('click', () => {
        if (!searchPanel) return;
        setDrawer(false);
        searchPanel.hidden = false;
        searchTrigger.setAttribute('aria-expanded', 'true');
        body.classList.add('is-locked');
        searchPanel.querySelector('input')?.focus();
    });
    document.querySelector('[data-search-close]')?.addEventListener('click', closeSearch);
    document.querySelector('.announcement-bar__close')?.addEventListener('click', (event) => event.currentTarget.closest('.announcement-bar')?.remove());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setDrawer(false);
            closeSearch();
        }
    });

    const searchInput = document.querySelector('[data-search-input]');
    const suggestions = document.querySelector('[data-search-suggestions]');
    let suggestionTimer;
    searchInput?.addEventListener('input', () => {
        window.clearTimeout(suggestionTimer);
        const term = searchInput.value.trim();
        if (!suggestions || term.length < 2) {
            if (suggestions) suggestions.hidden = true;
            return;
        }
        suggestionTimer = window.setTimeout(async () => {
            try {
                const response = await fetch(`${searchInput.dataset.suggestionsUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                suggestions.innerHTML = data.items?.length
                    ? data.items.map((item) => `<a href="${escapeHtml(item.url)}"><span>${escapeHtml(item.name)}</span>${item.sku ? `<small>${escapeHtml(item.sku)}</small>` : ''}</a>`).join('')
                    : '<p class="search-suggestions__empty">No matching pieces</p>';
                suggestions.hidden = false;
            } catch {
                suggestions.hidden = true;
            }
        }, 220);
    });

    const form = document.querySelector('[data-product-variant-form]');
    if (form) {
        const variants = JSON.parse(form.dataset.variants || '[]');
        const selected = { size: '', color: '' };
        const sizeOptions = [...form.querySelectorAll('[data-size-id]')];
        const colorOptions = [...form.querySelectorAll('[data-color-id]')];
        const updateVariant = () => {
            const matching = variants.filter((variant) => (!selected.size || String(variant.size_id) === selected.size) && (!selected.color || String(variant.color_id) === selected.color));
            const requiresSize = variants.some((variant) => variant.size_id !== null);
            const requiresColor = variants.some((variant) => variant.color_id !== null);
            const complete = (!requiresSize || selected.size) && (!requiresColor || selected.color);
            const variant = complete ? matching.find((item) => Number(item.available_stock) > 0) || matching[0] : null;
            const hidden = form.querySelector('[data-selected-variant]');
            const price = document.querySelector('.product-detail__price strong');
            const stock = document.querySelector('.product-stock');
            const addButton = form.querySelector('[data-add-to-cart]');
            const wishlistButton = form.querySelector('[data-product-wishlist]');
            if (hidden) hidden.value = variant?.id || '';
            if (stock) {
                stock.textContent = variant ? (Number(variant.available_stock) > 0 ? `${variant.available_stock} available` : 'Currently unavailable') : 'Select available options';
                stock.classList.toggle('is-available', Boolean(variant && Number(variant.available_stock) > 0));
                stock.classList.toggle('is-unavailable', Boolean(variant && Number(variant.available_stock) <= 0));
            }
            if (price && variant?.price_override !== null && variant?.price_override !== undefined) price.textContent = `${form.dataset.currency} ${Number(variant.price_override).toFixed(2)}`;
            if (addButton) addButton.disabled = !variant || Number(variant.available_stock) < 1;
            if (wishlistButton) {
                wishlistButton.disabled = !variant;
                wishlistButton.dataset.variantId = variant?.id || '';
            }
        };
        sizeOptions.forEach((button) => button.addEventListener('click', () => {
            selected.size = button.dataset.sizeId || '';
            sizeOptions.forEach((option) => option.classList.toggle('is-selected', option === button));
            const label = form.querySelector('[data-selected-size]');
            if (label) label.textContent = button.textContent.trim();
            updateVariant();
        }));
        colorOptions.forEach((button) => button.addEventListener('click', () => {
            selected.color = button.dataset.colorId || '';
            colorOptions.forEach((option) => option.classList.toggle('is-selected', option === button));
            const label = form.querySelector('[data-selected-color]');
            if (label) label.textContent = button.querySelector('.sr-only')?.textContent || 'Selected';
            updateVariant();
        }));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('[data-add-to-cart]');
            const message = form.querySelector('[data-cart-message]');
            if (!form.querySelector('[data-selected-variant]')?.value) {
                if (message) message.textContent = 'Choose an available size and colour first.';
                return;
            }
            button.disabled = true;
            try {
                const response = await fetch('/api/cart.php', { method: 'POST', body: new URLSearchParams(new FormData(form)), headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Could not add this item to your bag.');
                if (message) message.textContent = result.message;
                const cartCount = document.querySelector('a[href="/cart/"] .count-badge');
                if (cartCount) cartCount.textContent = String(result.cart.item_count);
            } catch (error) {
                if (message) message.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });

        form.querySelector('[data-product-wishlist]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const message = form.querySelector('[data-cart-message]');
            const variantId = button.dataset.variantId || form.querySelector('[data-selected-variant]')?.value;
            if (!variantId) {
                if (message) message.textContent = 'Choose an available size and colour first.';
                return;
            }
            try {
                const response = await fetch('/api/wishlist.php', { method: 'POST', body: new URLSearchParams({ action: 'add', variant_id: variantId, csrf_token: form.querySelector('[name="csrf_token"]').value }), headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (response.status === 401 && result.login_url) {
                    window.location.href = result.login_url;
                    return;
                }
                if (!response.ok || !result.success) throw new Error(result.message || 'Could not save this piece.');
                if (message) message.textContent = result.message;
                button.textContent = 'Saved to wishlist';
            } catch (error) {
                if (message) message.textContent = error.message;
            }
        });

        updateVariant();
    }

    const mainImage = document.querySelector('#product-main-image');
    const thumbs = [...document.querySelectorAll('[data-gallery-thumb]')];
    thumbs.forEach((thumb) => thumb.addEventListener('click', () => {
        if (!mainImage) return;
        mainImage.src = thumb.dataset.gallerySrc || '';
        mainImage.alt = thumb.dataset.galleryAlt || '';
        thumbs.forEach((item) => item.classList.toggle('is-active', item === thumb));
    }));
    const dialog = document.querySelector('[data-image-dialog]');
    const dialogImage = dialog?.querySelector('[data-image-dialog-image]');
    let dialogIndex = 0;
    const showDialogImage = (index) => {
        if (!dialogImage || !thumbs.length) return;
        dialogIndex = (index + thumbs.length) % thumbs.length;
        dialogImage.src = thumbs[dialogIndex].dataset.gallerySrc || '';
        dialogImage.alt = thumbs[dialogIndex].dataset.galleryAlt || '';
    };
    document.querySelector('[data-gallery-zoom]')?.addEventListener('click', () => {
        if (!dialog || !mainImage) return;
        showDialogImage(Math.max(0, thumbs.findIndex((thumb) => thumb.dataset.gallerySrc === mainImage.src)));
        dialog.showModal();
    });
    document.querySelector('[data-image-dialog-prev]')?.addEventListener('click', () => showDialogImage(dialogIndex - 1));
    document.querySelector('[data-image-dialog-next]')?.addEventListener('click', () => showDialogImage(dialogIndex + 1));
    document.querySelector('[data-image-dialog-close]')?.addEventListener('click', () => dialog?.close());
})();