<?php

use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

it('returns 404 for /cms/collection-definitions in yaml mode', function (): void {
    config(['noerd_cms.collections.mode' => 'yaml']);
    config(['noerd_cms.collections.show_definitions_ui' => false]);

    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-definitions');
    $response->assertNotFound();
});

it('returns 200 for /cms/collection-definitions in database mode', function (): void {
    config(['noerd_cms.collections.mode' => 'database']);
    config(['noerd_cms.collections.show_definitions_ui' => true]);

    ['user' => $user] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/collection-definitions');
    $response->assertOk();
});
