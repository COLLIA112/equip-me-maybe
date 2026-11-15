<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    /** GET /api/equipment?lab_id=&status= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Equipment::query()
            ->with(['lab', 'tags'])
            ->filter($request->only(['lab_id', 'status']))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return EquipmentResource::collection($items);
    }
}
