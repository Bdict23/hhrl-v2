<?php

namespace App\Services\Event;

use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\BanquetProcurement;
use App\Models\Business\Branch;


class BanquetProcurementService
{

    protected $procurement;
    protected $event;
    protected $branch;

    public function __construct(BanquetProcurement $procurement, Event $event, Branch $branch,)
    {
        $this->procurement = $procurement;
        $this->event = $event;
        $this->branch = $branch;
    }
}
