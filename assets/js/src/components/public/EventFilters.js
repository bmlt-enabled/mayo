import { __, sprintf } from '@wordpress/i18n';

const FACET_DEFS = [
    {
        key: 'event_type',
        i18nLabel: () => __('Event Type', 'mayo-events-manager'),
        /* translators: dropdown placeholder for the event-type filter */
        i18nPlaceholder: () => __('Filter by event type…', 'mayo-events-manager'),
    },
    {
        key: 'service_body',
        i18nLabel: () => __('Service Body', 'mayo-events-manager'),
        /* translators: dropdown placeholder for the service-body filter */
        i18nPlaceholder: () => __('Filter by service body…', 'mayo-events-manager'),
    },
    {
        key: 'categories',
        i18nLabel: () => __('Category', 'mayo-events-manager'),
        /* translators: dropdown placeholder for the category filter */
        i18nPlaceholder: () => __('Filter by category…', 'mayo-events-manager'),
    },
    {
        key: 'tags',
        i18nLabel: () => __('Tag', 'mayo-events-manager'),
        /* translators: dropdown placeholder for the tag filter */
        i18nPlaceholder: () => __('Filter by tag…', 'mayo-events-manager'),
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

const EventFilters = ({ facets, selected, onAdd, onRemove, onClear, lockedFilters }) => {
    const visibleFacets = FACET_DEFS.filter(def => {
        if (lockedFilters?.has(def.key)) {
            return false;
        }
        return normalizeOptions(def.key, facets).length > 0;
    });

    if (visibleFacets.length === 0) {
        return null;
    }

    const hasAnySelection = Object.values(selected || {}).some(arr => Array.isArray(arr) && arr.length > 0);

    return (
        <div className="mayo-event-filters" role="region" aria-label={__('Event filters', 'mayo-events-manager')}>
            {visibleFacets.map(def => {
                const options = normalizeOptions(def.key, facets);
                const activeValues = Array.isArray(selected?.[def.key]) ? selected[def.key] : [];
                const availableOptions = options.filter(opt => !activeValues.includes(opt.value));
                const valueToLabel = new Map(options.map(opt => [opt.value, opt.label]));
                const selectId = `mayo-event-filter-${def.key}`;
                return (
                    <div key={def.key} className="mayo-event-filter-group">
                        <select
                            id={selectId}
                            className="mayo-event-filter-add"
                            aria-label={def.i18nLabel()}
                            value=""
                            onChange={(e) => {
                                if (e.target.value) {
                                    onAdd(def.key, e.target.value);
                                    e.target.value = '';
                                }
                            }}
                            disabled={availableOptions.length === 0}
                        >
                            <option value="">{def.i18nPlaceholder()}</option>
                            {availableOptions.map(option => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {activeValues.map(value => {
                            const label = valueToLabel.get(value) || value;
                            return (
                                <span key={value} className="mayo-event-filter-chip">
                                    <span className="mayo-event-filter-chip-label">{label}</span>
                                    <button
                                        type="button"
                                        className="mayo-event-filter-chip-remove"
                                        onClick={() => onRemove(def.key, value)}
                                        aria-label={sprintf(
                                            /* translators: %s: filter value being removed */
                                            __('Remove filter %s', 'mayo-events-manager'),
                                            label
                                        )}
                                    >
                                        ×
                                    </button>
                                </span>
                            );
                        })}
                    </div>
                );
            })}
            {hasAnySelection && (
                <button
                    type="button"
                    className="mayo-event-filter-clear"
                    onClick={onClear}
                >
                    {__('Clear filters', 'mayo-events-manager')}
                </button>
            )}
        </div>
    );
};

export default EventFilters;
