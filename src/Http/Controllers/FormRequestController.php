<?php

namespace Noerd\Cms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Noerd\Cms\Models\FormRequest as FormRequestModel;

class FormRequestController extends Controller
{
    public function store(Request $request)
    {
        $rules = [
            'data' => ['required', 'array'],
            'form' => ['required', 'string', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = (int) ($request->attributes->get('tenant_id'));
        if (!$tenantId) {
            return response()->json(['message' => 'Unauthorized tenant'], 401);
        }

        $model = FormRequestModel::create([
            'form' => $request->string('form'),
            'tenant_id' => $tenantId,
            'data' => json_encode($request->input('data', [])),
        ]);

        return response()->json([
            'id' => $model->id,
            'created_at' => $model->created_at,
        ], 201);
    }
}


