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
                    'total_amount'  => $item['rate'] * $item['quantity'],
                    'start_date'    => $data['start_date'],
                    'start_time'    => $data['arrival_time'],
                    'end_date'      => $data['end_date'],
                    'end_time'      => $data['departure_time'],
                ];
            }

            foreach ($data['services'] as $item) {
                $services[] = [
                    'service_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                ];
            }

            foreach ($data['menu'] as $item) {
                $menu[] = [
                    'menu_id'       => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'note'          => $item['note'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                ];
            }
            $event->venues()->createMany($venues);
            $event->services()->createMany($services);
            $event->menus()->createMany($menu);

            return $event;
        });
    }

    public function updateEvent(array $data): Event
    {
        return DB::transaction(function () use ($data) {
            $event = $this->event->findOrFail($data['event_id']);

            $event->update([
                'event_name'        => $data['event_name'],
                'customer_id'       => $data['customer_id'],
                'event_address'     => $data['event_address'],
                'start_date'        => $data['start_date'],
                'end_date'          => $data['end_date'],
                'arrival_time'      => $data['arrival_time'],
                'departure_time'    => $data['departure_time'],
                'guest_count'       => $data['guest_count'],
                'status'            => $data['status'] == 'FINAL' ? 'CONFIRMED' : 'PENDING',
                'notes'             => $data['note'],
                'total_amount'      => $data['venue_total_amount'] + $data['service_total_amount'] + $data['menu_total_amount'],
                'reviewer_id'       => $data['reviewer_id'],
                'approver_id'       => $data['approver_id'],
            ]);

            // Update related venues, services, and menus
            // For simplicity, we will delete existing ones and create new ones
            $event->venues()->delete();
            $event->services()->delete();
            $event->menus()->delete();

            foreach ($data['venues'] as $item) {
                $event->venues()->create([
                    'venue_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                    'start_date'    => $data['start_date'],
                    'start_time'    => $data['arrival_time'],
                    'end_date'      => $data['end_date'],
                    'end_time'      => $data['departure_time'],
                ]);
            }

            foreach ($data['services'] as $item) {
                $event->services()->create([
                    'service_id'      => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                ]);
            }

            foreach ($data['menu'] as $item) {
                $event->menus()->create([
                    'menu_id'       => $item['id'],
                    'qty'           => $item['quantity'],
                    'price_id'      => $item['price_id'],
                    'total_amount'  => $item['rate'] * $item['quantity'],
                    'note'          => $item['note'],
                ]);
            }

            return $event;
        });
    }

    public function confirmEvent(int $eventId): Event
    {
        $event = $this->event->findOrFail($eventId);
        $event->status = 'CONFIRMED';
        $event->save();

        return $event;
    }

    public function rollbackEvent(int $eventId): Event
    {
        $event = $this->event->findOrFail($eventId);
        $event->status = 'PENDING';
        $event->save();

        return $event;
    }

    public static function getLiquidationData(int $id)
    {
        $data = EventLiquidation::findOrFail($id);
        return $data;
    }
}
