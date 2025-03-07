<?php

namespace SqsWithS3\Traits;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Arr;
use Illuminate\Container\Container;
use SqsWithS3\Traits\PayloadPointers;

trait SqsWithS3BaseJob
{
    use PayloadPointers;

    /**
     * The Amazon SQS client instance.
     *
     * @var SqsClient
     */
    protected $sqs;

    /**
     * The Amazon SQS job instance.
     *
     * @var array
     */
    protected $job;

    /**
     * Holds the raw body to prevent fetching the file from
     * s3 multiple times.
     */
    protected string $cachedRawBody = '';

    /**
     * s3 options for the job.
     */
    protected array $s3Options;

    /**
     * Create a new job instance.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @return void
     */
    public function __construct(Container $container, SqsClient $sqs, array $job, $connectionName, $queue, array $s3Options)
    {
        $this->sqs = $sqs;
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->s3Options = $s3Options;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete(): void
    {
        parent::delete();

        if (Arr::get($this->s3Options, 'cleanup') && $pointer = $this->getPayloadLocation()) {
            $this->getConfiguredStorage()->delete($pointer);
        }
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        if ($this->cachedRawBody) {
            return $this->cachedRawBody;
        }

        if ($pointer = $this->getPayloadLocation()) {
            return $this->cachedRawBody = $this->getConfiguredStorage()->get($pointer);
        }

        return parent::getRawBody();
    }
}
