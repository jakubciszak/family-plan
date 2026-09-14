import React from 'react';

export function CircularProgress({ label }) {
    return (
        <div className="md-progress-circular loading" role="status" aria-live="polite">
            <span className="md-progress-circular__spinner" />
            {label && <span className="md-body-medium">{label}</span>}
        </div>
    );
}

export function LinearProgress({ value, max = 100, label }) {
    const percent = max > 0 ? Math.min(100, Math.max(0, (value / max) * 100)) : 0;

    return (
        <div
            className="md-progress-linear"
            role="progressbar"
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-label={label}
        >
            <div className="md-progress-linear__value" style={{ width: `${percent}%` }} />
        </div>
    );
}
