import { __ } from '@wordpress/i18n';

const FACET_DEFS = [
    { key: 'event_type', i18nLabel: () => __('Event Type', 'mayo-events-manager') },
    { key: 'service_body', i18nLabel: () => __('Service Body', 'mayo-events-manager') },
    { key: 'categories', i18nLabel: () => __('Category', 'mayo-events-manager') },
    { key: 'tags', i18nLabel: () => __('Tag', 'mayo-events-manager') },
];

const normalizeOptions = (key, facets) => {
    if (key === 'event_type') {
        return (facets?.event_types || []).map(type => ({ value: type, label: type }));
    }
    if (key === 'service_body') {
        return (facets?.service_bodies || []).map(body => ({
            value: String(body.id),
            label: body.name,
            sourceId: body.source_id,
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

const EventFilters = ({ facets, selected, onToggle, onClear, lockedFilters }) => {
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
                return (
                    <div key={def.key} className="mayo-event-filter-group">
                        <div className="mayo-event-filter-label">{def.i18nLabel()}</div>
                        <div className="mayo-event-filter-pills">
                            {options.map(option => {
                                const isActive = activeValues.includes(option.value);
                                return (
                                    <button
                                        key={`${option.value}-${option.sourceId || ''}`}
                                        type="button"
                                        className="mayo-event-filter-pill"
                                        aria-pressed={isActive}
                                        onClick={() => onToggle(def.key, option.value)}
                                    >
                                        {option.label}
                                    </button>
                                );
                            })}
                        </div>
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
