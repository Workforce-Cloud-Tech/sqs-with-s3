<?php

namespace SqsWithS3\Traits;

use Illuminate\Support\Arr;
use Illuminate\Filesystem\FilesystemAdapter;

trait PayloadPointers
{
    /**
     * returns the location of stored payload if exists.
     */
    protected function getPayloadLocation(): ?string
    {
        return json_decode($this->job['Body'])->pointer ?? null;
    }

    /**
     * Resolves the configured queue disk that stores large payloads.
     */
    protected function getConfiguredStorage(): FilesystemAdapter
    {
        return $this->container->make('filesystem')->disk(Arr::get($this->s3Options, 'disk'));
    }
}