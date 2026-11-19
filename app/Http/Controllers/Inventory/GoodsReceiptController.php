<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreGoodsReceiptRequest;
use App\Services\Inventory\ReceiveGoodsService;
use Illuminate\Http\JsonResponse;

class GoodsReceiptController extends Controller
{
    public function store(StoreGoodsReceiptRequest $request, ReceiveGoodsService $service): JsonResponse
    {
        $receipt = $service->receive($request->user(), $request->validated());

        return response()->json(['message' => 'Penerimaan barang berhasil diposting.', 'id' => $receipt->getKey(), 'number' => $receipt->receipt_number], $receipt->wasRecentlyCreated ? 201 : 200);
    }
}
