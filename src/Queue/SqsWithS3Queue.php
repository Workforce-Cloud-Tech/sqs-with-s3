<?php

namespace SqsWithS3\Queue;

use DateInterval;
use Aws\Sqs\SqsClient;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Queue\SqsQueue;
use Illuminate\Contracts\Queue\Job;
use SqsWithS3\Traits\PayloadPointers;
use SqsWithS3\Jobs\SqsWithS3Job;
use SqsWithS3\Contracts\FifoMessage;

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
     *
     * @var array
     */
    protected $s3Options;

    public function __construct(
        SqsClient $sqs,
        $default,
        $s3Options,
        $prefix = '',
        $suffix = '',
        $dispatchAfterCommit = false
    ) {
        $this->s3Options = $s3Options;

        parent::__construct($sqs, $default, $prefix, $suffix, $dispatchAfterCommit);
    }

    /**
     * Push a new job onto the queue.
     *
     * Overridden so that jobs implementing {@see FifoMessage} can attach
     * MessageGroupId / MessageDeduplicationId to the SQS send call.
     *
     * Matches Laravel 7's SqsQueue::push() signature exactly — do not use
     * enqueueUsing() here (that helper was introduced in Laravel 8).
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw(
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            $this->fifoOptions($job)
        );
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * Matches Laravel 7's SqsQueue::later() signature exactly.
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw(
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            $this->fifoOptions($job),
            $delay
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options   Supports MessageGroupId and MessageDeduplicationId for FIFO queues.
     * @param  mixed  $delay
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [], $delay = 0)
    {
        $queueUrl = $this->getQueue($queue);

        $message = [
            'QueueUrl' => $queueUrl,
            'MessageBody' => $payload,
        ];

        if (strlen($payload) >= self::MAX_SQS_LENGTH || Arr::get($this->s3Options, 'always_store')) {
            $uuid = json_decode($payload)->uuid;
            $filepath = "queue-payloads/" . "{$uuid}.json";
            $this->getConfiguredStorage()->put($filepath, $payload);

            $message['MessageBody'] = json_encode(['pointer' => $filepath]);
        }

        // FIFO queues require MessageGroupId. When a job implements FifoMessage the caller
        // will pass the ids through $options. If the queue is FIFO but no group id was
        // supplied, fall back to a safe default so the send does not fail.
        if ($this->isFifoQueue($queueUrl)) {
            $message['MessageGroupId'] = $options['MessageGroupId']
                ?? Arr::get($this->s3Options, 'default_message_group_id', 'default');

            if (!empty($options['MessageDeduplicationId'])) {
                $message['MessageDeduplicationId'] = $options['MessageDeduplicationId'];
            }

            // FIFO queues do not support per-message DelaySeconds.
            $delay = 0;
        }

        if ($delay) {
            $message['DelaySeconds'] = $this->secondsUntil($delay);
        }

        return $this->sqs->sendMessage($message)->get('MessageId');
    }

    /**
     * Pop the next job off of the queue.
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
     */
    public function clear($queue)
    {
        return tap(parent::clear($queue), function (): void {
            if (Arr::get($this->s3Options, 'cleanup')) {
                $this->getConfiguredStorage()->deleteDirectory("queue-payloads");
            }
        });
    }

    /**
     * Extract FIFO options from a dispatched job, when the job implements FifoMessage.
     */
    protected function fifoOptions($job): array
    {
        if (!is_object($job) || !($job instanceof FifoMessage)) {
            return [];
        }

        return [
            'MessageGroupId' => $job->getMessageGroupId(),
            'MessageDeduplicationId' => $job->getMessageDeduplicationId(),
        ];
    }

    /**
     * FIFO queue URLs always end with `.fifo`.
     */
    protected function isFifoQueue(string $queueUrl): bool
    {
        return Str::endsWith($queueUrl, '.fifo');
    }
}
