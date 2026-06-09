<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\BanquetEvent\Event;

class BanquetEventApiController extends Controller
{
    public function activeEvent(Request $request)
    {
    $branch_id = $request->query('branch_id');

    $events = Event::query()
    ->where('branch_id', $branch_id)
    ->where('status', 'CONFIRMED')
    ->where('liquidation_status', 'PENDING')
     ->whereHas('banquetEventBudget', function ($query) {
        $query->where('status', 'APPROVED');
    })
    ->get();

     return response()->json($events);

    }

        public function getFundedEvent(Request $request)
    {
    $branch_id = $request->query('branch_id');

    $events = Event::query()
    ->where('branch_id', $branch_id)
    ->where('status', 'CONFIRMED')
    ->where('liquidation_status', 'PENDING')
     ->whereHas('banquetEventBudget', function ($query) {
        $query->where('status', 'APPROVED');
    })
    ->get();

     return response()->json($events);

    }
}
