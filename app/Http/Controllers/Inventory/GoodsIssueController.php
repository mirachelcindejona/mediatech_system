<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreGoodsIssueRequest;
use App\Services\Inventory\PostGoodsIssueService;
use Illuminate\Http\JsonResponse;

class GoodsIssueController extends Controller
{
    public function store(StoreGoodsIssueRequest $request, PostGoodsIssueService $service): JsonResponse
    {
        $issue = $service->post($request->user(), $request->validated());

        return response()->json(['message' => 'Pengeluaran barang berhasil diposting.', 'id' => $issue->getKey(), 'number' => $issue->goods_issue_number], $issue->wasRecentlyCreated ? 201 : 200);
    }
}
