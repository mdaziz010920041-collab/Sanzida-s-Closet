const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
}[character]));

function renderAuthPage(mode, csrfToken, nextPath, errorMessage = '', values = {}) {
    const registering = mode === 'register';
    const title = registering ? 'Create account' : 'Sign in';
    const fields = registering ? `<div class="field"><label class="field__label" for="first-name">First name</label><input class="input" id="first-name" name="first_name" value="${escapeHtml(values.first_name)}" autocomplete="given-name" required></div><div class="field"><label class="field__label" for="last-name">Last name</label><input class="input" id="last-name" name="last_name" value="${escapeHtml(values.last_name)}" autocomplete="family-name"></div><div class="field"><label class="field__label" for="phone">Phone</label><input class="input" id="phone" name="phone" type="tel" value="${escapeHtml(values.phone)}" autocomplete="tel"></div>` : '';
    const email = escapeHtml(values.email || '');
    const formAction = registering ? '/auth/register.php' : `/auth/login.php?next=${encodeURIComponent(nextPath)}`;
    const footer = registering ? `Already have an account? <a class="link" href="/auth/login.php">Sign in</a>` : `New here? <a class="link" href="/auth/register.php">Create an account</a>`;
    const forgot = registering ? '' : '<div class="auth-form__meta"><a class="link" href="/auth/forgot-password.php">Forgot password?</a></div>';
    const intro = registering ? 'Save your details and keep your favourite pieces close.' : 'Keep your details, wishlist, and orders together in one quiet place.';
    const content = `<main class="auth-main"><section class="auth-card ${registering ? 'auth-card--wide' : ''}" aria-labelledby="auth-title"><p class="eyebrow">${registering ? 'A place for your edit' : 'Welcome back'}</p><h1 id="auth-title">${registering ? 'Create your<br><em>account.</em>' : 'Sign in to<br><em>your closet.</em>'}</h1><p class="auth-card__intro">${intro}</p>${errorMessage ? `<div class="alert alert--soft" role="alert">${escapeHtml(errorMessage)}</div>` : ''}<form class="auth-form ${registering ? 'form-grid' : ''}" method="post" action="${formAction}" novalidate><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="next" value="${escapeHtml(nextPath)}">${fields}<div class="field"><label class="field__label" for="email">Email address</label><input class="input" id="email" name="email" type="email" value="${email}" autocomplete="email" required></div><div class="field"><label class="field__label" for="password">Password</label><input class="input" id="password" name="password" type="password" autocomplete="${registering ? 'new-password' : 'current-password'}" required></div>${registering ? '<div class="field"><label class="field__label" for="password-confirmation">Confirm password</label><input class="input" id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>' : forgot}<button class="button" type="submit">${registering ? 'Create account' : 'Sign in'} <span aria-hidden="true">&rarr;</span></button></form><p class="auth-card__footer">${footer}</p></section></main>`;
    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${title} | Sanzida's Closet</title><link rel="stylesheet" href="/assets/css/site.css"></head><body class="auth-page"><header class="site-header"><a class="brand" href="/"><img src="/assets/images/brand-logo.jpg" width="44" height="44" alt=""><span class="brand-name">Sanzida's Closet</span></a><nav aria-label="Primary navigation"><a href="/products/">Shop</a><a href="/categories/">Collections</a></nav><a class="header-link" href="/cart/">Cart</a></header>${content}<footer class="site-footer"><a href="/">Sanzida's Closet</a><span>&copy; ${new Date().getFullYear()}</span></footer></body></html>`;
}

module.exports = { renderAuthPage };