<?php

namespace App\Http\Controllers;

use App\Services\OfficeTrentOrchestratorService;
use App\Models\OfficeContentBrand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeTrentController extends Controller
{
    private function authorized(Request $request): bool
    {
        $token = (string) config('services.office_bridge.token');
        if ($token === '') return false;
        $authorization = (string) $request->header('Authorization');
        $bridgeToken = (string) $request->header('X-Office-Bridge-Token');

        return hash_equals('Bearer '.$token, $authorization) || hash_equals($token, $bridgeToken);
    }

    public function intent(Request $request, OfficeTrentOrchestratorService $trent): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'workspace_id' => ['nullable', 'uuid', 'exists:office_content_brands,id']
        ]);

        $workspaceId = $data['workspace_id'] ?? null;
        if (empty($workspaceId)) {
             $brand = OfficeContentBrand::where('slug', 'jauki')->first();
             $workspaceId = $brand ? $brand->id : null;
        }

        $result = $trent->processIntent($data['message'], $workspaceId ?? '');

        return response()->json($result);
    }
}
