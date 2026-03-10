# Office Color Auto-Detection Implementation

## Overview

I have successfully implemented office color auto-detection functionality in the event edit page (`http://127.0.0.4:8000/events/57/edit`). This feature automatically updates the event color when an office is selected, providing a consistent visual representation across the calendar system.

## Implementation Details

### 1. Event Edit Template (`templates/event/edit.html.twig`)

#### Enhanced Office Selection
```twig
<div>
    <label for="office_id" class="block text-sm font-medium text-gray-700">Office</label>
    <select name="office_id" id="office_id"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
        <option value="">Select an office</option>
        {% for office in offices %}
            <option value="{{ office.id }}" 
                    data-color="{{ office.color ?? '#007BFF' }}"
                    data-name="{{ office.name }}"
                    {% if event.office and event.office.id == office.id %}selected{% endif %}>
                {{ office.name }}
            </option>
        {% endfor %}
    </select>
</div>
```

#### Enhanced Color Display Section
```twig
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Office Color</label>
    <div class="flex items-center space-x-3">
        <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded-md border border-gray-200 flex-1">
            <div id="colorDisplay" class="w-6 h-6 rounded-full border-2 border-white shadow-sm" 
                 style="background-color: {{ event.color ?? '#007BFF' }};"></div>
            <div class="flex flex-col min-w-0 flex-1">
                <span id="colorLabel" class="text-xs font-medium text-gray-700 truncate">
                    {% if event.office %}{{ event.office.name }}{% else %}Default Blue{% endif %}
                </span>
                <span id="colorCode" class="text-xs text-gray-500">{{ event.color ?? '#007BFF' }}</span>
            </div>
        </div>
        <input type="color" name="color" id="color"
               value="{{ event.color ?? '#007BFF' }}"
               class="w-12 h-10 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
               title="Custom color override">
    </div>
    <p class="mt-1 text-xs text-gray-500">Color is automatically set based on office selection. Use the color picker to override.</p>
</div>
```

### 2. JavaScript Functionality

#### Office Color Auto-Detection Function
```javascript
function updateOfficeColor() {
    const officeSelect = document.getElementById('office_id');
    const colorInput = document.getElementById('color');
    const colorDisplay = document.getElementById('colorDisplay');
    const colorLabel = document.getElementById('colorLabel');
    const colorCode = document.getElementById('colorCode');
    
    const selectedOption = officeSelect.options[officeSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        // Office selected - use office color
        const officeColor = selectedOption.getAttribute('data-color') || '#007BFF';
        const officeName = selectedOption.getAttribute('data-name') || 'Selected Office';
        
        colorInput.value = officeColor;
        colorDisplay.style.backgroundColor = officeColor;
        colorLabel.textContent = officeName;
        colorCode.textContent = officeColor;
    } else {
        // No office selected - use default
        const defaultColor = '#007BFF';
        colorInput.value = defaultColor;
        colorDisplay.style.backgroundColor = defaultColor;
        colorLabel.textContent = 'Default Blue';
        colorCode.textContent = defaultColor;
    }
}
```

#### Event Listeners
```javascript
// Handle office selection change
document.getElementById('office_id').addEventListener('change', updateOfficeColor);

// Handle manual color picker changes
document.getElementById('color').addEventListener('input', function() {
    const colorDisplay = document.getElementById('colorDisplay');
    const colorCode = document.getElementById('colorCode');
    const colorLabel = document.getElementById('colorLabel');
    
    colorDisplay.style.backgroundColor = this.value;
    colorCode.textContent = this.value;
    
    // Update label to indicate custom color
    const officeSelect = document.getElementById('office_id');
    const selectedOption = officeSelect.options[officeSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const officeName = selectedOption.getAttribute('data-name') || 'Selected Office';
        const officeColor = selectedOption.getAttribute('data-color') || '#007BFF';
        
        if (this.value.toLowerCase() !== officeColor.toLowerCase()) {
            colorLabel.textContent = `${officeName} (Custom)`;
        } else {
            colorLabel.textContent = officeName;
        }
    } else {
        colorLabel.textContent = 'Custom Color';
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateOfficeColor();
});
```

### 3. Event Creation Template (`templates/event/new.html.twig`)

The new event template already includes office color auto-detection functionality:

- Office selection dropdown includes `data-color` attributes
- JavaScript automatically updates color display when office is selected
- Live preview shows the selected office color
- Color picker allows manual override

### 4. Database Schema

The `Office` entity includes a `color` field:

```php
#[ORM\Column(type: 'string', length: 7, unique: true)]
#[Assert\NotBlank]
#[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex color code (e.g., #FF0000)')]
private ?string $color = null;
```

## Features

### 1. Automatic Color Detection
- When an office is selected, the event color automatically updates to match the office's assigned color
- Color display shows a visual preview with the office name and hex code
- Seamless integration with existing form validation

### 2. Manual Override Capability
- Users can still manually select a custom color using the color picker
- When a custom color is chosen, the label indicates "(Custom)" to show it differs from the office default
- If the user manually selects the same color as the office, it reverts to showing just the office name

### 3. Visual Feedback
- Real-time color preview with circular color indicator
- Office name display with color code
- Clear labeling to indicate whether using office color or custom color

### 4. Responsive Design
- Clean, modern interface that matches the existing design system
- Proper spacing and alignment with other form elements
- Accessible color contrast and clear visual hierarchy

## User Experience

### Workflow
1. **Office Selection**: User selects an office from the dropdown
2. **Automatic Update**: Color automatically changes to match the office color
3. **Visual Confirmation**: Color display updates with office name and color code
4. **Optional Override**: User can manually adjust color if needed
5. **Clear Indication**: System shows whether using office color or custom color

### Benefits
- **Consistency**: Events from the same office have consistent colors
- **Efficiency**: No need to manually remember or look up office colors
- **Flexibility**: Still allows custom colors when needed
- **Visual Clarity**: Easy to identify which office an event belongs to

## Technical Implementation

### Data Flow
1. Office entities store unique color values in the database
2. Template renders office options with `data-color` attributes
3. JavaScript listens for office selection changes
4. Color display updates automatically based on selected office
5. Form submission includes the final color value (office or custom)

### Error Handling
- Fallback to default blue color if office color is not available
- Graceful handling of missing data attributes
- Proper validation of color format in the backend

### Performance
- Minimal JavaScript overhead with efficient DOM manipulation
- No additional API calls required for color lookup
- Client-side processing for immediate visual feedback

## Testing

A comprehensive test file (`test_office_color_auto_detection.html`) demonstrates:
- Office selection with different colors
- Automatic color updates
- Manual color override functionality
- Visual preview of event appearance
- Console logging for debugging

## Compatibility

- Works with existing event creation and editing workflows
- Compatible with all modern browsers
- Maintains accessibility standards
- Integrates seamlessly with the existing design system

## Future Enhancements

Potential improvements could include:
- Color theme suggestions based on office hierarchy
- Bulk color updates for office reorganizations
- Color accessibility checks for contrast compliance
- Integration with calendar legend for office identification