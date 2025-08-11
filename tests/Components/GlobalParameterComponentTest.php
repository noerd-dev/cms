<?php

use Livewire\Volt\Volt;
use Noerd\Cms\Models\GlobalParameter;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);

$testSettings = [
    'componentName' => 'global-parameter-component',
    'listName' => 'global-parameters-table',
    'id' => 'globalParameterId',
];

it('test the route', function (): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $response = $this->get('/cms/global-parameters');
    $response->assertStatus(200);
});

it('validates the data', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.key', '')
        ->set('model.value', '')
        ->call('store')
        ->assertHasErrors(['model.key', 'model.value']);
});

it('successfully stores the data', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);
    $parameterKey = fake()->word;
    $parameterValue = fake()->sentence;

    Volt::test($testSettings['componentName'])
        ->set('model.key', $parameterKey)
        ->set('model.value', $parameterValue)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('global_parameters', [
        'key' => $parameterKey,
        'value' => json_encode($parameterValue),
    ]);
});

it('sets a table key for the list', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['listName'])
        ->assertNotSet('tableId', '');
});

it('validates that key is required', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.value', fake()->sentence)
        ->call('store')
        ->assertHasErrors(['model.key']);
});

it('validates that value is required', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    Volt::test($testSettings['componentName'])
        ->set('model.key', fake()->word)
        ->set('model.value', '')
        ->call('store')
        ->assertHasErrors(['model.value']);
});

it('can retrieve existing global parameter data', function (): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key',
        'value' => json_encode('test_value'),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    $this->assertDatabaseHas('global_parameters', [
        'id' => $existingParameter->id,
        'key' => 'test_key',
        'value' => json_encode('test_value'),
    ]);
});

it('validates key is string and has max length', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);

    // Test max length validation
    $longKey = str_repeat('a', 256); // Over 255 characters

    Volt::test($testSettings['componentName'])
        ->set('model.key', $longKey)
        ->set('model.value', 'test value')
        ->call('store')
        ->assertHasErrors(['model.key']);
});

it('stores data with tenant_id', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);
    $parameterKey = fake()->word;
    $parameterValue = fake()->sentence;

    Volt::test($testSettings['componentName'])
        ->set('model.key', $parameterKey)
        ->set('model.value', $parameterValue)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('global_parameters', [
        'key' => $parameterKey,
        'value' => json_encode($parameterValue),
        'tenant_id' => $user->selected_tenant_id,
    ]);
});

it('it sets and removes the model id in url', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();

    $this->actingAs($user);
    $model = GlobalParameter::factory()->withTenantId($user->selected_tenant_id)->create();

    Volt::test($testSettings['listName'])->call('tableAction', $model->id)
        ->assertDispatched('noerdModal', component: $testSettings['componentName']);

    Volt::test($testSettings['componentName'], [$model->id])
        ->assertSet('model.id', $model->id)
        ->assertSet($testSettings['id'], $model->id) // URL Parameter
        ->call('delete')
        ->assertDispatched('reloadTable-' . $testSettings['listName'])
        ->assertSet($testSettings['id'], '') // URL Parameter should be removed
        ->assertHasNoErrors();
});

it('loads existing string value into component model for editing', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key_string',
        'value' => json_encode('test_value_string'),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    Volt::test($testSettings['componentName'], [$existingParameter->id])
        ->assertSet('model.key', 'test_key_string')
        ->assertSet('model.value', 'test_value_string');
});

it('loads existing array value into component model for editing', function () use ($testSettings): void {
    $user = User::factory()->withContentModule()->create();
    $this->actingAs($user);

    $existingParameter = GlobalParameter::create([
        'key' => 'test_key_array',
        'value' => json_encode(['de' => 'Hallo', 'en' => 'Hello']),
        'tenant_id' => $user->selected_tenant_id,
    ]);

    Volt::test($testSettings['componentName'], [$existingParameter->id])
        ->assertSet('model.key', 'test_key_array')
        ->assertSet('model.value', fn ($value) => is_array($value) && ($value['de'] ?? null) === 'Hallo' && ($value['en'] ?? null) === 'Hello');
});
