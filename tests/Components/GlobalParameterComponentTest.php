<?php


use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'cms::global-parameter-detail',
    'listName' => 'cms::global-parameters-list',
    'id' => 'modelId',
    'urlParam' => 'globalParameterId',
];

it('test the route', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $response = $this->get('/cms/global-parameters');
    $response->assertStatus(200);
});

it('validates the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', '')
        ->set('detailData.value', '')
        ->call('store')
        ->assertHasErrors(['detailData.key', 'detailData.value']);
});

it('successfully stores the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $parameterKey = fake()->word;
    $parameterValue = fake()->sentence;

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', $parameterKey)
        ->set('detailData.value', $parameterValue)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('global_parameters', [
        'key' => $parameterKey,
        'value' => json_encode($parameterValue),
    ]);
});

it('sets a table key for the list', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test($testSettings['listName'])
        ->assertNotSet('listId', '');
});

it('validates that key is required', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.value', fake()->sentence)
        ->call('store')
        ->assertHasErrors(['detailData.key']);
});

it('validates that value is required', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', fake()->word)
        ->set('detailData.value', '')
        ->call('store')
        ->assertHasErrors(['detailData.value']);
});

it('can retrieve existing global parameter data', function (): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key',
        'value' => json_encode('test_value'),
        'tenant_id' => $tenant->id,
    ]);

    $this->assertDatabaseHas('global_parameters', [
        'id' => $existingParameter->id,
        'key' => 'test_key',
        'value' => json_encode('test_value'),
    ]);
});

it('validates key is string and has max length', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    // Test max length validation
    $longKey = str_repeat('a', 256); // Over 255 characters

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', $longKey)
        ->set('detailData.value', 'test value')
        ->call('store')
        ->assertHasErrors(['detailData.key']);
});

it('stores data with tenant_id', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $parameterKey = fake()->word;
    $parameterValue = fake()->sentence;

    Livewire::test($testSettings['componentName'])
        ->set('detailData.key', $parameterKey)
        ->set('detailData.value', $parameterValue)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('global_parameters', [
        'key' => $parameterKey,
        'value' => json_encode($parameterValue),
        'tenant_id' => $tenant->id,
    ]);
});

it('it sets and removes the model id in url', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $model = GlobalParameter::factory()->withTenantId($tenant->id)->create();

    Livewire::test($testSettings['listName'])->call('listAction', $model->id)
        ->assertDispatched('noerdModal', modalComponent: $testSettings['componentName']);

    Livewire::withUrlParams([$testSettings['urlParam'] => $model->id])
        ->test($testSettings['componentName'])
        ->assertSet('detailData.id', $model->id)
        ->assertHasNoErrors();
});

it('loads existing string value into component model for editing', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key_string',
        'value' => json_encode('test_value_string'),
        'tenant_id' => $tenant->id,
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $existingParameter->id])
        ->test($testSettings['componentName'])
        ->assertSet('detailData.key', 'test_key_string')
        ->assertSet('detailData.value', 'test_value_string');
});

it('loads existing array value into component model for editing', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key_array',
        'value' => json_encode(['de' => 'Hallo', 'en' => 'Hello']),
        'tenant_id' => $tenant->id,
    ]);

    Livewire::withUrlParams([$testSettings['urlParam'] => $existingParameter->id])
        ->test($testSettings['componentName'])
        ->assertSet('detailData.key', 'test_key_array')
        ->assertSet('detailData.value', fn($value) => is_array($value) && ($value['de'] ?? null) === 'Hallo' && ($value['en'] ?? null) === 'Hello');
});
