<?php


use Noerd\Cms\Models\GlobalParameter;
use Noerd\Cms\Tests\Traits\CreatesCmsUser;

uses(Tests\TestCase::class);
uses(CreatesCmsUser::class);

$testSettings = [
    'componentName' => 'global-parameter-detail',
    'listName' => 'global-parameters-list',
    'id' => 'globalParameterId',
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
        ->set('globalParameterData.key', '')
        ->set('globalParameterData.value', '')
        ->call('store')
        ->assertHasErrors(['globalParameterData.key', 'globalParameterData.value']);
});

it('successfully stores the data', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $parameterKey = fake()->word;
    $parameterValue = fake()->sentence;

    Livewire::test($testSettings['componentName'])
        ->set('globalParameterData.key', $parameterKey)
        ->set('globalParameterData.value', $parameterValue)
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
        ->set('globalParameterData.value', fake()->sentence)
        ->call('store')
        ->assertHasErrors(['globalParameterData.key']);
});

it('validates that value is required', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    Livewire::test($testSettings['componentName'])
        ->set('globalParameterData.key', fake()->word)
        ->set('globalParameterData.value', '')
        ->call('store')
        ->assertHasErrors(['globalParameterData.value']);
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
        ->set('globalParameterData.key', $longKey)
        ->set('globalParameterData.value', 'test value')
        ->call('store')
        ->assertHasErrors(['globalParameterData.key']);
});

it('stores data with tenant_id', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);
    $parameterKey = fake()->word;
    $parameterValue = fake()->sentence;

    Livewire::test($testSettings['componentName'])
        ->set('globalParameterData.key', $parameterKey)
        ->set('globalParameterData.value', $parameterValue)
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

    Livewire::test($testSettings['componentName'], [$model->id])
        ->assertSet('globalParameterData.id', $model->id)
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

    Livewire::test($testSettings['componentName'], [$existingParameter->id])
        ->assertSet('globalParameterData.key', 'test_key_string')
        ->assertSet('globalParameterData.value', 'test_value_string');
});

it('loads existing array value into component model for editing', function () use ($testSettings): void {
    ['user' => $user, 'tenant' => $tenant] = $this->createUserWithCmsAccess();
    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key_array',
        'value' => json_encode(['de' => 'Hallo', 'en' => 'Hello']),
        'tenant_id' => $tenant->id,
    ]);

    Livewire::test($testSettings['componentName'], [$existingParameter->id])
        ->assertSet('globalParameterData.key', 'test_key_array')
        ->assertSet('globalParameterData.value', fn($value) => is_array($value) && ($value['de'] ?? null) === 'Hallo' && ($value['en'] ?? null) === 'Hello');
});
