<?php

namespace App\Services\Event;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\EventLiquidation;
use App\Models\Inventory\Cardex;


class BanquetEventService
{

    protected $liquidation;
    protected $event;
    protected $branch;

    public function __construct(
        EventLiquidation $liquidation,
        Event $event,
        Branch $branch,

    ) {
        $this->liquidation = $liquidation;
        $this->event = $event;
        $this->branch = $branch;
    }

    public function createEvent(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $venues = [];
            $services = [];
            $menu = [];

            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->event->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'BEO-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            $event = $this->event->create([
                'reference'         => $reference,
                'branch_id'         => $branchId,
                'event_name'        => $data['event_name'],
                'customer_id'        => $data['customer_id'],
                'event_address'     => $data['event_address'],
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'],
                'arrival_time'      => $data['arrival_time'],
                'departure_time'    => $data['departure_time'],
                'created_by'        => $data['prepared_by'],
                'guest_count'       => $data['guest_count'],
                'status'            => $data['status'] == 'FINAL' ? 'CONFIRMED' : 'PENDING',
                'notes'             => $data['note'],
                'total_amount'      => $data['venue_total_amount'] + $data['service_total_amount'] + $data['menu_total_amount'],
                'reviewer_id'       => $data['reviewer_id'],
                'approver_id'       => $data['approver_id'],
            ]);

            foreach ($data['venues'] as $item) {
                $venues[] = [
                    'venue_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $data['venue_total_amount'],
                    'start_date'    => $data['arrival_time'],
                    'start_time'    => $data['start_date'],
                    'end_date'      => $data['end_date'],
                    'end_time'      => $data['departure_time'],
                ];
            }

            foreach ($data['services'] as $item) {
                $services[] = [
                    'service_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $data['service_total_amount'],
                ];
            }

            foreach ($data['menu'] as $item) {
                $menu[] = [
                    'menu_id'       => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'note'          => $item['price_id'],
                    'total_amount'  => $data['menu_total_amount'],
                ];
            }
            $event->venues()->createMany($venues);
            $event->services()->createMany($services);
            $event->menus()->createMany($menu);

            return $event;
        });
    }

    public static function getLiquidationData(int $id)
    {
        $data = EventLiquidation::findOrFail($id);
        return $data;
    }
}
