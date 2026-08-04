<?php

declare(strict_types=1);

use App\Kal\Domain\FileSize;
use App\Shared\Domain\Exception\InvalidArgumentException;

it('creates a file size from a positive integer', function (): void {
    $size = FileSize::create(1024);

    expect($size->value())->toBe(1024);
});

it('throws when file size is zero', function (): void {
    FileSize::create(0);
})->throws(InvalidArgumentException::class, 'The file size must be greater than zero.');

it('throws when file size is negative', function (): void {
    FileSize::create(-1);
})->throws(InvalidArgumentException::class, 'The file size must be greater than zero.');

it('throws when file size exceeds the maximum', function (): void {
    FileSize::create(6 * 1024 * 1024);
})->throws(InvalidArgumentException::class);

it('accepts the boundary value just under the max', function (): void {
    $size = FileSize::create(5 * 1024 * 1024);

    expect($size->value())->toBe(5 * 1024 * 1024);
});
