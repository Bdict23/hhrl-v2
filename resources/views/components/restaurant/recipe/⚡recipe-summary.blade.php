<?php

use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Models\DataManagement\Recipe;

new class extends Component
{
    use WithPagination;
    public ?string $status = null;
    public ?array $dates = null;
    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = [
            'column' => 'created_at',
            'direction' => 'desc',
        ];

        public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'menu_image', 'label' => 'image'],
                ['index' => 'menu_name', 'label' => 'recipe name'],
                ['index' => 'recipe_type', 'label' => 'type'],
                ['index' => 'category_id', 'label' => 'category' , 'sortable' => false],
                ['index' => 'menu_code', 'label' => 'code', 'sortable' => false],
                ['index' => 'status', 'label' => 'status', 'sortable' => false],
                ['index' => 'created_at', 'label' => 'created', 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'rows' => Recipe::query()
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }

};
?>

<div>
       <div class="mb-10">
         <x-ts-table :$headers :$rows :$sort paginate loading striped filter compact>
             <x-slot:header>
                 <div class="lg:flex lg:justify-between mb-3 grid">
                     <div class="w-auto mb-3">
                        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                         ['label' => 'Restaurant', 'link' =>  route('restaurant.recipe-summary'), 'icon' => 'cog'],
                         ['label' => 'Recipe Summary', 'icon' => 'list-bullet' ],
        
                         ]"  class="mb-3"/>
                     </div>
                 </div>
             </x-slot:header>
             @interact('column_status', $row)
                 <div class="flex items-center gap-2">
                     @if($row->status == 'PENDING')
                         <x-ts-badge :text="$row->status" color="gray" />
                     @elseif($row->status == 'FOR REVIEW')
                         <x-ts-badge :text="$row->status" color="amber" />
                     @elseif($row->status == 'FOR APPROVAL')
                         <x-ts-badge :text="$row->status" color="purple" />
                     @elseif($row->status == 'UNAVAILABLE')
                         <x-ts-badge :text="$row->status" color="olive" />
                     @elseif($row->status == 'AVAILABLE')
                         <x-ts-badge :text="$row->status" color="green" />
                     @elseif($row->status == 'INACTIVE'| $row->status == 'REJECTED')
                         <x-ts-badge :text="$row->status" color="rose" />
                     @endif
                 </div>
             @endinteract
             @interact('column_menu_image', $row)
                     <x-ts-avatar image="{{ asset('storage/'.$row->menu_image) }}" md text="AIR" square />
             @endinteract
             @interact('column_created_at', $row)
                 {{ Carbon::parse($row->created_at)->format('M. d, Y') }}
             @endinteract
             @interact('column_amount', $row)
                 {{-- ₱ {{  number_format(($row->amount) ?? 0 , 2) }} --}}
             @endinteract
             @interact('column_action', $row)
             <x-ts-dropdown icon="ellipsis-vertical" static lg>
                 @if($row->status == 'PENDING')
                     <a href="{{route('reimbursement.edit',  ['id' => $row->id])}}">
                         <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                     </a>
                 @endif
                 <a href="{{route('reimbursement.view', ['id' => $row->id])}}">
                     <x-ts-dropdown.items text="View" separator icon="eye" />
                 </a>
                 <a>
                     <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                 </a>
             </x-ts-dropdown>
             @endinteract
         </x-ts-table>
       </div>


        <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Reimbursement" href="{{ route('reimbursement.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>

</div>
