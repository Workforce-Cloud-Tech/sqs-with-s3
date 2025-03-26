
# SQS WITH S3- Overcoming SQS Size Limits with S3


SQS-with-S3 is a lightweight and efficient PHP package that helps overcome Amazon SQS's 256 KB message size limit. When a payload exceeds 200 KB, this package automatically uploads it to Amazon S3 and stores only a reference in SQS, ensuring seamless and scalable message handling.

### Features


- Automatically detects and uploads payloads over 200 KB to Amazon S3
- Stores an S3 reference in Amazon SQS for easy retrieval


### Installation

Install via Composer :

```bash
composer require recruitcrm/sqs-with-s3
```
Add below configurations in queue.php :

```php
'sqs-with-s3' => [
    'driver' => 'sqs-with-s3',
    'key' => env('SQS_KEY', 'your-public-key'),
    'secret' => env('SQS_SECRET', 'your-secret-key'),
    'prefix' => env('SQS_PREFIX', 'https://sqs.{region}.amazonaws.com/{your-account-id}'),
    'queue' => env('SQS_QUEUE', 'your-queue-name'),
    'region' => env('SQS_REGION', 'your-region'),
    'disk_options' => [
        'always_store' => false,
        'cleanup' => true,
        'disk' => 's3',
        'prefix' => env('SQS_QUEUE', 'your-queue-name')
    ],
],
```

For booting, register to your service provider in app.php

```php
$app->register(SqsWithS3\Providers\SqsWithS3ServiceProvider::class);
```




## Environment Variables

Required env variables

`SQS_KEY`

`SQS_SECRET`

`SQS_REGION`

