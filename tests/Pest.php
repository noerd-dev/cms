<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Global test helpers
|--------------------------------------------------------------------------
|
| These tests bind Tests\TestCase (the host application's), not
| Noerd\Tests\TestCase, so the noerd helpers (validDetailPayload,
| requiredLayoutFields, registerTestLivewireRoute, ...) are not loaded through
| that class. They are deliberately absent from the production composer
| autoload, so load them explicitly. HelperLoader resolves the file through the
| autoloader and therefore works whether noerd is installed as a composer
| package or as a submodule.
|
*/

\Noerd\Tests\HelperLoader::load();

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
| There is deliberately NO global fixture here: the single source of the
| tenant/user/language fixture is the CreatesCmsUser trait. A global
| beforeEach next to it created a SECOND tenant for every test that also
| called createUserWithCmsAccess().
|
*/

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);
