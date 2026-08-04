<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Meeting;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\DateTime;
use App\Shared\Domain\ValueObject\HttpsUrl;
use App\Shared\Domain\ValueObject\NonEmptyStringValue;
use App\Shared\Domain\ValueObject\UlidValue;
use Tests\Unit\Kal\Domain\Mother\MeetingMother;

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
})->throws(InvalidArgumentException::class, 'The URL must use HTTPS.');

it('throws when the meeting url is not a valid url', function (): void {
    HttpsUrl::fromString('not-a-url');
})->throws(InvalidArgumentException::class, 'The value is not a valid URL.');

it('throws when the meeting url is empty', function (): void {
    HttpsUrl::fromString('');
})->throws(InvalidArgumentException::class, 'The value is not a valid URL.');

it('accepts a valid https meeting url', function (): void {
    $url = HttpsUrl::fromString('https://meet.google.com/abc-defg-hij');

    expect($url->value())->toBe('https://meet.google.com/abc-defg-hij');
});

it('throws when the timezone is invalid', function (): void {
    MeetingMother::create(timezone: 'Invalid/Timezone');
})->throws(KalException::class, 'The meeting timezone is invalid.');

it('creates a meeting with all fields populated', function (): void {
    $scheduledAt = DateTime::create('2026-09-01 10:00:00', 'Europe/Madrid');
    $url = HttpsUrl::fromString('https://zoom.us/j/999');
    $title = NonEmptyStringValue::create('KAL kick-off');

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

// --- reconstitute ---

it('reconstitutes a meeting preserving its persisted id', function (): void {
    $id = UlidValue::generate();
    $scheduledAt = DateTime::create('2026-08-15 18:00:00', 'Europe/Madrid');
    $url = HttpsUrl::fromString('https://zoom.us/j/123456789');
    $title = NonEmptyStringValue::create('Weekly catch-up');

    $meeting = Meeting::reconstitute($id, $scheduledAt, $url, $title, 'Europe/Madrid');

    expect($meeting->id->equals($id))->toBeTrue()
        ->and($meeting->scheduledAt->equals($scheduledAt))->toBeTrue()
        ->and($meeting->url->equals($url))->toBeTrue()
        ->and($meeting->title->equals($title))->toBeTrue()
        ->and($meeting->timezone)->toBe('Europe/Madrid');
});

it('rejects reconstituting a meeting with a corrupted timezone', function (): void {
    Meeting::reconstitute(
        UlidValue::generate(),
        DateTime::create('2026-08-15 18:00:00'),
        HttpsUrl::fromString('https://zoom.us/j/123456789'),
        NonEmptyStringValue::create('Weekly catch-up'),
        'Invalid/Timezone',
    );
})->throws(KalException::class, 'The meeting timezone is invalid.');
