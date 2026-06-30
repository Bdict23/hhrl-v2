<?php

namespace App\Services\Inventory;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\Withdrawal;
use App\Models\BanquetEvent\Event;
use App\Models\Inventory\Cardex;


class ItemWithdrawalService
{

    protected $withdrawal;
    protected $event;

    public function __construct(Withdrawal $withdrawal, Event $event)
    {
        $this->withdrawal = $withdrawal;
        $this->event = $event;
    }


    public static function getEventwithdrawals(int $event, int $branch)
    {
        $withdrawals = Withdrawal::where('event_id', $event)->where('source_branch_id', $branch)->get();
        return $withdrawals;
    }
    public static function getEventWithdrawalTotal(int $event, int $branch)
    {
        $total = 0;
        $withdrawalIds = Withdrawal::where('event_id', $event)->where('source_branch_id', $branch)->get()->pluck('id');
        $cardex = Cardex::whereIn('withdrawal_id', $withdrawalIds)->get();
        foreach ($cardex as $item) {
            $total += $item->cost->amount;
        }
        return $total;
    }
}
