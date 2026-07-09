import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const pad2 = (n) => String(n).padStart(2, '0');

/**
 * Break a canonical "HH:mm" value into the pieces each control needs.
 * Returns empty pieces when the value is missing or malformed.
 */
const parseValue = (value, format) => {
    const match = /^(\d{1,2}):(\d{2})$/.exec(value || '');
    if (!match) {
        return { hour: '', minute: '', ampm: format === '12hour' ? 'AM' : '' };
    }
    const h24 = parseInt(match[1], 10);
    const minute = match[2];
    if (format === '12hour') {
        return {
            hour: String(h24 % 12 || 12),
            minute,
            ampm: h24 >= 12 ? 'PM' : 'AM',
        };
    }
    return { hour: pad2(h24), minute, ampm: '' };
};

/**
 * Recompose the control pieces into a canonical 24-hour "HH:mm" string.
 * Returns '' while the selection is incomplete so callers can treat the
 * field as empty (and required validation still fires).
 */
const composeValue = (hour, minute, ampm, format) => {
    if (hour === '' || minute === '') return '';
    let h24 = parseInt(hour, 10);
    if (format === '12hour') {
        if (!ampm) return '';
        h24 = h24 % 12;
        if (ampm === 'PM') h24 += 12;
    }
    return `${pad2(h24)}:${minute}`;
};

/**
 * Time entry control that presents 12-hour (Hour / Minute / AM-PM) or
 * 24-hour (Hour / Minute) selects based on `format`, while always emitting
 * a canonical 24-hour "HH:mm" value via a synthetic change event so it drops
 * into the existing name/value form handlers unchanged.
 */
const TimeField = ({ id, name, value, format, required, onChange }) => {
    const is12 = format === '12hour';
    const [pieces, setPieces] = useState(() => parseValue(value, format));

    // Remember the last value we emitted so our own updates don't get
    // clobbered by the sync effect below — only genuinely external changes
    // (e.g. the form reset after a successful submit) should reparse.
    const lastEmitted = useRef(value);

    useEffect(() => {
        if (value !== lastEmitted.current) {
            lastEmitted.current = value;
            setPieces(parseValue(value, format));
        }
    }, [value, format]);

    const emit = (next) => {
        setPieces(next);
        const composed = composeValue(next.hour, next.minute, next.ampm, format);
        lastEmitted.current = composed;
        onChange({ target: { name, value: composed } });
    };

    const hours = is12
        ? Array.from({ length: 12 }, (_, i) => String(i + 1))
        : Array.from({ length: 24 }, (_, i) => pad2(i));
    const minutes = Array.from({ length: 60 }, (_, i) => pad2(i));

    return (
        <div className="mayo-time-inputs" id={id}>
            <select
                className="mayo-time-hour"
                name={`${name}_hour`}
                aria-label={__('Hour', 'mayo-events-manager')}
                value={pieces.hour}
                required={required}
                onChange={(e) => emit({ ...pieces, hour: e.target.value })}
            >
                <option value="">{__('Hour', 'mayo-events-manager')}</option>
                {hours.map((h) => (
                    <option key={h} value={h}>{h}</option>
                ))}
            </select>
            <span className="mayo-time-sep">:</span>
            <select
                className="mayo-time-minute"
                name={`${name}_minute`}
                aria-label={__('Minute', 'mayo-events-manager')}
                value={pieces.minute}
                required={required}
                onChange={(e) => emit({ ...pieces, minute: e.target.value })}
            >
                <option value="">{__('Min', 'mayo-events-manager')}</option>
                {minutes.map((m) => (
                    <option key={m} value={m}>{m}</option>
                ))}
            </select>
            {is12 && (
                <select
                    className="mayo-time-ampm"
                    name={`${name}_ampm`}
                    aria-label={__('AM/PM', 'mayo-events-manager')}
                    value={pieces.ampm}
                    required={required}
                    onChange={(e) => emit({ ...pieces, ampm: e.target.value })}
                >
                    <option value="AM">{__('AM', 'mayo-events-manager')}</option>
                    <option value="PM">{__('PM', 'mayo-events-manager')}</option>
                </select>
            )}
        </div>
    );
};

export default TimeField;
