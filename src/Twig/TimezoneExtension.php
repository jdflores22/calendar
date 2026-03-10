<?php

namespace App\Twig;

use App\Service\TimezoneService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TimezoneExtension extends AbstractExtension
{
    public function __construct(
        private TimezoneService $timezoneService
    ) {}

    public function getFilters(): array
    {
        return [
            // SIMPLE: Only 3 filters needed
            new TwigFilter('ph_time', [$this, 'toPhilippinesTime']),
            new TwigFilter('ph_date', [$this, 'toPhilippinesDate']),
            new TwigFilter('ph_datetime_local', [$this, 'toDateTimeLocal']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('system_timezone', [$this, 'getSystemTimezone']),
            new TwigFunction('system_timezone_offset', [$this, 'getSystemTimezoneOffset']),
            new TwigFunction('timezone_config_js', [$this, 'getTimezoneConfigJs']),
        ];
    }

    /**
     * SIMPLE: Convert to Philippines time for form inputs
     */
    public function toDateTimeLocal(\DateTimeInterface $dateTime): string
    {
        return $this->timezoneService->toDateTimeLocal($dateTime);
    }

    /**
     * SIMPLE: Convert to Philippines time for display
     */
    public function toPhilippinesTime(\DateTimeInterface $dateTime, string $format = 'g:i A'): string
    {
        return $this->timezoneService->toDisplayTime($dateTime, $format);
    }

    /**
     * SIMPLE: Convert to Philippines date for display
     */
    public function toPhilippinesDate(\DateTimeInterface $dateTime, string $format = 'l, F j, Y g:i A'): string
    {
        return $this->timezoneService->toDisplayTime($dateTime, $format);
    }

    /**
     * Get system timezone name
     */
    public function getSystemTimezone(): string
    {
        return $this->timezoneService->getSystemTimezone();
    }

    /**
     * Get system timezone offset
     */
    public function getSystemTimezoneOffset(): string
    {
        return '+08:00'; // Philippines is always UTC+8
    }

    /**
     * Get timezone configuration for JavaScript
     */
    public function getTimezoneConfigJs(): array
    {
        return [
            'timezone' => $this->timezoneService->getSystemTimezone(),
            'offset' => '+08:00',
            'name' => 'Philippines Standard Time',
            'abbreviation' => 'PST',
        ];
    }
}