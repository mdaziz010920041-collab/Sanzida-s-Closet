<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/omnichannel.php';

try {
    $connection = database_connection();
    admin_require($connection, 'settings.manage');
    $channels = (new MarketplaceSyncService($connection))->status();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $channels = [];
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Marketplace channels | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="admin-page"><aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><a href="<?= escape_html(base_url('admin/')) ?>">Overview</a><a class="is-active" href="<?= escape_html(base_url('admin/channels.php')) ?>">Channels</a><a href="<?= escape_html(base_url('admin/reports.php')) ?>">Reports</a><a href="<?= escape_html(base_url('admin/module.php?name=settings')) ?>">Settings</a></nav></aside><main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Integration readiness</p><h1>Marketplace channels</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header><section class="admin-panel"><p class="muted">The website remains the source of truth. No marketplace API is called until its adapter, credentials, approvals, and sandbox reconciliation are complete.</p><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Channel</th><th>Configuration</th><th>Status</th><th>Next step</th></tr></thead><tbody><?php foreach ($channels as $channel): ?><tr><td><?= escape_html((string) $channel['name']) ?></td><td><?= $channel['configured'] ? 'Credentials present' : 'Not configured' ?></td><td><?= $channel['configured'] && $channel['name'] !== 'Own website' ? 'Adapter pending' : ($channel['configured'] ? 'Source of truth' : 'Blocked') ?></td><td><?= escape_html((string) $channel['message']) ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
