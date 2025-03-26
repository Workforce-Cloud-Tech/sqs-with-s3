<?php

namespace SqsWithS3\Queue\Connectors;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Arr;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Queue\Connectors\ConnectorInterface;
use SqsWithS3\Queue\SqsWithS3Queue;

class SqsWithS3Connector extends SqsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new SqsWithS3Queue(
            new SqsClient(
                Arr::except($config, ['token'])
            ),
            $config['queue'],
            $config['disk_options'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
            $config['after_commit'] ?? null,
        );
    }
}
