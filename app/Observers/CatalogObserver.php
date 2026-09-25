<?php

namespace App\Observers;

use App\Services\Catalog\PublicCatalog;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CatalogObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(): void
    {
        app(PublicCatalog::class)->invalidate();
    }

    public function deleted(): void
    {
        app(PublicCatalog::class)->invalidate();
    }
}
