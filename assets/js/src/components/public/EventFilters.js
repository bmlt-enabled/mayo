import { useState, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const FACET_DEFS = [
    {
        key: 'event_type',
        /* translators: filter pill label and panel title for the event-type filter */
        i18nLabel: () => __('Event Type', 'mayo-events-manager'),
    },
    {
        key: 'service_body',
        /* translators: filter pill label and panel title for the service-body filter */
        i18nLabel: () => __('Service Body', 'mayo-events-manager'),
    },
    {
        key: 'categories',
        /* translators: filter pill label and panel title for the category filter */
        i18nLabel: () => __('Category', 'mayo-events-manager'),
    },
    {
        key: 'tags',
        /* translators: filter pill label and panel title for the tag filter */
        i18nLabel: () => __('Tag', 'mayo-events-manager'),
    },
];

const normalizeOptions = (key, facets) => {
    if (key === 'event_type') {
        return (facets?.event_types || []).map(type => ({ value: type, label: type }));
    }
    if (key === 'service_body') {
        return (facets?.service_bodies || []).map(body => ({
            value: String(body.id),
            label: body.name,
        }));
    }
    if (key === 'categories') {
        return (facets?.categories || []).map(term => ({ value: term.slug, label: term.name }));
    }
    if (key === 'tags') {
        return (facets?.tags || []).map(term => ({ value: term.slug, label: term.name }));
    }
    return [];
};

const EventFilters = ({ facets, selected, onToggle, onClear, lockedFilters, disabled = false }) => {
    const containerRef = useRef(null);
    const [openFacet, setOpenFacet] = useState(null);

    const visibleFacets = FACET_DEFS.filter(def => {
        if (lockedFilters?.has(def.key)) {
            return false;
        }
        return normalizeOptions(def.key, facets).length > 0;
    });

    // Close the open panel when clicking outside, pressing Escape, or when the
    // visible facets change underneath us.
    useEffect(() => {
        if (!openFacet) {
            return undefined;
        }
        const onMouseDown = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpenFacet(null);
            }
        };
        const onKey = (e) => {
            if (e.key === 'Escape') {
                setOpenFacet(null);
            }
        };
        document.addEventListener('mousedown', onMouseDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onMouseDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [openFacet]);

    if (visibleFacets.length === 0) {
        return null;
    }

    const hasAnySelection = Object.values(selected || {}).some(arr => Array.isArray(arr) && arr.length > 0);

    return (
        <div
            className={'mayo-event-filters' + (disabled ? ' is-disabled' : '')}
            role="region"
            aria-label={__('Event filters', 'mayo-events-manager')}
            aria-busy={disabled}
            ref={containerRef}
        >
            {visibleFacets.map(def => {
                const options = normalizeOptions(def.key, facets);
                const activeValues = Array.isArray(selected?.[def.key]) ? selected[def.key] : [];
                const isOpen = openFacet === def.key;
                const count = activeValues.length;
                const label = def.i18nLabel();
                const panelId = `mayo-event-filter-panel-${def.key}`;
                return (
                    <div key={def.key} className="mayo-event-filter-pill-wrap">
                        <button
                            type="button"
                            className={
                                'mayo-event-filter-pill'
                                + (isOpen ? ' is-open' : '')
                                + (count > 0 ? ' has-selection' : '')
                            }
                            aria-haspopup="listbox"
                            aria-expanded={isOpen}
                            aria-controls={panelId}
                            disabled={disabled}
                            onClick={() => {
                                if (disabled) {
                                    return;
                                }
                                setOpenFacet(isOpen ? null : def.key);
                            }}
                        >
                            <span className="mayo-event-filter-pill-label">{label}</span>
                            {count > 0 && (
                                <span className="mayo-event-filter-pill-count">{count}</span>
                            )}
                        </button>
                        {isOpen && (
                            <div
                                id={panelId}
                                className="mayo-event-filter-panel"
                                role="listbox"
                                aria-multiselectable="true"
                                aria-label={label}
                            >
                                <div className="mayo-event-filter-panel-header">
                                    <span className="mayo-event-filter-panel-title">{label}</span>
                                    <button
                                        type="button"
                                        className="mayo-event-filter-panel-close"
                                        onClick={() => setOpenFacet(null)}
                                        aria-label={sprintf(
                                            /* translators: %s: filter facet name being closed */
                                            __('Close %s filter', 'mayo-events-manager'),
                                            label
                                        )}
                                    >
                                        ×
                                    </button>
                                </div>
                                <ul className="mayo-event-filter-panel-options">
                                    {options.map(option => {
                                        const isActive = activeValues.includes(option.value);
                                        return (
                                            <li key={option.value}>
                                                <button
                                                    type="button"
                                                    className={
                                                        'mayo-event-filter-option'
                                                        + (isActive ? ' is-active' : '')
                                                    }
                                                    role="option"
                                                    aria-selected={isActive}
                                                    disabled={disabled}
                                                    onClick={() => {
                                                        if (!disabled) {
                                                            onToggle(def.key, option.value);
                                                        }
                                                    }}
                                                >
                                                    {option.label}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}
                    </div>
                );
            })}
            {hasAnySelection && (
                <button
                    type="button"
                    className="mayo-event-filter-clear"
                    disabled={disabled}
                    onClick={() => {
                        if (!disabled) {
                            onClear();
                        }
                    }}
                    title={__('Clear filters', 'mayo-events-manager')}
                    aria-label={__('Clear filters', 'mayo-events-manager')}
                >
                    <span aria-hidden="true">🧹</span>
                </button>
            )}
        </div>
    );
};

export default EventFilters;
