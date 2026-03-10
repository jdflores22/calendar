<?php

namespace App\Service;

class TimezoneService
{
    // Simple: Everything uses Philippines timezone
    private const SYSTEM_TIMEZONE = 'Asia/Manila';
    
    /**
     * Get the system timezone (Philippines)
     */
    public function getSystemTimezone(): string
    {
        return self::SYSTEM_TIMEZONE;
    }
    
    /**
     * SIMPLE: Convert UTC DateTime to Philippines timezone and return formatted string
     * This is the ONLY method we need for consistent display
     */
    public function toPhilippinesTime(\DateTimeInterface $datetime, string $format = 'Y-m-d H:i:s'): string
    {
        // Create a new DateTime object in Philippines timezone from the UTC input
        $philippinesTime = new \DateTime(
            $datetime->format('Y-m-d H:i:s'), 
            new \DateTimeZone('UTC')
        );
        
        // Convert to Philippines timezone
        $philippinesTime->setTimezone(new \DateTimeZone(self::SYSTEM_TIMEZONE));
        
        return $philippinesTime->format($format);
    }
    
    /**
     * SIMPLE: Format for HTML datetime-local inputs (always Philippines time)
     */
    public function toDateTimeLocal(\DateTimeInterface $datetime): string
    {
        return $this->toPhilippinesTime($datetime, 'Y-m-d\TH:i');
    }
    
    /**
     * SIMPLE: Format for display (always Philippines time)
     */
    public function toDisplayTime(\DateTimeInterface $datetime, string $format = 'g:i A'): string
    {
        return $this->toPhilippinesTime($datetime, $format);
    }
    
    /**
     * SIMPLE: Format for calendar API (always Philippines time with timezone)
     */
    public function toCalendarFormat(\DateTimeInterface $datetime): string
    {
        // Create a new DateTime object in Philippines timezone from the UTC input
        $philippinesTime = new \DateTime(
            $datetime->format('Y-m-d H:i:s'), 
            new \DateTimeZone('UTC')
        );
        
        // Convert to Philippines timezone
        $philippinesTime->setTimezone(new \DateTimeZone(self::SYSTEM_TIMEZONE));
        
        return $philippinesTime->format('c'); // ISO 8601 with timezone
    }
    
    /**
     * Convert Philippines time string to UTC DateTime for database storage
     * This is needed for form submissions and API calls
     */
    public function convertToUtc(string $datetime): \DateTime
    {
        // Input is in Philippines timezone (from forms or API)
        $philippinesTime = new \DateTime($datetime, new \DateTimeZone(self::SYSTEM_TIMEZONE));
        
        // Convert to UTC for database storage
        $utcTime = clone $philippinesTime;
        $utcTime->setTimezone(new \DateTimeZone('UTC'));
        
        return $utcTime;
    }
    
    /**
     * Convert UTC DateTime to Philippines timezone DateTime
     * This is needed for some operations that need DateTime objects
     */
    public function convertFromUtc(\DateTimeInterface $datetime): \DateTime
    {
        // Create a new DateTime object in Philippines timezone from the UTC input
        $philippinesTime = new \DateTime(
            $datetime->format('Y-m-d H:i:s'), 
            new \DateTimeZone('UTC')
        );
        
        // Convert to Philippines timezone
        $philippinesTime->setTimezone(new \DateTimeZone(self::SYSTEM_TIMEZONE));
        
        return $philippinesTime;
    }
    
    /**
     * Get current time in Philippines timezone
     */
    public function now(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(self::SYSTEM_TIMEZONE));
    }
}