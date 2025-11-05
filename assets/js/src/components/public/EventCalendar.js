import { useEffect, useRef, useState } from '@wordpress/element';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';

const EventCalendar = ({ settings = {} }) => {
    const calendarRef = useRef(null);
    const calendarInstance = useRef(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!calendarRef.current) return;

        // Fetch events from the API
        const fetchEvents = async (fetchInfo, successCallback, failureCallback) => {
            try {
                const params = new URLSearchParams({
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr,
                    ...(settings.categories && { categories: settings.categories }),
                    ...(settings.tags && { tags: settings.tags }),
                    ...(settings.eventType && { event_type: settings.eventType }),
                    ...(settings.status && { status: settings.status }),
                    ...(settings.serviceBody && { service_body: settings.serviceBody }),
                    ...(settings.sourceIds && { source_ids: settings.sourceIds }),
                });

                // Build full REST API URL
                const baseUrl = window.mayoApiSettings?.root || '/wp-json/';
                const url = `${baseUrl}event-manager/v1/events?${params.toString()}`;
                
                const fetchResponse = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.mayoApiSettings?.nonce || ''
                    }
                });
                
                if (!fetchResponse.ok) {
                    throw new Error(`HTTP error! status: ${fetchResponse.status}`);
                }
                
                const response = await fetchResponse.json();

                const events = response.events?.map(event => {
                    // Construct datetime from date and time fields
                    const startDate = event.meta?.event_start_date || '';
                    const startTime = event.meta?.event_start_time || '';
                    const endDate = event.meta?.event_end_date || startDate;
                    const endTime = event.meta?.event_end_time || '';
                    
                    // Combine date and time, or use date only if time is not available
                    const startDateTime = startTime ? `${startDate}T${startTime}` : startDate;
                    const endDateTime = endTime ? `${endDate}T${endTime}` : endDate;
                    
                    // Get event title
                    const title = event.title?.rendered || event.title || 'Untitled Event';
                    
                    return {
                        id: event.id,
                        title: title,
                        start: startDateTime,
                        end: endDateTime,
                        allDay: !startTime, // If no time specified, it's an all-day event
                        extendedProps: {
                            url: event.link, // Store URL in extendedProps instead
                            location: event.meta?.location_name,
                            eventType: event.meta?.event_type,
                            serviceBody: event.meta?.service_body,
                            description: event.content?.rendered,
                        },
                        backgroundColor: event.meta?.event_type === 'Service' ? '#2271b1' : '#50575e',
                        borderColor: event.meta?.event_type === 'Service' ? '#135e96' : '#3c434a',
                    };
                }) || [];

                successCallback(events);
            } catch (error) {
                console.error('Error fetching events:', error);
                failureCallback(error);
            } finally {
                setLoading(false);
            }
        };

        // Initialize FullCalendar
        calendarInstance.current = new Calendar(calendarRef.current, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin],
            initialView: settings.initialView || 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            events: fetchEvents,
            eventClick: (info) => {
                // Prevent default behavior
                if (info.jsEvent) {
                    info.jsEvent.preventDefault();
                }
                // Navigate to event URL from extendedProps
                const url = info.event.extendedProps?.url;
                if (url) {
                    window.location.href = url;
                }
            },
            eventContent: (arg) => {
                const timeText = arg.timeText;
                const title = arg.event.title;
                const location = arg.event.extendedProps.location;
                
                return {
                    html: `
                        <div class="fc-event-main-frame">
                            ${timeText ? `<div class="fc-event-time">${timeText}</div>` : ''}
                            <div class="fc-event-title-container">
                                <div class="fc-event-title fc-sticky">${title}</div>
                                ${location ? `<div class="fc-event-location">${location}</div>` : ''}
                            </div>
                        </div>
                    `
                };
            },
            height: settings.height || 'auto',
            timeZone: settings.timezone || 'local',
            loading: (isLoading) => {
                setLoading(isLoading);
            }
        });

        calendarInstance.current.render();

        // Cleanup
        return () => {
            if (calendarInstance.current) {
                calendarInstance.current.destroy();
            }
        };
    }, [settings]);

    return (
        <div className="mayo-event-calendar">
            {loading && (
                <div className="mayo-calendar-loading" style={{ textAlign: 'center', padding: '20px' }}>
                    Loading events...
                </div>
            )}
            <div ref={calendarRef} />
        </div>
    );
};

export default EventCalendar;
