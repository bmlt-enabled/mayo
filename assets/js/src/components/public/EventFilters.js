import { __ } from '@wordpress/i18n';

const FACET_DEFS = [
    { key: 'event_type', valueKey: 'value', labelKey: 'label', i18nLabel: () => __('Event Type', 'mayo-events-manager') },
    { key: 'service_body', valueKey: 'value', labelKey: 'label', i18nLabel: () => __('Service Body', 'mayo-events-manager') },
    { key: 'categories', valueKey: 'value', labelKey: 'label', i18nLabel: () => __('Category', 'mayo-events-manager') },
    { key: 'tags', valueKey: 'value', labelKey: 'label', i18nLabel: () => __('Tag', 'mayo-events-manager') },
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

const EventFilters = ({ facets, selected, onChange, lockedFilters }) => {
    const visibleFacets = FACET_DEFS.filter(def => {
        if (lockedFilters?.has(def.key)) {
            return false;
        }
        const options = normalizeOptions(def.key, facets);
        return options.length > 0;
    });

    if (visibleFacets.length === 0) {
        return null;
    }

    return (
        <div className="mayo-event-filters" role="region" aria-label={__('Event filters', 'mayo-events-manager')}>
            {visibleFacets.map(def => {
                const options = normalizeOptions(def.key, facets);
                const currentValue = selected?.[def.key] || '';
                const selectId = `mayo-event-filter-${def.key}`;
                return (
                    <div key={def.key} className="mayo-event-filter">
                        <label htmlFor={selectId} className="mayo-event-filter-label">
                            {def.i18nLabel()}
                        </label>
                        <select
                            id={selectId}
                            className="mayo-event-filter-select"
                            value={currentValue}
                            onChange={(e) => onChange(def.key, e.target.value)}
                        >
                            <option value="">{__('All', 'mayo-events-manager')}</option>
                            {options.map(option => (
                                <option key={`${option.value}-${option.sourceId || ''}`} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                );
            })}
            {Object.values(selected || {}).some(v => v) && (
                <button
                    type="button"
                    className="mayo-event-filter-clear"
                    onClick={() => onChange('__clear__', '')}
                >
                    {__('Clear filters', 'mayo-events-manager')}
                </button>
            )}
        </div>
    );
};

export default EventFilters;
