<?php

namespace App\Providers;

use App\Jobs\ApproveStory;
use App\Jobs\Config;
use App\Jobs\ExpireToken;
use App\Jobs\FinalizeStoryValidation;
use App\Jobs\TestJob;
use App\Jobs\ValidateStory;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        // create queue
        // Config::dispatch();

        // $this->app->bind(Config::class.'@handle', fn($job) => $job->handle());
        $this->app->bind(TestJob::class . '@handle', fn ($job) => $job->handle());
        $this->app->bind(ValidateStory::class . '@handle', fn ($job) => $job->handle());
        $this->app->bind(FinalizeStoryValidation::class . '@handle', fn ($job) => $job->handle());
        $this->app->bind(ApproveStory::class . '@handle', fn ($job) => $job->handle());
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
