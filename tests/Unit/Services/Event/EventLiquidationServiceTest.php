<?php

use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\EventLiquidation;
use App\Models\Business\Branch;
use App\Models\Transaction\CashReturn;
use App\Models\Transaction\Reimbursement;
use App\Services\Event\EventLiquidationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('event_liquidations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->string('status');
        $table->text('note')->nullable();
        $table->decimal('total_incurred', 15, 2)->default(0);
        $table->unsignedBigInteger('reviewed_by')->nullable();
        $table->unsignedBigInteger('approved_by')->nullable();
        $table->timestamps();
    });

    Schema::create('cash_returns', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('event_liquidation_id')->nullable();
        $table->string('status');
        $table->decimal('amount_returned', 15, 2)->default(0);
        $table->timestamps();
    });
});

it('finalizes a liquidation when no cash return is linked', function () {
    $liquidation = EventLiquidation::query()->create([
        'created_by' => 1,
        'status' => 'DRAFT',
        'note' => null,
        'total_incurred' => 10,
    ]);

    $service = new EventLiquidationService(
        new EventLiquidation(),
        new Event(),
        new Branch(),
        new CashReturn(),
        new Reimbursement(),
    );

    $updatedLiquidation = $service->updateLiquidation([
        'prepared_by' => 2,
        'status' => 'FINAL',
        'notes' => 'Final notes',
        'total_incurred' => 12,
        'reviewed_by' => 3,
        'approved_by' => 4,
        'liquidation_id' => $liquidation->id,
        'amount_returned' => 5,
    ]);

    expect($updatedLiquidation->status)->toBe('FOR REVIEW')
        ->and($updatedLiquidation->note)->toBe('Final notes');

    $this->assertDatabaseMissing('cash_returns', [
        'event_liquidation_id' => $liquidation->id,
    ]);
});
