<?php

declare(strict_types=1);

function analytics_config(): array
{
    return ['ga_id' => trim((string) app_setting('integrations.ga_measurement_id', env_value('GA_MEASUREMENT_ID', ''))), 'meta_pixel_id' => trim((string) app_setting('integrations.meta_pixel_id', env_value('META_PIXEL_ID', '')))];
}

function analytics_script(): string
{
    $config = analytics_config();
    if ($config['ga_id'] === '' && $config['meta_pixel_id'] === '') return '';
    $gaId = json_encode($config['ga_id'], JSON_THROW_ON_ERROR);
    $pixelId = json_encode($config['meta_pixel_id'], JSON_THROW_ON_ERROR);
    $script = '<script>window.analytics_event=window.analytics_event||function(name,params){params=params||{};if(window.gtag)window.gtag("event",name,params);if(window.fbq){var map={page_view:"PageView",view_item:"ViewContent",search:"Search",add_to_cart:"AddToCart",remove_from_cart:"RemoveFromCart",add_to_wishlist:"AddToWishlist",begin_checkout:"InitiateCheckout",add_payment_info:"AddPaymentInfo",purchase:"Purchase",refund:"Refund"};if(map[name])window.fbq("track",map[name],params);}};</script>';
    if ($config['ga_id'] !== '') $script .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . escape_html($config['ga_id']) . '"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date);gtag("config",' . $gaId . ',{allow_google_signals:false,allow_ad_personalization_signals:false});</script>';
    if ($config['meta_pixel_id'] !== '') $script .= '<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init",' . $pixelId . ');</script>';
    $script .= '<script>analytics_event("page_view");</script>';
    return $script;
}
