<?php

namespace Laravel\Cashier\Console;

use Illuminate\Console\Command;

class UpdateSubscriptionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:subscription:status {chunk_size=25} {empty_only=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update subscription status from stripe';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $chunk_size = $this->argument('chunk_size');

        $empty_only = $this->argument('empty_only') == 'true';

        $subscriptions = \Laravel\Cashier\Subscription::select()
            ->when($empty_only, function($collection) {
                return $collection->where('stripe_status', '');
            })
            ->get();

        $chunks_qty = ceil($subscriptions->count() / $chunk_size);

        $subscriptions->chunk($chunk_size)
            ->each(function($chunk, $key) use ($chunks_qty) {
                $key++;
                $this->info("Processing chunk #$key / $chunks_qty");
                $chunk->each(function($subscription) {
                    $this->info("    Processing subscribtion #{$subscription->stripe_id}");
                    $this->info("        Status before update = '{$subscription->stripe_status}'");
                    $subscription->syncStripeStatus();
                    $this->info("        Status after update = '{$subscription->stripe_status}'");
                });
                usleep(100);
            });
    }
}

