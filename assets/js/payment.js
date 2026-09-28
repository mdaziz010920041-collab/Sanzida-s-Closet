document.addEventListener('DOMContentLoaded', () => {
    const trigger = document.querySelector('[data-payment-start]');
    if (!trigger) return;
    const message = document.querySelector('[data-payment-message]');
    const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
    const api = document.body.dataset.paymentApi || 'api/payment.php';
    const orderNumber = document.body.dataset.orderNumber || '';

    trigger.addEventListener('click', async () => {
        trigger.disabled = true;
        trigger.setAttribute('aria-busy', 'true');
        if (message) message.textContent = 'Preparing secure payment...';
        try {
            if (window.analytics_event) window.analytics_event('add_payment_info', {currency: document.body.dataset.currency || 'INR'});
            const form = new FormData();
            form.append('action', 'initiate'); form.append('order_number', orderNumber); form.append('csrf_token', csrf);
            const response = await fetch(api, { method: 'POST', body: form, headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Payment could not be started.');
            if (data.status === 'paid') { window.location.href = 'confirmation.php?order=' + encodeURIComponent(orderNumber); return; }
            if (!data.key_id) throw new Error('Payment is pending gateway configuration.');
            await new Promise((resolve, reject) => {
                if (window.Razorpay) return resolve();
                const script = document.createElement('script'); script.src = 'https://checkout.razorpay.com/v1/checkout.js'; script.onload = resolve; script.onerror = () => reject(new Error('Payment checkout could not load.')); document.head.appendChild(script);
            });
            const checkout = new window.Razorpay({ key: data.key_id, amount: data.amount, currency: data.currency, name: "Sanzida's Closet", order_id: data.provider_order_id, handler: async (payment) => {
                const verify = new FormData(); verify.append('action', 'verify'); verify.append('order_number', orderNumber); verify.append('csrf_token', csrf); verify.append('razorpay_order_id', payment.razorpay_order_id); verify.append('razorpay_payment_id', payment.razorpay_payment_id); verify.append('razorpay_signature', payment.razorpay_signature);
                const verifiedResponse = await fetch(api, { method: 'POST', body: verify, headers: { Accept: 'application/json' } }); const verified = await verifiedResponse.json(); if (!verifiedResponse.ok || !verified.success) throw new Error(verified.message || 'Payment verification failed.'); window.location.href = 'confirmation.php?order=' + encodeURIComponent(orderNumber);
            }, modal: { ondismiss: () => { trigger.disabled = false; trigger.removeAttribute('aria-busy'); if (message) message.textContent = 'Payment window closed. You can retry when ready.'; } }});
            checkout.on('payment.failed', (failure) => { trigger.disabled = false; trigger.removeAttribute('aria-busy'); if (message) message.textContent = failure.error?.description || 'Payment failed. You can retry.'; });
            checkout.open();
        } catch (error) {
            trigger.disabled = false; trigger.removeAttribute('aria-busy'); if (message) message.textContent = error.message;
        }
    });
});