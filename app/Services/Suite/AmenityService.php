<?php

namespace App\Services\Suite;

use App\Models\Suite\Amenity;
use DomainException;
use Illuminate\Support\Facades\DB;

class AmenityService
{
    public function __construct(protected Amenity $amenity)
    {
    }

    /** @param array{amenity_name: string} $data */
    public function create(array $data): string
    {
        $this->amenity->create([
            'name' => $data['amenity_name'],
        ]);

        return 'Amenity successfully created!';
    }

    /** @param array{id: int, amenity_name: string} $data */
    public function update(array $data): string
    {
        $amenity = $this->amenity->findOrFail($data['id']);
        $amenity->update([
            'name' => $data['amenity_name'],
        ]);

        return 'Amenity updated successfully!';
    }

    /** @param array{id: int} $data */
    public function delete(array $data): string
    {
        return DB::transaction(function () use ($data): string {
            $amenity = $this->amenity->newQuery()
                ->lockForUpdate()
                ->findOrFail($data['id']);

            if (DB::table('room_room_amenities')->where('amenity_id', $amenity->id)->exists()) {
                throw new DomainException('This amenity is assigned to one or more rooms and cannot be deleted.');
            }

            $amenity->delete();

            return 'Amenity '.$amenity->name.' deleted successfully!';
        });
    }
}
