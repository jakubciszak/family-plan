export const formatMoney = (minorUnits, currency = 'PLN', language = 'pl') =>
    new Intl.NumberFormat(language, { style: 'currency', currency }).format((minorUnits || 0) / 100);

export const toMinorUnits = (text) => {
    const normalised = String(text ?? '').replace(/\s/g, '').replace(',', '.');

    if (normalised === '' || !/^\d+(\.\d{1,2})?$/.test(normalised)) {
        return null;
    }

    return Math.round(parseFloat(normalised) * 100);
};

export const fromMinorUnits = (minorUnits) => ((minorUnits || 0) / 100).toFixed(2).replace('.', ',');
