<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\AcknowledgementReceiptService;




new class extends Component
{
    use Interactions;

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
    public $status;


    protected $rules =[
        'customerId' => 'required|exists:customers,id',
        'eventId' => 'nullable|exists:banquet_events,id',
        'bankId' => 'required|exists:banks,id',
        'accountName' => 'required',
        'checkNumber' => 'required',
        'checkDate' => 'required',
        'checkStatus' => 'required|in:CURRENT,POST-DATED',
        'checkAmountNumeric' => 'required|numeric|min:1',
        'note' => 'nullable|string|max:150',
        ];

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

    public function saveAsDraftAction(){
        $validated = $this->validate();
         $this->status = 'DRAFT';
         $this->dialog()
        ->question('New Acknowledgement Receipt', 'Are you sure to save this acknowledgement receipt as draft?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function saveAsFinalAction(){
        $validated = $this->validate();
        $this->status = 'OPEN';
        $this->dialog()
        ->question('New Acknowledgement Receipt', 'Are you sure to save this acknowledgement receipt as final ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(AcknowledgementReceiptService $acknowledgementReceiptService)
    {
        try {
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'customer_id' => $this->customerId,
                'event_id' => $this->eventId,
                'bank_id' => $this->bankId,
                'account_name' => $this->accountName,
                'check_number' => $this->checkNumber,
                'check_date' => $this->checkDate,
                'check_amount' => $this->checkAmountNumeric,
                'amount_in_words' => $this->amountInWords,
                'check_status' => $this->checkStatus,
                'note' => $this->note,
                'status' => $this->status,
                'created_by' => Auth::user()->emp_id,
                'company_id' => Auth::user()->branch->company_id,
            ];

            // 4. Call the Service
            $ar = $acknowledgementReceiptService->create($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Acknowledgement Receipt {$ar->reference} created successfully!")->send();
            $this->reset();
            return redirect()->route('acknowledgement-receipt.summary');
            } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("Acknowledgement Receipt Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }
    public function resetForm()
    {
        $this->customerId = null;
        $this->eventId = null;
        $this->bankId = null;
        $this->accountName = null;
        $this->checkNumber = null;
        $this->checkDate = null;
        $this->checkStatus = 'CURRENT';
        $this->checkAmount = null;
        $this->checkAmountNumeric = null;
        $this->note = null;
        $this->amountInWords = null;
        $this->status = null;
    }

};
?>

<div class="p-6 font-sans">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Transaction', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Acknowledgement Receipt Summary', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Create acknowledgement receipt', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    <x-ts-card>
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold tracking-tight uppercase">Acknowledgement Receipt</h2>
        </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="grid grid-cols-9 md:col-span-12 gap-10">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Reference</label>
                        <div class="w-full bg-gray-50 border border-gray-200 text-gray-400 text-sm rounded-lg px-3 py-2.5 font-mono flex items-center select-none cursor-not-allowed">
                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            &lt;AUTO&gt;
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-select.styled
                            :request="route('api.get.active.funded-event', ['branch_id' => auth()->user()->branch_id])"
                            label="Associated Event"
                            select="label:event_name|value:id|description:reference"
                            placeholder="Select event"
                            wire:model='eventId'
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
                    <x-ts-input label="Address"  wire:model='address' readonly/>
                </div>

            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="flex items-center space-x-2 mb-4">
                    <span class="w-1.5 h-4 bg-emerald-700 rounded-full"></span>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Check Details</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                       <x-ts-input label="ACCOUNT NAME" wire:model="accountName"></x-ts-input>
                    </div>

                    <div class="md:col-span-6">
                        <x-ts-select.styled searchable
                                            :request="route('api.get.branch-banks', ['branch_id' => auth()->user()->branch_id])"
                                            label="BANK"
                                            select="label:bank_name|value:id|description:bank_address"
                                            placeholder="Select bank"
                                            wire:model.live="bankId"
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
                        <x-ts-input label="CHECK NUMBER" wire:model="checkNumber"></x-ts-input>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-date label="CHECK DATE" wire:model="checkDate"/>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-select.native :options="['CURRENT','POST-DATED']" wire:model='checkStatus' label="CHECK STATUS"/>
                    </div>

                    <div class="md:col-span-8 flex flex-col justify-between space-y-5">
                        <div>
                            <x-ts-currency wire:model.live='checkAmount' mutate  label="AMOUNT"/>
                        </div>

                        <div>
                            <x-ts-input wire:model='amountInWords' label="AMOUNT IN WORDS"  readonly/>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-textarea label="NOTE" wire:model="note" count maxlength="150" resize class="md:h-28"></x-ts-textarea>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end items-center space-x-3">
                <x-ts-button  wire:click="resetForm" flat>Reset</x-ts-button>
                <div class="whitespace-nowrap content-center">
                        <x-ts-dropdown>
                            <x-slot:action>
                                <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                            </x-slot:action>
                            <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()"/>
                            <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator  wire:click="saveAsFinalAction()" />
                        </x-ts-dropdown>
                    </div>
            </div>
    </x-ts-card>
    <x-ts-loading delay="short" loading="store" />
</div>
