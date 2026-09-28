document.addEventListener('DOMContentLoaded', () => {
    const storageKey = 'sanzidas_closet_recent_products';

    const readRecent = () => {
        try {
            const value = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(value) ? value : [];
        } catch (error) {
            return [];
        }
    };

    const writeRecent = (product) => {
        const recent = readRecent().filter((item) => item.slug !== product.slug);
        recent.unshift(product);
        localStorage.setItem(storageKey, JSON.stringify(recent.slice(0, 6)));
    };

    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[character]));

    const searchInput = document.querySelector('[data-search-input]');
    const suggestions = document.querySelector('[data-search-suggestions]');
    document.querySelector('.catalogue-search')?.addEventListener('submit', () => {
        if (window.analytics_event && searchInput?.value.trim()) window.analytics_event('search', {search_term: searchInput.value.trim()});
    });
    let suggestionTimer = null;
    searchInput?.addEventListener('input', () => {
        window.clearTimeout(suggestionTimer);
        const term = searchInput.value.trim();
        if (!suggestions || term.length < 2) {
            if (suggestions) suggestions.hidden = true;
            return;
        }
        suggestionTimer = window.setTimeout(async () => {
            suggestions.innerHTML = '<span class="skeleton skeleton--line" aria-hidden="true"></span><span class="skeleton skeleton--line" aria-hidden="true"></span>';
            suggestions.hidden = false;
            try {
                const response = await fetch(`${searchInput.dataset.suggestionsUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Suggestion request failed');
                const data = await response.json();
                suggestions.innerHTML = data.items?.length ? data.items.map((item) => `<a href="${escapeHtml(item.url)}"><span>${escapeHtml(item.name)}</span>${item.sku ? `<small>${escapeHtml(item.sku)}</small>` : ''}</a>`).join('') : '<p class="search-suggestions__empty">No matching pieces</p>';
                suggestions.hidden = false;
            } catch (error) {
                suggestions.hidden = true;
            }
        }, 220);
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.catalogue-search') && suggestions) suggestions.hidden = true;
    });

    const filterDrawer = document.querySelector('#catalogue-filters');
    const filterBackdrop = document.querySelector('.filter-backdrop');
    const filterTrigger = document.querySelector('[data-filter-open]');
    const filterCloseButtons = document.querySelectorAll('[data-filter-close]');
    const syncFilterAccessibility = () => {
        if (filterDrawer && window.innerWidth > 767) {
            filterDrawer.setAttribute('aria-hidden', 'false');
        } else if (filterDrawer && !filterDrawer.classList.contains('is-open')) {
            filterDrawer.setAttribute('aria-hidden', 'true');
        }
    };
    syncFilterAccessibility();
    window.addEventListener('resize', syncFilterAccessibility, { passive: true });
    const closeFilters = () => {
        if (!filterDrawer) return;
        filterDrawer.classList.remove('is-open');
        filterDrawer.setAttribute('aria-hidden', 'true');
        filterTrigger?.setAttribute('aria-expanded', 'false');
        if (filterBackdrop) filterBackdrop.hidden = true;
        document.body.classList.remove('is-locked');
    };
    filterTrigger?.addEventListener('click', () => {
        if (!filterDrawer) return;
        filterDrawer.classList.add('is-open');
        filterDrawer.setAttribute('aria-hidden', 'false');
        filterTrigger.setAttribute('aria-expanded', 'true');
        if (filterBackdrop) filterBackdrop.hidden = false;
        document.body.classList.add('is-locked');
        filterDrawer.querySelector('select, input, button')?.focus();
    });
    filterCloseButtons.forEach((button) => button.addEventListener('click', closeFilters));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeFilters();
    });

    document.querySelectorAll('[data-recent-product]').forEach((link) => {
        link.addEventListener('click', () => {
            const card = link.closest('.product-card');
            writeRecent({
                slug: link.dataset.recentProduct,
                name: card?.querySelector('.product-card__title')?.textContent?.trim() || 'Product',
                url: link.href,
                image: card?.querySelector('img')?.src || '',
                alt: card?.querySelector('img')?.alt || 'Product image'
            });
        });
    });

    const currentProduct = document.body.dataset.productView;
    if (currentProduct) {
        try {
            const product = JSON.parse(currentProduct);
            if (product.slug) {
                writeRecent(product);
                if (window.analytics_event) window.analytics_event('view_item', {currency: product.currency || 'INR', value: Number(product.price || 0), items: [{item_id: product.slug, item_name: product.name}]});
            }
        } catch (error) {
            // Invalid page data should not prevent catalogue interactions.
        }
    }

    const recentContainer = document.querySelector('[data-recent-container]');
    const recentGrid = document.querySelector('[data-recent-grid]');
    if (recentContainer && recentGrid) {
        let currentSlug = '';
        try {
            currentSlug = currentProduct ? JSON.parse(currentProduct).slug : '';
        } catch (error) {
            currentSlug = '';
        }
        const recent = readRecent().filter((item) => item.slug !== currentSlug).slice(0, 4);
        if (recent.length > 0) {
            recentGrid.innerHTML = recent.map((item) => `<article class="product-card"><a class="product-card__link" href="${escapeHtml(item.url)}"><div class="product-card__media">${item.image ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.alt || item.name)}" loading="lazy" width="640" height="800">` : '<div class="catalogue-image-placeholder"><span>Image coming soon</span></div>'}</div><div class="product-card__body"><h3 class="product-card__title">${escapeHtml(item.name)}</h3></div></a></article>`).join('');
            recentContainer.hidden = false;
        }
    }

    const mainImage = document.querySelector('#product-main-image');
    document.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => {
        thumb.addEventListener('click', () => {
            if (!mainImage) {
                return;
            }
            mainImage.src = thumb.dataset.gallerySrc || '';
            mainImage.alt = thumb.dataset.galleryAlt || '';
            mainImage.classList.remove('is-changing');
            void mainImage.offsetWidth;
            mainImage.classList.add('is-changing');
            document.querySelectorAll('[data-gallery-thumb]').forEach((item) => item.classList.remove('is-active'));
            thumb.classList.add('is-active');
        });
    });

    const imageDialog = document.querySelector('[data-image-dialog]');
    const dialogImage = document.querySelector('[data-image-dialog-image]');
    const dialogPrevious = document.querySelector('[data-image-dialog-prev]');
    const dialogNext = document.querySelector('[data-image-dialog-next]');
    const galleryThumbs = [...document.querySelectorAll('[data-gallery-thumb]')];
    let dialogIndex = 0;
    const showDialogImage = (index) => {
        if (!dialogImage || galleryThumbs.length === 0) return;
        dialogIndex = (index + galleryThumbs.length) % galleryThumbs.length;
        const thumb = galleryThumbs[dialogIndex];
        dialogImage.src = thumb.dataset.gallerySrc || '';
        dialogImage.alt = thumb.dataset.galleryAlt || '';
    };
    document.querySelector('[data-gallery-zoom]')?.addEventListener('click', () => {
        if (!imageDialog || !mainImage || !dialogImage) {
            return;
        }
        const activeIndex = galleryThumbs.findIndex((thumb) => thumb.dataset.gallerySrc === mainImage.src);
        showDialogImage(activeIndex >= 0 ? activeIndex : 0);
        imageDialog.showModal();
    });
    dialogPrevious?.addEventListener('click', () => showDialogImage(dialogIndex - 1));
    dialogNext?.addEventListener('click', () => showDialogImage(dialogIndex + 1));
    document.querySelector('[data-image-dialog-close]')?.addEventListener('click', () => imageDialog?.close());
    imageDialog?.addEventListener('click', (event) => {
        if (event.target === imageDialog) {
            imageDialog.close();
        }
    });

    const variantForm = document.querySelector('[data-variant-form]');
    if (variantForm) {
        const variants = JSON.parse(variantForm.dataset.variants || '[]');
        const selected = { size: '', color: '' };
        const updateVariant = () => {
            const matching = variants.filter((variant) => (!selected.size || String(variant.size_id) === selected.size) && (!selected.color || String(variant.color_id) === selected.color));
            const requiresSize = variants.some((variant) => variant.size_id !== null);
            const requiresColor = variants.some((variant) => variant.color_id !== null);
            const complete = (!requiresSize || selected.size) && (!requiresColor || selected.color);
            const variant = complete ? matching.find((item) => Number(item.available_stock) > 0) || matching[0] : null;
            const stockMessage = document.querySelector('[data-stock-message]');
            const sku = variantForm.querySelector('[data-variant-sku]');
            const hidden = variantForm.querySelector('[data-selected-variant]');
            const price = document.querySelector('[data-product-price]');
            const addButton = variantForm.querySelector('[data-add-to-cart]');
            const wishlistButton = variantForm.querySelector('[data-wishlist-action]');
            if (hidden) hidden.value = variant?.id || '';
            if (sku) sku.textContent = variant ? `SKU: ${variant.sku}` : 'Choose available options to see the SKU.';
            if (stockMessage) {
                stockMessage.textContent = variant ? (Number(variant.available_stock) > 0 ? `${variant.available_stock} available` : 'Currently unavailable') : 'Select available options';
                stockMessage.classList.toggle('is-available', Boolean(variant && Number(variant.available_stock) > 0));
                stockMessage.classList.toggle('is-unavailable', Boolean(variant && Number(variant.available_stock) <= 0));
            }
            if (price && variant?.price_override !== null && variant?.price_override !== undefined) price.textContent = `${variantForm.dataset.currency} ${Number(variant.price_override).toFixed(2)}`;
            if (addButton) {
                const available = Boolean(variant && Number(variant.available_stock) > 0);
                addButton.disabled = !available;
                addButton.classList.toggle('is-disabled', !available);
                addButton.setAttribute('aria-disabled', String(!available));
            }
            if (wishlistButton) {
                wishlistButton.disabled = !variant;
                wishlistButton.classList.toggle('is-disabled', !variant);
                wishlistButton.dataset.variantId = variant?.id || '';
            }
        };
        variantForm.querySelectorAll('[data-size-id]').forEach((button) => button.addEventListener('click', () => {
            selected.size = button.dataset.sizeId || ''; variantForm.querySelectorAll('[data-size-id]').forEach((item) => item.classList.remove('is-selected')); button.classList.add('is-selected'); variantForm.querySelector('[data-selected-size]').textContent = button.textContent.trim(); updateVariant();
        }));
        variantForm.querySelectorAll('[data-color-id]').forEach((button) => button.addEventListener('click', () => {
            selected.color = button.dataset.colorId || ''; variantForm.querySelectorAll('[data-color-id]').forEach((item) => item.classList.remove('is-selected')); button.classList.add('is-selected'); variantForm.querySelector('[data-selected-color]').textContent = button.querySelector('.sr-only')?.textContent || 'Selected'; updateVariant();
        }));
        variantForm.querySelectorAll('[data-detail-quantity]').forEach((button) => button.addEventListener('click', () => {
            const input = variantForm.querySelector('[name="quantity"]');
            if (!input) return;
            const next = Number(input.value || 1) + (button.dataset.detailQuantity === 'increase' ? 1 : -1);
            input.value = String(Math.max(1, Math.min(99, next)));
        }));
    }
});