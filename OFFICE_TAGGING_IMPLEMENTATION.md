# Office Tagging Implementation for Events

## Overview
This implementation adds the ability to tag multiple offices for events in the TESDA Calendar system. Users can now select multiple offices that are involved in a meeting or event, in addition to the primary office.

## Features Added

### 1. Database Changes
- **New Table**: `event_offices` - Junction table for many-to-many relationship between events and offices
- **Foreign Keys**: 
  - `event_id` → `events.id` (CASCADE DELETE)
  - `office_id` → `offices.id` (CASCADE DELETE)

### 2. Entity Updates
- **Event Entity** (`src/Entity/Event.php`):
  - Added `taggedOffices` property (ManyToMany relationship)
  - Added methods: `getTaggedOffices()`, `addTaggedOffice()`, `removeTaggedOffice()`, `clearTaggedOffices()`, `setTaggedOffices()`, `hasTaggedOffice()`, `getTaggedOfficeNames()`
  - Updated `getEffectiveColor()` to consider tagged offices when no primary office is set

### 3. Controller Updates
- **EventController** (`src/Controller/EventController.php`):
  - Updated `populateEventFromData()` to handle `tagged_office_ids[]` array
  - Updated `formatEventForCalendar()` to include tagged offices in API responses

- **CalendarController** (`src/Controller/CalendarController.php`):
  - Updated `formatEventForCalendar()` to include tagged offices data
  - Updated recurring event instances to include tagged offices
  - Updated filter events to include tagged offices
  - Ensures tagged offices are available in all calendar API responses

### 4. Template Updates
- **New Event Form** (`templates/event/new.html.twig`):
  - Added "Tagged Offices" section with checkboxes for all available offices
  - Updated live preview to show tagged offices as badges
  - Added JavaScript to handle tagged office selection and preview updates

- **Edit Event Form** (`templates/event/edit.html.twig`):
  - Added same tagged offices functionality as new event form
  - Maintains existing office selection for backward compatibility

- **Event Show Page** (`templates/event/show.html.twig`):
  - Added "Tagged Offices" section displaying all tagged offices as colored badges
  - Shows office names, codes, and colors
  - Includes helpful description text

- **Calendar View** (`templates/calendar/index.html.twig`):
  - Updated event tooltips to show tagged offices as colored badges
  - Updated event details modal to display tagged offices
  - Enhanced visual representation with office colors

## Usage

### Creating/Editing Events
1. **Primary Office**: Select the main office responsible for the event (determines default color)
2. **Tagged Offices**: Check all offices whose staff will participate in the meeting
3. **Live Preview**: See selected offices displayed as colored badges in the preview panel

### Viewing Events
1. **Event Show Page**: Visit `/events/{id}` to see full event details including tagged offices
2. **Calendar Tooltips**: Hover over events in calendar to see tagged offices in tooltip
3. **Calendar Modal**: Click on events to see detailed modal with tagged offices

### Form Data Structure
```html
<!-- Primary office (single selection) -->
<select name="office_id">
    <option value="1">Office A</option>
    <option value="2">Office B</option>
</select>

<!-- Tagged offices (multiple selection) -->
<input type="checkbox" name="tagged_office_ids[]" value="1"> Office A
<input type="checkbox" name="tagged_office_ids[]" value="2"> Office B
<input type="checkbox" name="tagged_office_ids[]" value="3"> Office C
```

### API Response Format
```json
{
  "extendedProps": {
    "office": {
      "id": 1,
      "name": "Primary Office",
      "code": "PO"
    },
    "taggedOffices": [
      {
        "id": 1,
        "name": "Office A",
        "code": "OA",
        "color": "#FF0000"
      },
      {
        "id": 2,
        "name": "Office B", 
        "code": "OB",
        "color": "#00FF00"
      }
    ]
  }
}
```

## Visual Display

### Event Show Page
- **Primary Office**: Displayed with label "Primary Office"
- **Tagged Offices**: Displayed as colored badges with office names and codes
- **Helper Text**: "Offices involved in this meeting"

### Calendar Tooltips
- **Primary Office**: Shows as "Primary: [Office Name]"
- **Tagged Offices**: Shows as colored badges with office names and colors
- **Responsive Design**: Adapts to screen size and position
- **Real-time Data**: Includes tagged offices data from the API

### Calendar Event Modal
- **Primary Office**: Labeled clearly as primary
- **Tagged Offices**: Displayed as colored badges in a flex layout
- **Interactive**: Clicking badges could potentially show office details (future enhancement)

## Migration
Run the migration to create the new database table:
```bash
php bin/console doctrine:migrations:migrate
```

## Backward Compatibility
- Existing events continue to work with their single office assignment
- Primary office selection remains unchanged
- Tagged offices is an additional feature that doesn't affect existing functionality
- All existing calendar views and event displays continue to work

## Benefits
1. **Better Meeting Coordination**: Clearly identify all offices involved in meetings
2. **Improved Visibility**: Staff can see which offices are participating in events at a glance
3. **Enhanced Reporting**: Ability to filter and report on multi-office events
4. **Flexible Organization**: Support for cross-departmental meetings and collaborations
5. **Visual Clarity**: Color-coded office badges make it easy to identify participants
6. **Comprehensive Display**: Tagged offices shown in all event views (show page, calendar tooltips, modals)

## Technical Notes
- The relationship uses CASCADE DELETE to maintain data integrity
- Tagged offices are stored separately from the primary office relationship
- Color determination follows priority: custom color → primary office color → first tagged office color → default blue
- JavaScript provides real-time preview updates when selecting tagged offices
- All event display locations (show page, calendar tooltips, modals) include tagged office information
- Responsive design ensures proper display across different screen sizes