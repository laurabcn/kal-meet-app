<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use Tests\Kal\Domain\Mother\MeetingMother;

it('creates a meeting with default timezone', function (): void {
    $meeting = MeetingMother::create();

    expect($meeting->id->value())->not->toBeEmpty()
        ->and($meeting->scheduledAt)->toBeInstanceOf(DateTime::class)
        ->and($meeting->url->value())->toBe('https://zoom.us/j/123456789')
        ->and($meeting->title->value())->toBe('Weekly catch-up')
        ->and($meeting->timezone)->toBe('Europe/Madrid');
});

it('creates a meeting with a custom timezone', function (): void {
    $meeting = MeetingMother::create(timezone: 'America/New_York');

    expect($meeting->timezone)->toBe('America/New_York');
});

it('throws when the meeting url is not https', function (): void {
    HttpsUrl::fromString('http://zoom.us/j/123456789');
})->throws(InvalidArgumentException::class, 'url_must_be_https');

it('throws when the meeting url is not a valid url', function (): void {
    HttpsUrl::fromString('not-a-url');
})->throws(InvalidArgumentException::class, 'invalid_url');

it('throws when the meeting url is empty', function (): void {
    HttpsUrl::fromString('');
})->throws(InvalidArgumentException::class, 'invalid_url');

it('accepts a valid https meeting url', function (): void {
    $url = HttpsUrl::fromString('https://meet.google.com/abc-defg-hij');

    expect($url->value())->toBe('https://meet.google.com/abc-defg-hij');
});

it('throws when the timezone is invalid', function (): void {
    MeetingMother::create(timezone: 'Invalid/Timezone');
})->throws(KalException::class, 'kal_meeting_invalid_timezone');

it('creates a meeting with all fields populated', function (): void {
    $scheduledAt = DateTime::create('2026-09-01 10:00:00', 'Europe/Madrid');
    $url = HttpsUrl::fromString('https://zoom.us/j/999');
    $title = new NonEmptyStringValue('KAL kick-off');

    $meeting = MeetingMother::create(
        scheduledAt: $scheduledAt,
        url: $url,
        title: $title,
        timezone: 'Europe/London',
    );

    expect($meeting->scheduledAt->equals($scheduledAt))->toBeTrue()
        ->and($meeting->url->equals($url))->toBeTrue()
        ->and($meeting->title->equals($title))->toBeTrue()
        ->and($meeting->timezone)->toBe('Europe/London');
});
