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

    public static function getLiquidationData(int $id)
    {
        $data = EventLiquidation::findOrFail($id);
        return $data;
    }
}
