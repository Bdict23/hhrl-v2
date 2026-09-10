<?php

namespace App\Console\Commands;

use App\Events\PickleBooking;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendTestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:send-test {type? : The type to send: pending, updated, approved, cancelled, alert, or all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send and broadcast test notifications via Laravel Reverb';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->error('No users found in database to notify.');
            return self::FAILURE;
        }

        $typeArg = strtolower($this->argument('type') ?? 'all');

        $notificationsMap = [
            'pending' => [
                'title'          => 'New Pending Payment - Voucher LYR-PX9021',
                'message'        => 'Customer submitted Gcash payment proof for Court 1 reservation under voucher LYR-PX9021. Awaiting verification.',
                'url'            => route('admin.dashboard'),
                'type'           => 'pending_payment',
                'booking'        => 102,
                'reference_code' => 'LYR-PX9021',
            ],
            'updated' => [
                'title'          => 'Court Reservation Updated - Voucher LYR-CR7720',
                'message'        => 'Court 2 schedule for voucher LYR-CR7720 was rescheduled to 6:00 PM - 8:00 PM.',
                'url'            => route('admin.dashboard'),
                'type'           => 'court_updated',
                'booking'        => null,
                'reference_code' => 'LYR-CR7720',
            ],
            'approved' => [
                'title'          => 'Payment Approved - Voucher LYR-PV5512',
                'message'        => 'Payment for booking voucher LYR-PV5512 has been verified and marked as paid.',
                'url'            => route('admin.dashboard'),
                'type'           => 'payment_approved',
                'booking'        => 101,
                'reference_code' => 'LYR-PV5512',
            ],
            'cancelled' => [
                'title'          => 'Declined - Voucher LYR-CX4409',
                'message'        => 'Booking voucher LYR-CX4409 has been declined and marked as cancelled.',
                'url'            => route('admin.dashboard'),
                'type'           => 'cancellation_requested',
                'booking'        => 99,
                'reference_code' => 'LYR-CX4409',
            ],
            'alert' => [
                'title'          => 'System Alert',
                'message'        => 'Scheduled maintenance in Pickle Court lighting system tonight at 11:00 PM.',
                'url'            => route('admin.dashboard'),
                'type'           => 'system_alert',
                'booking'        => null,
                'reference_code' => null,
            ],
        ];

        $toSend = [];
        if ($typeArg === 'all') {
            $toSend = array_values($notificationsMap);
        } elseif (isset($notificationsMap[$typeArg])) {
            $toSend = [$notificationsMap[$typeArg]];
        } else {
            $this->warn("Unknown type '{$typeArg}'. Sending all 5 notification messages.");
            $toSend = array_values($notificationsMap);
        }

        $this->info("Sending " . count($toSend) . " notification(s) to " . $users->count() . " user(s)...");

        foreach ($toSend as $item) {
            Notification::send(
                $users,
                new GeneralNotification(
                    $item['title'],
                    $item['message'],
                    $item['url'],
                    $item['type'],
                    $item['booking'],
                    $item['reference_code']
                )
            );

            $refLabel = $item['reference_code'] ? " [{$item['reference_code']}]" : '';
            $this->line("  ✔ Sent & Broadcast: <info>{$item['title']}</info>{$refLabel} [{$item['type']}]");
        }

        // Also dispatch PickleBooking event so sidebar and dashboard listeners trigger
        $latestBooking = Booking::latest()->first();
        PickleBooking::dispatch($latestBooking);
        $this->line("  ✔ Broadcasted: <info>PickleBooking (update-booking)</info> on channel <comment>hhrl-channel</comment>");

        $this->info('Done! Broadcast pushed to Laravel Reverb.');

        return self::SUCCESS;
    }
}
