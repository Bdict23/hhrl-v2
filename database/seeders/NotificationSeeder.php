<?php

namespace Database\Seeders;

use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class NotificationSeeder extends Seeder
{
    /**
     * Seed 5 distinct real-time notification messages with voucher reference codes.
     */
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            return;
        }

        $samples = [
            [
                'title'          => 'New Pending Payment - Voucher LYR-PX9021',
                'message'        => 'Customer submitted Gcash payment proof for Court 1 reservation under voucher LYR-PX9021. Awaiting verification.',
                'url'            => route('admin.dashboard'),
                'type'           => 'pending_payment',
                'booking'        => 102,
                'reference_code' => 'LYR-PX9021',
            ],
            [
                'title'          => 'Court Reservation Updated - Voucher LYR-CR7720',
                'message'        => 'Court 2 schedule for voucher LYR-CR7720 was rescheduled to 6:00 PM - 8:00 PM.',
                'url'            => route('admin.dashboard'),
                'type'           => 'court_updated',
                'booking'        => null,
                'reference_code' => 'LYR-CR7720',
            ],
            [
                'title'          => 'Payment Approved - Voucher LYR-PV5512',
                'message'        => 'Payment for booking voucher LYR-PV5512 has been verified and marked as paid.',
                'url'            => route('admin.dashboard'),
                'type'           => 'payment_approved',
                'booking'        => 101,
                'reference_code' => 'LYR-PV5512',
            ],
            [
                'title'          => 'Declined - Voucher LYR-CX4409',
                'message'        => 'Booking voucher LYR-CX4409 has been declined and marked as cancelled.',
                'url'            => route('admin.dashboard'),
                'type'           => 'cancellation_requested',
                'booking'        => 99,
                'reference_code' => 'LYR-CX4409',
            ],
            [
                'title'          => 'System Alert',
                'message'        => 'Scheduled maintenance in Pickle Court lighting system tonight at 11:00 PM.',
                'url'            => route('admin.dashboard'),
                'type'           => 'system_alert',
                'booking'        => null,
                'reference_code' => null,
            ],
        ];

        foreach ($samples as $sample) {
            Notification::send(
                $users,
                new GeneralNotification(
                    $sample['title'],
                    $sample['message'],
                    $sample['url'],
                    $sample['type'],
                    $sample['booking'],
                    $sample['reference_code']
                )
            );
        }
    }
}
