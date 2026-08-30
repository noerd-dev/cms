<?php

namespace Noerd\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Noerd\Cms\Jobs\SendFormConfirmationEmail;
use Noerd\Cms\Models\FormRequest as FormRequestModel;
use Noerd\Cms\Models\FormType;

class FormRequestController extends Controller
{
    /**
     * Upper bound for the JSON-encoded submission payload, so a token holder
     * cannot grow form_requests rows without limit.
     */
    private const MAX_PAYLOAD_BYTES = 65535;

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => ['required', 'array'],
            'form' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = (int) ($request->attributes->get('tenant_id'));
        if (! $tenantId) {
            return response()->json(['message' => 'Unauthorized tenant'], 401);
        }

        // This request carries no noerd session, so the tenant scope does not
        // apply — the tenant filter must be explicit.
        $formType = FormType::query()
            ->where('tenant_id', $tenantId)
            ->where('key', (string) $request->string('form'))
            ->first();

        if (! $formType) {
            return response()->json(['message' => 'Unknown form type.'], 422);
        }

        $data = $request->input('data', []);

        if (mb_strlen((string) json_encode($data)) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['message' => 'Payload too large.'], 422);
        }

        $fields = $formType->loadYmlConfig()['fields'] ?? [];

        if ($fields !== []) {
            $data = $this->onlyDeclaredFields($fields, $data);

            $fieldValidator = Validator::make($data, ...$this->rulesFromFormFields($fields));

            if ($fieldValidator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $fieldValidator->errors(),
                ], 422);
            }
        }

        $model = FormRequestModel::create([
            'form' => $request->string('form'),
            'form_type_id' => $formType->id,
            'tenant_id' => $tenantId,
            'data' => $data,
        ]);

        if ($formType->send_email) {
            SendFormConfirmationEmail::dispatch($model);
        }

        return response()->json([
            'id' => $model->id,
            'created_at' => $model->created_at,
        ], 201);
    }

    /**
     * Keep only the keys the form YAML declares — undeclared keys are dropped
     * instead of persisted as arbitrary JSON.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyDeclaredFields(array $fields, array $data): array
    {
        $declared = [];
        foreach ($fields as $field) {
            if (is_string($field['name'] ?? null) && $field['name'] !== '') {
                $declared[] = $field['name'];
            }
        }

        return array_intersect_key($data, array_flip($declared));
    }

    /**
     * Build the Laravel rules and custom messages from the form YAML fields —
     * the same contract the backend form components enforce.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function rulesFromFormFields(array $fields): array
    {
        $rules = [];
        $messages = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $rules[$name] = $field['validation'] ?? ['nullable'];

            foreach ($field['error_messages'] ?? [] as $rule => $message) {
                $messages["{$name}.{$rule}"] = $message;
            }
        }

        return [$rules, $messages];
    }
}
