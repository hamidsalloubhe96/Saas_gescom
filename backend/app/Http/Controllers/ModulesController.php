<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Modules facultatifs de l'entreprise (suivi des péremptions…), activés ou non indépendamment du type d'activité. */
class ModulesController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(collect(array_keys(Tenant::MODULES))->mapWithKeys(fn ($m) => [$m => ['sometimes', 'boolean']])->all());
        // On ne change que les modules envoyés : activer l'un ne désactive pas les autres.
        $enabled = tenant()->modulesWith($data);
        tenant()->forceFill(['modules' => $enabled])->saveQuietly();

        return response()->json(['modules' => $enabled]);
    }
}
