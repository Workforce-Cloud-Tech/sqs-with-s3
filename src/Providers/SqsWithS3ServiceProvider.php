<?php

namespace SqsWithS3\Providers;

use Illuminate\Support\ServiceProvider;
use SqsWithS3\Queue\Connectors\SqsWithS3Connector;

class SqsWithS3ServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function boot(): void
    {
        $manager = $this->app->make('queue');
        $manager->addConnector('sqs-with-s3', fn () => new SqsWithS3Connector());
    }
}
