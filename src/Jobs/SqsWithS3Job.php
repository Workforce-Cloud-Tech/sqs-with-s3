<?php

namespace SqsWithS3\Jobs;

use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Contracts\Queue\Job as JobContract;
use SqsWithS3\Traits\SqsWithS3BaseJob;

class SqsWithS3Job extends SqsJob implements JobContract
{
    use SqsWithS3BaseJob;
}
