<?php

declare(strict_types=1);

namespace Noerd\Cms\Listeners;

use Noerd\Cms\Models\CmsLanguage;
use Noerd\Events\TenantAppAssigned;
use Noerd\Support\TenantContext;

/**
 * The default website language of a tenant is created when the CMS app is
 * assigned to it — not for every tenant (a tenant without the CMS has no
 * website). A tenant that already has a language keeps it.
 */
final class EnsureDefaultCmsLanguage
{
    public function handle(TenantAppAssigned $event): void
    {
        if (mb_strtoupper($event->appName) !== 'CMS') {
            return;
        }

        TenantContext::run($event->tenantId, fn() => CmsLanguage::ensureDefaultLanguageForTenant($event->tenantId));
    }
}
