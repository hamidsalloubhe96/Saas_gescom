<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Type d'activité de l'entreprise (commerce général ou pharmacie) : choisi à l'inscription, modifiable ensuite. */
class BusinessTypeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['business_type' => ['required', 'in:'.implode(',', array_keys(Tenant::BUSINESS_TYPES))]]);
        tenant()->forceFill(['business_type' => $data['business_type']])->saveQuietly();

        return response()->json(['business_type' => $data['business_type']]);
    }
}
