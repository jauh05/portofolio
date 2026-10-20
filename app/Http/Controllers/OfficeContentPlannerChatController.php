<?php

namespace App\Http\Controllers;

use App\Models\OfficeContentBrand;
use App\Services\OfficeAiGatewayException;
use App\Services\OfficeTrentOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeContentPlannerChatController extends Controller
{
    /**
     * Session-authenticated bridge to Trent for the dashboard chat.
     * The browser never holds the bridge token; history carries earlier user turns for clarifications.
     */
    public function message(Request $request, OfficeTrentOrchestratorService $orchestrator): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'workspace_id' => ['nullable', 'uuid', 'exists:office_content_brands,id'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*' => ['string', 'max:2000'],
        ]);

        $workspaceId = $data['workspace_id']
            ?? OfficeContentBrand::where('slug', 'jauki')->value('id')
            ?? OfficeContentBrand::where('is_active', true)->orderBy('name')->value('id')
            ?? '';
        $message = trim(implode("\n", [...($data['history'] ?? []), $data['message']]));

        try {
            return response()->json($orchestrator->processIntent($message, (string) $workspaceId));
        } catch (OfficeAiGatewayException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
