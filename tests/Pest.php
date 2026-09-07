<?php

declare(strict_types=1);

/*
 | This file is loaded ONLY when Pest runs from the package root (Pest loads a
 | single Pest.php at <rootPath>/tests). When the suite runs from a host
 | application root (php artisan test app-modules/cms/tests), the host's
 | tests/Pest.php is loaded instead and this file is skipped entirely.
 |
 | That is why every test file binds Noerd\Cms\Tests\TestCase itself via
 | uses() — do not move those bindings here, or host-root runs would lose them.
 | The global noerd test helpers (validDetailPayload, requiredLayoutFields, …)
 | are loaded by Noerd\Tests\TestCase, which every file binds.
 */
