<?php

declare(strict_types=1);

namespace Noerd\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Noerd\Cms\Exceptions\FormSubmissionException;
use Noerd\Cms\Forms\FormSubmissionService;

class FormRequestController extends Controller
{
    public function store(Request $request, FormSubmissionService $submissions): JsonResponse
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

        // This request carries no noerd session: CmsApiAuth sets the token's
        // tenant as the tenant context, the service names it explicitly too.
        try {
            $model = $submissions->submit($tenantId, (string) $request->string('form'), (array) $request->input('data', []));
        } catch (FormSubmissionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'id' => $model->id,
            'created_at' => $model->created_at,
        ], 201);
    }
}
