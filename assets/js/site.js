const initializeSite = () => {
    const body = document.body;
    const header = document.querySelector('[data-header]');
    const drawer = document.querySelector('#mobile-drawer');
    const drawerBackdrop = document.querySelector('.drawer-backdrop');
    const menuTrigger = document.querySelector('.mobile-menu-trigger');
    const menuCloseButtons = document.querySelectorAll('[data-menu-close]');
    const searchPanel = document.querySelector('#search-panel');
    const searchTrigger = document.querySelector('[data-search-trigger]');
    const searchClose = document.querySelector('[data-search-close]');
    const searchInput = document.querySelector('#site-search');
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])';
    let lastFocusedElement = null;

    const setScrollLock = (locked) => {
        body.classList.toggle('is-locked', locked);
    };

    const trapFocus = (event, container) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [...container.querySelectorAll(focusableSelector)].filter((element) => element.offsetParent !== null);
        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const closeMegaMenus = () => {
        document.querySelectorAll('.mega-menu:not([hidden])').forEach((menu) => {
            menu.hidden = true;
            document.querySelector(`[aria-controls="${menu.id}"]`)?.setAttribute('aria-expanded', 'false');
        });
    };

    const closeDrawer = () => {
        if (!drawer) {
            return;
        }
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        drawer.inert = true;
        menuTrigger?.setAttribute('aria-expanded', 'false');
        if (drawerBackdrop) {
            drawerBackdrop.hidden = true;
        }
        setScrollLock(false);
        lastFocusedElement?.focus();
    };

    const openDrawer = () => {
        if (!drawer) {
            return;
        }
        lastFocusedElement = document.activeElement;
        closeMegaMenus();
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        drawer.inert = false;
        menuTrigger?.setAttribute('aria-expanded', 'true');
        if (drawerBackdrop) {
            drawerBackdrop.hidden = false;
        }
        setScrollLock(true);
        drawer.querySelector('button, a')?.focus();
    };

    const closeSearch = () => {
        if (!searchPanel) {
            return;
        }
        searchPanel.hidden = true;
        searchTrigger?.setAttribute('aria-expanded', 'false');
        setScrollLock(false);
        lastFocusedElement?.focus();
    };

    const openSearch = () => {
        if (!searchPanel) {
            return;
        }
        lastFocusedElement = document.activeElement;
        closeMegaMenus();
        closeDrawer();
        searchPanel.hidden = false;
        searchTrigger?.setAttribute('aria-expanded', 'true');
        setScrollLock(true);
        searchInput?.focus();
    };

    menuTrigger?.addEventListener('click', openDrawer);
    menuCloseButtons.forEach((button) => button.addEventListener('click', closeDrawer));
    searchTrigger?.addEventListener('click', openSearch);
    searchClose?.addEventListener('click', closeSearch);
    drawerBackdrop?.addEventListener('click', closeDrawer);

    document.querySelectorAll('[data-menu-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const menu = document.querySelector(`#${trigger.dataset.menuTrigger}`);
            if (!menu) {
                return;
            }
            const shouldOpen = menu.hidden;
            closeMegaMenus();
            if (shouldOpen) {
                menu.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.site-header')) {
            closeMegaMenus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMegaMenus();
            closeDrawer();
            closeSearch();
        }
        if (!searchPanel?.hidden) {
            trapFocus(event, searchPanel);
        } else if (drawer?.classList.contains('is-open')) {
            trapFocus(event, drawer);
        }
    });

    document.querySelector('.announcement-bar__close')?.addEventListener('click', (event) => {
        event.currentTarget.closest('.announcement-bar')?.remove();
    });

    document.querySelector('.search-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        searchInput?.focus();
    });

    document.querySelectorAll('form.auth-form, form.account-form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.classList.add('is-submitting');
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
        });
    });

    let scrollFrame = 0;
    window.addEventListener('scroll', () => {
        if (scrollFrame) return;
        scrollFrame = window.requestAnimationFrame(() => {
            scrollFrame = 0;
            header?.classList.toggle('is-scrolled', window.scrollY > 8);
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && window.innerWidth > 767) {
                document.querySelectorAll('[data-parallax]').forEach((element) => {
                    const bounds = element.getBoundingClientRect();
                    const offset = (window.innerHeight / 2 - (bounds.top + bounds.height / 2)) * 0.025;
                    element.querySelector('.hero-art__frame')?.style.setProperty('transform', `translate3d(0, ${offset}px, 0)`);
                });
            }
        });
    }, { passive: true });

    document.querySelectorAll('.section__header, .values-grid article, .social-tile').forEach((item) => item.classList.add('scroll-reveal'));
    const revealItems = document.querySelectorAll('.reveal, .scroll-reveal');
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealItems.forEach((item) => item.style.opacity = '1');
    } else {
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    currentObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        revealItems.forEach((item) => observer.observe(item));
    }

    if (drawer) {
        drawer.inert = true;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const heroCarousel = document.querySelector('[data-hero-carousel]');
    const heroSlides = heroCarousel ? [...heroCarousel.querySelectorAll('[data-hero-slide]')] : [];
    let heroIndex = 0;
    let heroTimer;
    const showHeroSlide = (index) => {
        if (heroSlides.length < 2) return;
        heroIndex = (index + heroSlides.length) % heroSlides.length;
        heroSlides.forEach((slide, slideIndex) => {
            const active = slideIndex === heroIndex;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        const status = heroCarousel.querySelector('[data-hero-status]');
        if (status) status.textContent = `${heroIndex + 1} / ${heroSlides.length}`;
    };
    const restartHeroTimer = () => {
        window.clearInterval(heroTimer);
        if (!reduceMotion && heroSlides.length > 1) heroTimer = window.setInterval(() => showHeroSlide(heroIndex + 1), 5000);
    };
    heroCarousel?.querySelector('[data-hero-prev]')?.addEventListener('click', () => { showHeroSlide(heroIndex - 1); restartHeroTimer(); });
    heroCarousel?.querySelector('[data-hero-next]')?.addEventListener('click', () => { showHeroSlide(heroIndex + 1); restartHeroTimer(); });
    restartHeroTimer();
    const touchDevice = window.matchMedia('(hover: none), (pointer: coarse)').matches;
    if (!reduceMotion && !touchDevice) {
        document.querySelectorAll('.product-card').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const bounds = card.getBoundingClientRect();
                const rotateY = ((event.clientX - bounds.left) / bounds.width - 0.5) * 5;
                const rotateX = ((event.clientY - bounds.top) / bounds.height - 0.5) * -5;
                card.style.setProperty('--tilt-x', `${rotateX.toFixed(2)}deg`);
                card.style.setProperty('--tilt-y', `${rotateY.toFixed(2)}deg`);
            });
            card.addEventListener('pointerleave', () => {
                card.style.removeProperty('--tilt-x');
                card.style.removeProperty('--tilt-y');
            });
        });
    }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeSite, { once: true });
else initializeSite();