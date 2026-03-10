import { Controller } from "@hotwired/stimulus"
import { Calendar } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'

export default class extends Controller {
    static targets = ["calendar", "loading", "tooltip"]
    static values = { 
        eventsUrl: String,
        officesUrl: String,
        searchUrl: String,
        filterUrl: String
    }

    connect() {
        this.initializeCalendar()
        this.initializeSearchAndFilters()
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy()
        }
    }

    initializeCalendar() {
        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            views: {
                dayGridMonth: {
                    titleFormat: { year: 'numeric', month: 'long' }
                },
                timeGridWeek: {
                    titleFormat: { year: 'numeric', month: 'short', day: 'numeric' }
                },
                timeGridDay: {
                    titleFormat: { year: 'numeric', month: 'long', day: 'numeric' }
                },
                listWeek: {
                    titleFormat: { year: 'numeric', month: 'long', day: 'numeric' }
                }
            },
            height: 'auto',
            aspectRatio: 1.8,
            events: {
                url: this.eventsUrlValue,
                failure: () => {
                    alert('There was an error while fetching events!')
                    this.hideLoading()
                }
            },
            loading: (bool) => {
                if (bool) {
                    this.showLoading()
                } else {
                    this.hideLoading()
                }
            },
            eventMouseEnter: (info) => {
                this.showTooltip(info, info.jsEvent)
            },
            eventMouseLeave: () => {
                this.hideTooltip()
            },
            eventClick: (info) => {
                info.jsEvent.preventDefault()
                this.showEventDetails(info.event)
            },
            dayMaxEvents: 3,
            moreLinkClick: 'popover',
            eventDisplay: 'block',
            displayEventTime: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            },
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            },
            nowIndicator: true,
            weekNumbers: false,
            navLinks: true,
            selectable: false,
            selectMirror: true,
            dayHeaderFormat: { weekday: 'short' }
        })

        this.calendar.render()
        this.bindEvents()
    }

    bindEvents() {
        // Hide tooltip when clicking elsewhere
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.fc-event')) {
                this.hideTooltip()
            }
        })

        // Hide tooltip when scrolling
        window.addEventListener('scroll', () => this.hideTooltip())
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.style.display = 'flex'
        }
    }

    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.style.display = 'none'
        }
    }

    showTooltip(info, jsEvent) {
        if (!this.hasTooltipTarget) return

        const event = info.event
        const props = event.extendedProps

        // Update tooltip content
        const titleEl = this.tooltipTarget.querySelector('#tooltip-title')
        const timeEl = this.tooltipTarget.querySelector('#tooltip-time')
        const locationEl = this.tooltipTarget.querySelector('#tooltip-location')
        const descriptionEl = this.tooltipTarget.querySelector('#tooltip-description')
        const officeEl = this.tooltipTarget.querySelector('#tooltip-office')

        if (titleEl) titleEl.textContent = event.title

        if (timeEl) {
            let timeText = ''
            if (props.type === 'holiday') {
                timeText = 'Holiday - ' + (props.typeDisplayName || props.holidayType)
            } else if (event.allDay) {
                timeText = 'All Day'
            } else {
                timeText = this.formatTime(event.start) + ' - ' + this.formatTime(event.end)
            }
            timeEl.textContent = timeText
        }

        if (locationEl) {
            locationEl.textContent = props.location || ''
            locationEl.style.display = props.location ? 'block' : 'none'
        }

        if (descriptionEl) {
            descriptionEl.textContent = props.description || ''
            descriptionEl.style.display = props.description ? 'block' : 'none'
        }

        if (officeEl) {
            let officeText = ''
            if (props.type === 'holiday') {
                officeText = props.country || 'Philippines'
                if (props.region) {
                    officeText += ' - ' + props.region
                }
            } else if (props.office) {
                officeText = 'Office: ' + props.office.name
            }
            officeEl.textContent = officeText
            officeEl.style.display = officeText ? 'block' : 'none'
        }

        // Position and show tooltip
        this.tooltipTarget.style.display = 'block'
        this.tooltipTarget.style.left = (jsEvent.pageX + 10) + 'px'
        this.tooltipTarget.style.top = (jsEvent.pageY + 10) + 'px'
    }

    hideTooltip() {
        if (this.hasTooltipTarget) {
            this.tooltipTarget.style.display = 'none'
        }
    }

    showEventDetails(event) {
        const props = event.extendedProps
        
        let details = ''
        
        if (props.type === 'holiday') {
            details = 'Holiday: ' + event.title + '\n'
            details += 'Type: ' + (props.typeDisplayName || props.holidayType) + '\n'
            details += 'Date: ' + this.formatDate(event.start) + '\n'
            if (props.description) {
                details += 'Description: ' + props.description + '\n'
            }
            if (props.country) {
                details += 'Country: ' + props.country + '\n'
            }
            if (props.region) {
                details += 'Region: ' + props.region + '\n'
            }
            if (props.isRecurring) {
                details += 'Recurring: Yes\n'
            }
        } else {
            details = 'Event: ' + event.title + '\n'
            if (!event.allDay) {
                details += 'Time: ' + this.formatTime(event.start) + ' - ' + this.formatTime(event.end) + '\n'
            } else {
                details += 'All Day Event\n'
            }
            if (props.location) {
                details += 'Location: ' + props.location + '\n'
            }
            if (props.description) {
                details += 'Description: ' + props.description + '\n'
            }
            if (props.office) {
                details += 'Office: ' + props.office.name + '\n'
            }
            if (props.creator) {
                details += 'Created by: ' + props.creator + '\n'
            }
            if (props.isRecurring) {
                details += 'Recurring: ' + (props.recurrenceDescription || 'Yes') + '\n'
            }
        }
        
        alert(details)
    }

    formatTime(date) {
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    formatDate(date) {
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        })
    }

    initializeSearchAndFilters() {
        // Search input
        const searchInput = document.getElementById('searchInput')
        if (searchInput) {
            let searchTimeout
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout)
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value)
                }, 300) // Debounce search
            })
        }

        // Filter toggle
        const filterToggle = document.getElementById('filterToggle')
        const filtersPanel = document.getElementById('filtersPanel')
        if (filterToggle && filtersPanel) {
            filterToggle.addEventListener('click', () => {
                const isVisible = filtersPanel.style.display !== 'none'
                filtersPanel.style.display = isVisible ? 'none' : 'block'
            })
        }

        // Apply filters button
        const applyFiltersBtn = document.getElementById('applyFilters')
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.applyFilters()
            })
        }

        // Clear filters button
        const clearFiltersBtn = document.getElementById('clearFilters')
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                this.clearAllFilters()
            })
        }

        // Real-time filter changes for some fields
        const quickFilters = ['priorityFilter', 'statusFilter', 'eventTypeFilter']
        quickFilters.forEach(filterId => {
            const filterEl = document.getElementById(filterId)
            if (filterEl) {
                filterEl.addEventListener('change', () => {
                    this.applyFilters()
                })
            }
        })
    }

    performSearch(query) {
        if (!query.trim()) {
            // If search is empty, reload normal events
            this.calendar.refetchEvents()
            return
        }

        this.showLoading()
        
        const searchParams = new URLSearchParams({
            q: query.trim()
        })

        fetch(`${this.searchUrlValue}?${searchParams}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Replace calendar events with search results
                    this.calendar.removeAllEvents()
                    this.calendar.addEventSource(data.events)
                } else {
                    console.error('Search failed:', data.message)
                }
            })
            .catch(error => {
                console.error('Search error:', error)
            })
            .finally(() => {
                this.hideLoading()
            })
    }

    applyFilters() {
        const filters = this.collectFilterCriteria()
        
        if (Object.keys(filters).length === 0) {
            // No filters applied, reload normal events
            this.calendar.refetchEvents()
            return
        }

        this.showLoading()

        fetch(this.filterUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(filters)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Replace calendar events with filtered results
                    this.calendar.removeAllEvents()
                    this.calendar.addEventSource(data.events)
                } else {
                    console.error('Filter failed:', data.message)
                }
            })
            .catch(error => {
                console.error('Filter error:', error)
            })
            .finally(() => {
                this.hideLoading()
            })
    }

    collectFilterCriteria() {
        const criteria = {}

        // Search query
        const searchInput = document.getElementById('searchInput')
        if (searchInput && searchInput.value.trim()) {
            criteria.query = searchInput.value.trim()
        }

        // Office filter
        const officeFilter = document.getElementById('officeFilter')
        if (officeFilter) {
            const selectedOffices = Array.from(officeFilter.selectedOptions).map(option => option.value)
            if (selectedOffices.length > 0) {
                criteria.office_ids = selectedOffices
            }
        }

        // Priority filter
        const priorityFilter = document.getElementById('priorityFilter')
        if (priorityFilter && priorityFilter.value) {
            criteria.priority = priorityFilter.value
        }

        // Status filter
        const statusFilter = document.getElementById('statusFilter')
        if (statusFilter && statusFilter.value) {
            criteria.status = statusFilter.value
        }

        // Event type filter
        const eventTypeFilter = document.getElementById('eventTypeFilter')
        if (eventTypeFilter && eventTypeFilter.value) {
            switch (eventTypeFilter.value) {
                case 'recurring':
                    criteria.is_recurring = true
                    break
                case 'single':
                    criteria.is_recurring = false
                    break
                case 'allday':
                    criteria.is_all_day = true
                    break
            }
        }

        // Date range filters
        const startDateFilter = document.getElementById('startDateFilter')
        if (startDateFilter && startDateFilter.value) {
            criteria.start_date = startDateFilter.value
        }

        const endDateFilter = document.getElementById('endDateFilter')
        if (endDateFilter && endDateFilter.value) {
            criteria.end_date = endDateFilter.value
        }

        return criteria
    }

    clearAllFilters() {
        // Clear search input
        const searchInput = document.getElementById('searchInput')
        if (searchInput) {
            searchInput.value = ''
        }

        // Clear all filter selects and inputs
        const filterElements = [
            'officeFilter',
            'priorityFilter', 
            'statusFilter',
            'eventTypeFilter',
            'startDateFilter',
            'endDateFilter'
        ]

        filterElements.forEach(filterId => {
            const element = document.getElementById(filterId)
            if (element) {
                if (element.type === 'select-multiple') {
                    // Clear multiple select
                    Array.from(element.options).forEach(option => {
                        option.selected = false
                    })
                } else {
                    element.value = ''
                }
            }
        })

        // Hide filters panel
        const filtersPanel = document.getElementById('filtersPanel')
        if (filtersPanel) {
            filtersPanel.style.display = 'none'
        }

        // Reload normal events
        this.calendar.refetchEvents()
    }
}