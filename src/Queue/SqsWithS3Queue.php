<?php

namespace SqsWithS3\Queue;

use DateInterval;
use Aws\Sqs\SqsClient;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Queue\SqsQueue;
use Illuminate\Contracts\Queue\Job;
use SqsWithS3\Traits\PayloadPointers;
use SqsWithS3\Jobs\SqsWithS3Job;

class SqsWithS3Queue extends SqsQueue
{
    use PayloadPointers;

    /**
     * The max length of a SQS message before it must be stored as a pointer.
     *
     * @var int
     */
    public const MAX_SQS_LENGTH = 200000;

    /**
     * The storage options to save large payloads.
     */
    protected array $s3Options;

    /**
     * Create a new Amazon SQS queue instance.
     *
     * @param  string  $default
     * @param  array  $s3Options
     * @param  string  $prefix
     * @param  string  $suffix
     * @param  bool  $dispatchAfterCommit
     * @return void
     */
    public function __construct(
        SqsClient $sqs,
        $default,
        $s3Options,
        $prefix = '',
        $suffix = '',
        $dispatchAfterCommit = false,
    ) {
        $this->s3Options = $s3Options;

        parent::__construct($sqs, $default, $prefix, $suffix, $dispatchAfterCommit);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  mixed  $delay
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [], $delay = 0)
    {
        $message = [
            'QueueUrl' => $this->getQueue($queue),
            'MessageBody' => $payload,
        ];

        if (strlen($payload) >= self::MAX_SQS_LENGTH || Arr::get($this->s3Options, 'always_store')) {
            $uuid = json_decode($payload)->uuid;
            $filepath = "queue-payloads/" . "{$uuid}.json";
            $this->getConfiguredStorage()->put($filepath, $payload);

            $message['MessageBody'] = json_encode(['pointer' => $filepath]);
        }

        if ($delay) {
            $message['DelaySeconds'] = $this->secondsUntil($delay);
        }

        return $this->sqs->sendMessage($message)->get('MessageId');
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  DateTimeInterface|DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            $delay,
            function ($payload, $queue) use ($delay) {
                return $this->pushRaw($payload, $queue, [], $delay);
            }
        );
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return Job|null
     */
    public function pop($queue = null)
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new SqsWithS3Job(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue,
                $this->s3Options
            );
        }
    }

    /**
     * Delete all the jobs from the queue.
     *
     * @param  string  $queue
     * @return int
     */
    public function clear($queue)
    {
        return tap(parent::clear($queue), function (): void {
            if (Arr::get($this->s3Options, 'cleanup')) {
                $this->getConfiguredStorage()->deleteDirectory("queue-payloads");
            }
        });
    }
}
