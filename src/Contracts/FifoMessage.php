<?php

namespace SqsWithS3\Contracts;

/**
 * Implement this contract on a queued Job to dispatch it onto a FIFO SQS queue
 * with a deterministic MessageGroupId and MessageDeduplicationId.
 *
 * - MessageGroupId: required by FIFO. Messages with the same group are processed in order.
 *   For workloads where ordering is not required, you can return any stable identifier
 *   that partitions traffic for throughput (e.g. accountId).
 *
 * - MessageDeduplicationId: required by FIFO when content-based dedup is not enabled.
 *   Should be a deterministic hash of the logical message (e.g. sha256 of payload +
 *   identifying keys) so that retries/duplicates produce the same id within the 5-min
 *   SQS dedup window.
 */
interface FifoMessage
{
    public function getMessageGroupId(): string;

    public function getMessageDeduplicationId(): string;
}
