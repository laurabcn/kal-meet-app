<?php

declare(strict_types=1);

use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\Url;

it('creates a valid http url', function (): void {
    $url = Url::fromString('http://example.com/path');

    expect($url->value())->toBe('http://example.com/path');
});

it('creates a valid https url via base Url', function (): void {
    $url = Url::fromString('https://example.com');

    expect($url->value())->toBe('https://example.com');
});

it('throws when the url is invalid', function (): void {
    Url::fromString('not-a-url');
})->throws(InvalidArgumentException::class, 'invalid_url');

it('throws when the url is empty', function (): void {
    Url::fromString('');
})->throws(InvalidArgumentException::class, 'invalid_url');

it('compares urls by value', function (): void {
    $a = Url::fromString('https://example.com');
    $b = Url::fromString('https://example.com');
    $c = Url::fromString('https://other.com');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('creates an https url', function (): void {
    $url = HttpsUrl::fromString('https://zoom.us/j/1');

    expect($url)->toBeInstanceOf(HttpsUrl::class)
        ->and($url)->toBeInstanceOf(Url::class)
        ->and($url->value())->toBe('https://zoom.us/j/1');
});

it('rejects http for HttpsUrl', function (): void {
    HttpsUrl::fromString('http://zoom.us/j/1');
})->throws(InvalidArgumentException::class, 'url_must_be_https');

it('accepts HTTPS scheme case-insensitively for HttpsUrl', function (): void {
    $url = HttpsUrl::fromString('HTTPS://zoom.us/j/1');

    expect($url->value())->toBe('HTTPS://zoom.us/j/1');
});
