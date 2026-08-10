<?php

use App\Models\BanquetEvent\BanquetProcurement;
use App\Models\BanquetEvent\Event;
use App\Models\Business\Branch;
use App\Services\Event\BanquetProcurementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('returns a single banquet procurement budget by id', function () {
    Schema::dropIfExists('banquet_procurements');
    Schema::create('banquet_procurements', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('event_id')->nullable();
        $table->string('reference_number')->nullable();
        $table->timestamps();
    });

    $budget = BanquetProcurement::create([
        'event_id' => 1,
        'reference_number' => 'BEB-TEST-0001',
    ]);

    $service = new BanquetProcurementService(new BanquetProcurement, new Event, new Branch);

    expect($service->viewBudget($budget->id))
        ->toBeInstanceOf(BanquetProcurement::class)
        ->and($service->viewBudget($budget->id)->id)->toBe($budget->id);
});
