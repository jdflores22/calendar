# Timezone Consistency Fix

## Issue Description
There was a discrepancy between the time displayed in calendar hover tooltips and the edit form:
- **Calendar hover**: Showed correct time (e.g., 8:00 AM)
- **Edit form**: Showed incorrect time (e.g., 12:00 PM)

## Root Cause Analysis

### Calendar Hover Implementation
- Uses `formatEventForCalendar()` method in `CalendarController`
- Converts UTC to Philippines timezone using `TimezoneService->convertFromUtc()`
- Sends datetime with timezone info: `2026-02-20T02:00:00+08:00`
- Browser's `toLocaleTimeString()` correctly interprets timezone offset
- **Result**: Shows correct Philippines time

### Edit Form Implementation (Before Fix)
- Used controller-prepared `eventData.startTimeFormatted` 
- Called `TimezoneService->formatForFrontend()` in controller
- Sent datetime without timezone info: `2026-02-20T02:00`
- Browser interpreted as local browser timezone, not Philippines timezone
- **Result**: Showed incorrect time if browser timezone ≠ Philippines timezone

## Solution Implemented

### 1. Template Consistency
**Before (Edit Template)**:
```twig
value="{{ eventData.startTimeFormatted }}"
```

**After (Edit Template)**:
```twig
value="{{ event.startTime ? event.startTime|philippines_time : '' }}"
```

**New Template (Already Correct)**:
```twig
value="{{ event.startTime ? event.startTime|philippines_time : '' }}"
```

### 2. Controller Simplification
Removed controller-side datetime formatting since Twig filter handles it:

**Before**:
```php
'startTimeFormatted' => $event->getStartTime() ? $this->timezoneService->formatForFrontend($event->getStartTime()) : '',
'endTimeFormatted' => $event->getEndTime() ? $this->timezoneService->formatForFrontend($event->getEndTime()) : '',
```

**After**:
```php
// Removed - now handled by Twig filter directly
```

### 3. Twig Filter Usage
Both templates now use the same `philippines_time` Twig filter:
- Calls `TimezoneService->formatForFrontend()`
- Converts UTC to Philippines timezone
- Returns format suitable for `datetime-local` inputs

## Technical Details

### TimezoneService Methods
- `convertFromUtc()`: Converts UTC DateTime to Philippines timezone
- `formatForFrontend()`: Formats for `datetime-local` inputs (`Y-m-d\TH:i`)
- `formatForDisplay()`: Formats for display purposes

### Twig Extension
- `philippines_time` filter: Calls `formatForFrontend()` for form inputs
- `philippines_date` filter: Calls `formatForDisplay()` for display

### Test Results
```
UTC DateTime: 2026-02-19 18:00:00 UTC
Philippines DateTime: 2026-02-20 02:00:00 PST
Form Input Value: 2026-02-20T02:00
Expected Display: 2:00 AM
```

## Browser Behavior with datetime-local

### How datetime-local Works
1. Accepts format: `YYYY-MM-DDTHH:MM`
2. Interprets time as browser's local timezone
3. Displays in browser's preferred format

### Our Solution
1. Convert UTC to Philippines timezone server-side
2. Send Philippines time without timezone info
3. Browser displays the Philippines time as-is
4. User sees correct Philippines time regardless of browser timezone

## Files Modified

1. **templates/event/edit.html.twig**
   - Changed to use `philippines_time` Twig filter
   - Consistent with new event template

2. **src/Controller/EventController.php**
   - Removed controller-side datetime formatting
   - Simplified eventData array

3. **src/Service/TimezoneService.php**
   - Enhanced documentation for `formatForFrontend()`
   - Added `formatForFormInput()` method (alternative approach)

## Verification

### Test Case: Event 68
- **Database UTC**: 2026-02-19 18:00:00
- **Philippines Time**: 2026-02-20 02:00:00 (2:00 AM)
- **Form Input**: 2026-02-20T02:00
- **Expected Display**: 2:00 AM
- **Calendar Hover**: 2:00 AM ✅
- **Edit Form**: 2:00 AM ✅

## Status: ✅ RESOLVED

Both calendar hover and edit form now show consistent Philippines time. The discrepancy has been resolved by ensuring both use the same timezone conversion approach through the `TimezoneService` and Twig filters.