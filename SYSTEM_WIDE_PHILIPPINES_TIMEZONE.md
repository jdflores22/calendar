# System-Wide Philippines Timezone Implementation

## Overview
Implemented a comprehensive system-wide Philippines timezone (UTC+8) configuration that ensures ALL pages and components consistently use Philippines Standard Time.

## Core Implementation

### 1. Enhanced TimezoneService
**Location**: `src/Service/TimezoneService.php`

**Key Features**:
- Centralized timezone configuration (`SYSTEM_TIMEZONE = 'Asia/Manila'`)
- System-wide timezone utilities
- JavaScript integration methods
- Consistent UTC ↔ Philippines conversion

**New Methods**:
```php
getSystemTimezone(): string                    // Returns 'Asia/Manila'
getSystemTimezoneOffset(): string              // Returns '+08:00'
getCurrentDate(): string                       // Current Philippines date (Y-m-d)
getCurrentTime(): string                       // Current Philippines time (H:i)
getCurrentDateTimeForJs(): string              // For JavaScript datetime inputs
getTimezoneConfigForJs(): array                // Configuration for JavaScript
```

### 2. Enhanced Twig Extension
**Location**: `src/Twig/TimezoneExtension.php`

**New Filters**:
- `system_time` - Convert UTC to Philippines time for forms
- `system_date` - Convert UTC to Philippines time for display

**New Functions**:
- `system_timezone()` - Get system timezone name
- `system_timezone_offset()` - Get timezone offset
- `current_system_time()` - Current Philippines time
- `current_system_date()` - Current Philippines date
- `timezone_config_js()` - JavaScript configuration

### 3. Global JavaScript Utilities
**Location**: `templates/base.html.twig`

**Global Object**: `window.SystemTimezone`
```javascript
SystemTimezone.now()                    // Current Philippines time
SystemTimezone.convert(date)            // Convert any date to Philippines time
SystemTimezone.formatForInput(date)     // Format for datetime-local inputs
SystemTimezone.getCurrentDate()         // Current Philippines date (YYYY-MM-DD)
SystemTimezone.getCurrentTime()         // Current Philippines time (HH:MM)
SystemTimezone.getTimezone()           // 'Asia/Manila'
SystemTimezone.getOffset()             // '+08:00'
SystemTimezone.formatWithTimezone(date) // Format with timezone display
```

## Updated Templates

### Event Templates
- ✅ `templates/event/edit.html.twig` - Uses `system_time` filter
- ✅ `templates/event/new.html.twig` - Uses `system_time` filter  
- ✅ `templates/event/show.html.twig` - Uses `system_date` filter
- ✅ `templates/event/index.html.twig` - Uses `system_date` filter

### Dashboard Templates
- ✅ `templates/dashboard/index_enhanced.html.twig` - Uses `system_date` filter
- ✅ `templates/security/dashboard.html.twig` - Uses `system_date` filter

### Component Templates
- ✅ `templates/components/ui_blocks.html.twig` - Uses `system_date` filter

### Calendar Template
- ✅ `templates/calendar/index.html.twig` - Uses global `SystemTimezone` utilities
- ✅ Real-time clock uses system timezone
- ✅ Event creation uses system timezone defaults
- ✅ Statistics calculated with system timezone

## System-Wide Consistency

### Database Storage (UTC)
```
All datetime fields stored in UTC timezone
Example: 2026-02-12 00:01:00 UTC
```

### Display (Philippines UTC+8)
```
All user-facing times shown in Philippines timezone
Example: February 12, 2026 8:01 AM PST
```

### Form Inputs (Philippines)
```
All datetime-local inputs pre-filled with Philippines time
Example: 2026-02-12T08:01
```

### JavaScript Operations (Philippines)
```
All client-side date operations use Philippines timezone
Example: SystemTimezone.getCurrentDate() returns Philippines date
```

## Verification Points

### Event 76 Test Case
- **Database**: `2026-02-12 00:01:00` UTC
- **All Pages Should Show**: `8:01 AM` Philippines time
- **Form Inputs Should Show**: `08:01` Philippines time

### Real-time Elements
- **Calendar Clock**: Updates every second with Philippines time
- **Dashboard Times**: All show Philippines time
- **Event Creation**: Defaults to current Philippines time

### Cross-Page Consistency
- **Calendar Hover**: `8:01 AM - 10:30 AM`
- **Event Details**: `Thursday, February 12, 2026 8:01 AM - 10:30 AM`
- **Event Edit**: `08:01` in datetime input
- **Event Index**: `Feb 12, 2026 8:01 AM`
- **Dashboard**: `8:01 AM`

## Configuration Management

### Single Source of Truth
```php
// TimezoneService.php
private const SYSTEM_TIMEZONE = 'Asia/Manila';
private const SYSTEM_TIMEZONE_OFFSET = '+08:00';
```

### Easy Timezone Changes
To change the system timezone:
1. Update `SYSTEM_TIMEZONE` constant in `TimezoneService`
2. Update `SYSTEM_TIMEZONE_OFFSET` constant
3. Clear cache: `php bin/console cache:clear`
4. All pages automatically use new timezone

### JavaScript Integration
```javascript
// Available globally on all pages
window.SYSTEM_TIMEZONE_CONFIG = {
    "timezone": "Asia/Manila",
    "offset": "+08:00", 
    "name": "Philippines Standard Time",
    "abbreviation": "PST"
}
```

## Benefits

### 1. Consistency
- All pages use the same timezone
- No more discrepancies between calendar and forms
- Unified user experience

### 2. Maintainability  
- Single configuration point
- Easy to change system timezone
- Centralized timezone logic

### 3. Developer Experience
- Clear timezone utilities
- Consistent naming conventions
- Global JavaScript helpers

### 4. User Experience
- Predictable time display
- No timezone confusion
- Consistent across all features

## Migration Notes

### Filter Changes
- `philippines_time` → `system_time` (backward compatible)
- `philippines_date` → `system_date` (backward compatible)
- Old filters still work but new ones are preferred

### JavaScript Changes
- Global `SystemTimezone` object available on all pages
- Replaces manual timezone conversion code
- Consistent API across all components

## Status: ✅ COMPLETE

The entire TESDA Calendar system now uses Philippines timezone (UTC+8) consistently across:

- ✅ **All Event Pages** - Create, edit, view, list
- ✅ **Calendar Interface** - Hover, display, creation
- ✅ **Dashboard Pages** - All time displays
- ✅ **Form Inputs** - All datetime fields
- ✅ **Real-time Elements** - Clocks, updates
- ✅ **JavaScript Operations** - Client-side date handling
- ✅ **Database Operations** - UTC storage, Philippines display

**Result**: Complete timezone consistency throughout the application. All users see Philippines time (UTC+8) regardless of their browser's local timezone setting.