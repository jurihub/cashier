<?php

namespace Laravel\Cashier;

use Carbon\Carbon;

class MultisubscriptionBuilder extends SubscriptionBuilder
{
    /**
     * The plans being subscribed to.
     *
     * @var array
     */
    protected $plans = [];
    
    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string  $plan
     * @return void
     */
    public function __construct($owner, $name = 'default')
    {
        $this->owner = $owner;
        $this->name = $name;
    }
    
    /**
     * Add a new Stripe subscription to the Stripe model.
     *
     * @param  string  $code
     * @param  int  $quantity
     */
    public function addPlan($code, $quantity = 1)
    {
        $this->plans[$code] = $quantity;
        return $this;
    }
    
    /**
     * Creates a new Stripe subscription with multiple plans.
     *
     * @param  \Stripe\PaymentMethod|string|null  $paymentMethod
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     */
    public function create($paymentMethod = null, array $options = [])
    {
        $customer = $this->getStripeCustomer($paymentMethod, $options);
          
        $stripeSubscription = $customer->subscriptions->create($this->buildPayload());

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }
        
        // registers the subscription
        $subscription = $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => $stripeSubscription->status,
            'stripe_plan' => '',
            'quantity' => 0,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
        
        // registers the subscription's items
        foreach ($stripeSubscription->items->data as $item) {
            $subscription->subscriptionItems()->create([
                'stripe_id' => $item['id'],
                'stripe_plan' => $item['plan']['id'],
                'quantity' => $item['quantity'],
            ]);
        }

        if ($subscription->incomplete()) {
            (new Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }
        
        return $subscription;
    }
    
    /**
     * Build the payload for subscription creation.
     *
     * @return array
     */
    protected function buildPayload()
    {
        return array_filter([
            'items' => $this->buildPayloadItems(),
            'billing_cycle_anchor' => $this->billingCycleAnchor,
            'coupon' => $this->coupon,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $this->metadata,
            'tax_percent' => $this->getTaxPercentageForPayload(),
            'trial_end' => $this->getTrialEndForPayload(),
            'off_session' => true,
        ]);
    }
    
    protected function buildPayloadItems()
    {
        $items = [];
        foreach ($this->plans as $plan => $quantity) {
            array_push($items, [
                'plan' => $plan,
                'quantity' => $quantity,
            ]);
        }
        return $items;
    }
}
