<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\AcknowledgementReceiptService;
use App\Models\Transaction\Acknowledgement;


new class extends Component
{
    use Interactions;

    public $arId;
    public $address;
    public $customerId;
    public $accountName;
    public $bankId;
    public $eventId;
    public $checkNumber;
    public $checkDate;
    public $checkStatus = 'CURRENT';
    public $checkAmount;
    public $checkAmountNumeric;
    public $note;
    public $amountInWords;
    public $isDraft;
    public $reference;

    public function mount($id)
    {
        $this->arId = $id;
        $this->fetchData();

    }

    public function fetchData()
    {
        $data = Acknowledgement::find($this->arId);
        $this->customerId = $data->customer_id;
        $this->address = $data->customer->customer_address;
        $this->accountName = $data->account_name;
        $this->bankId = $data->bank_id;
        $this->eventId = $data->event_id;
        $this->checkNumber = $data->check_number;
        $this->checkDate = $data->check_date;
        $this->checkStatus = $data->check_status;
        $this->checkAmount = $data->check_amount;
        $this->checkAmountNumeric = $data->check_amount;
        $this->note = $data->note;
        $this->amountInWords = $data->amount_in_words;
        $this->isDraft = $data->status == 'DRAFT' ? true : false;
        $this->reference = $data->reference;

    }

    public function updatedCustomerId($value)
    {
        $data = Customer::find($value);
        $this->address = $data?->customer_address;
        $this->accountName = $data?->full_name;

    }

    public function updatedCheckAmount($value)
    {
        $number = (float) str_replace(",", "", $value);
        $this->checkAmountNumeric = $number;
        if($number < 0){
            $this->checkAmount = 0;
            $this->amountInWords = $this->convertNumberToWords(0);
            return;
        }
        $this->amountInWords = $this->convertNumberToWords($number);
    }

    private function convertNumberToWords($number)
    {
        // Separate pesos and centavos
        $pesos = floor($number);
        $centavos = round(($number - $pesos) * 100);

        // Convert pesos to words
        $pesosInWords = $this->numberToWords($pesos);
        $result = ucfirst($pesosInWords) . ' PESO' . ($pesos != 1 ? 'S' : '');

        // Add centavos if any
        if ($centavos > 0) {
            $centavosInWords = $this->numberToWords($centavos);
            $result .= ' AND ' . $centavosInWords . ' CENTAVO' . ($centavos != 1 ? 'S' : '');
        }

        return $result . ' ONLY';
    }

    private function numberToWords($number)
    {
        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
        $teens = ['TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];

        if ($number == 0) return 'ZERO';

        $words = '';

        // Billions
        if ($number >= 1000000000) {
            $words .= $this->numberToWords(floor($number / 1000000000)) . ' BILLION ';
            $number %= 1000000000;
        }

        // Millions
        if ($number >= 1000000) {
            $words .= $this->numberToWords(floor($number / 1000000)) . ' MILLION ';
            $number %= 1000000;
        }

        // Thousands
        if ($number >= 1000) {
            $words .= $this->numberToWords(floor($number / 1000)) . ' THOUSAND ';
            $number %= 1000;
        }

        // Hundreds
        if ($number >= 100) {
            $words .= $ones[floor($number / 100)] . ' HUNDRED ';
            $number %= 100;
        }

        // Tens and ones
        if ($number >= 20) {
            $words .= $tens[floor($number / 10)] . ' ';
            $number %= 10;
        } elseif ($number >= 10) {
            $words .= $teens[$number - 10] . ' ';
            $number = 0;
        }

        if ($number > 0) {
            $words .= $ones[$number] . ' ';
        }

        return trim($words);
    }



};
?>

<div class="p-6 font-sans">
    <div class="mb-3 flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                                  ['label' => 'Transaction', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'archive-box' ],
                                  ['label' => 'Acknowledgement Receipt Summary', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'list-bullet'],
                                  ['label' => 'View acknowledgement receipt', 'icon' => 'eye'],
                      ]"  class="mb-3"/>

                      <label class="text-2xl italic">( {{ $reference }} )</label>
    </div>
    <x-ts-card>
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold tracking-tight uppercase">Acknowledgement Receipt</h2>
        </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="grid grid-cols-9 md:col-span-12 gap-10">
                    <div class="md:col-span-4">
                        <x-ts-select.styled
                            :request="route('api.active.event', ['branch_id' => auth()->user()->branch_id])"
                            label="Associated Event"
                            select="label:event_name|value:id|description:reference"
                            placeholder="Select event"
                            wire:model='eventId'
                            readonly
                        />
                    </div>
                </div>

                <div class="md:col-span-12">
                        <x-ts-select.styled searchable
                                            :request="route('api.get.branch-customers', ['branch_id' => auth()->user()->branch_id])"
                                            label="RECEIVED FROM (SOURCE)"
                                            select="label:full_name|value:id|description:customer_address"
                                            placeholder="Select source (customer)"
                                            wire:model.live="customerId"
                                            readonly
                                            required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                        <span x-html="`Create source <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
                </div>
                <div class="md:col-span-12">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Address</label>
                    <x-ts-input  wire:model='address' readonly></x-ts-input>
                </div>

            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="flex items-center space-x-2 mb-4">
                    <span class="w-1.5 h-4 bg-emerald-700 rounded-full"></span>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Check Details</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                       <x-ts-input label="ACCOUNT NAME" wire:model="accountName" readonly />
                    </div>

                    <div class="md:col-span-6">
                        <x-ts-select.styled searchable
                                            :request="route('api.get.branch-banks', ['branch_id' => auth()->user()->branch_id])"
                                            label="BANK"
                                            select="label:bank_name|value:id|description:bank_address"
                                            placeholder="Select bank"
                                            wire:model.live="bankId"
                                            readonly
                                            required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                        <span x-html="`register bank <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-input label="CHECK NUMBER" wire:model="checkNumber" readonly/>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-date label="CHECK DATE" wire:model="checkDate" disabled/>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-select.styled :options="['CURRENT','POST-DATED']" wire:model='checkStatus' label="CHECK STATUS" readonly/>
                    </div>

                    <div class="md:col-span-8 flex flex-col justify-between space-y-5">
                        <div>
                            <x-ts-currency wire:model.live='checkAmount' mutate  label="AMOUNT" readonly/>
                        </div>

                        <div>
                            <x-ts-input wire:model='amountInWords' label="AMOUNT IN WORDS"  readonly/>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-textarea label="NOTE" wire:model="note" count maxlength="150" resize class="md:h-28" readonly/>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end items-center space-x-3">

                @if ($isDraft)
                    <x-ts-button light icon="pencil-square"  :href="route('acknowledgement-receipt.edit', ['id' => $arId])">Edit</x-ts-button>
                @else
                    <x-ts-button light icon="pencil-square"  disabled>Edit</x-ts-button>
                @endif
                <x-ts-button  wire:click="resetForm"  icon="printer">Print</x-ts-button>

            </div>
    </x-ts-card>
    <x-ts-loading delay="short" loading="store" />
</div>
