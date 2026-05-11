<?php

declare(strict_types=1);

namespace LaravelDoctor;

interface Check
{
    public function id(): string;

    public function category(): string;

    public function description(): string;

    /**
     * @return Finding[]
     */
    public function run(CheckContext $context): array;
}
