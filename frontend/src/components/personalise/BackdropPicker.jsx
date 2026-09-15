import React from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../md3/Button';
import personalisationService from '../../services/personalisationService';
import { shrinkForUpload } from '../../services/picture';

const PATTERNS = ['plain', 'dots', 'waves', 'grid', 'stars', 'bubbles', 'confetti'];

function BackdropPicker({ backdrop, onPick }) {
    const { t } = useTranslation();
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState(null);
    const file = React.useRef(null);

    const upload = async (picked) => {
        if (!picked) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const data = await shrinkForUpload(picked, 'backdrop');
            const kept = await personalisationService.keepPicture('backdrop', data);
            await onPick({ pictureId: kept.id, dimming: backdrop?.dimming ?? 40 });
        } catch (failure) {
            setError(failure?.response?.data?.error || t('personalise.pictureFailed'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="backdrop-picker" data-testid="backdrop-picker">
            <div className="backdrop-picker__patterns" role="group" aria-label={t('personalise.backdrop')}>
                {PATTERNS.map((pattern) => (
                    <button
                        key={pattern}
                        type="button"
                        className={`backdrop-picker__pattern backdrop-picker__pattern--${pattern}${
                            !backdrop?.pictureId && backdrop?.pattern === pattern ? ' is-chosen' : ''
                        }`}
                        aria-pressed={!backdrop?.pictureId && backdrop?.pattern === pattern}
                        onClick={() => onPick({ pattern })}
                    >
                        <span>{t(`personalise.backdrops.${pattern}`)}</span>
                    </button>
                ))}
            </div>

            <div className="backdrop-picker__own">
                <Button variant="tonal" icon="add" loading={busy} onClick={() => file.current?.click()}>
                    {t('personalise.ownBackdrop')}
                </Button>
                <input
                    ref={file}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    hidden
                    onChange={(event) => upload(event.target.files?.[0])}
                />
            </div>

            {backdrop?.pictureId && (
                <label className="backdrop-picker__dimming">
                    <span>{t('personalise.dimming', { percent: backdrop.dimming ?? 40 })}</span>
                    <input
                        type="range"
                        min="0"
                        max="90"
                        step="5"
                        value={backdrop.dimming ?? 40}
                        onChange={(event) => onPick({
                            pictureId: backdrop.pictureId,
                            dimming: Number(event.target.value),
                        })}
                    />
                </label>
            )}

            {error && <p className="form-error" role="alert">{error}</p>}
        </div>
    );
}

export default BackdropPicker;
