const POSITION_CONVERSION_CM = {
    who2006: 0.7,
    cdc2000: 0.8,
};

export function adjustHeightMeasurement(value, ageMonths, position, reference) {
    if (value === null) return { value: null, adjustment: 0, error: null };
    if (!Number.isFinite(value) || value <= 0) {
        return { value: null, adjustment: 0, error: 'Enter a measured value greater than zero.' };
    }
    if (position === 'standard') return { value, adjustment: 0, error: null };
    if (position !== 'recumbent' && position !== 'standing') {
        return { value: null, adjustment: 0, error: 'Select how the height measurement was taken.' };
    }

    const standardPosition = ageMonths < 24 ? 'recumbent' : 'standing';
    if (position === standardPosition) return { value, adjustment: 0, error: null };

    if (reference === 'who2007') {
        return {
            value: null,
            adjustment: 0,
            error: 'WHO 2007 height-for-age requires standing height. No conversion is applied for this reference.',
        };
    }

    const conversion = POSITION_CONVERSION_CM[reference];
    if (!Number.isFinite(conversion)) {
        return { value: null, adjustment: 0, error: 'No measurement-position conversion is defined for this reference.' };
    }
    if (reference === 'who2006' && ageMonths > 60) {
        return { value: null, adjustment: 0, error: 'WHO 2006 position conversion is defined only through 60 months.' };
    }

    const adjustment = position === 'standing' ? conversion : -conversion;
    const adjustedValue = value + adjustment;
    if (adjustedValue <= 0) {
        return { value: null, adjustment: 0, error: 'The position adjustment would make the measured value invalid.' };
    }

    return { value: adjustedValue, adjustment, error: null };
}
