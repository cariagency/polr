<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
    ];

    function __construct($app) {
        parent::__construct($app);
        if (env('SOCIALITE_PROVIDER')) {
            $this->listen[\SocialiteProviders\Manager\SocialiteWasCalled::class] = ['SocialiteProviders\\Okta\\OktaExtendSocialite@handle'];
        }
    }
}
