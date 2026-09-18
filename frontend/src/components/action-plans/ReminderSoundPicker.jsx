import React, { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, TextField } from '../md3';
import { playReminderSound, reminderSoundOf, reminderSounds } from '../../services/actionPlanSounds';

export default function ReminderSoundPicker({ value, onChange }) {
    const { t } = useTranslation();
    const id = useId();
    const audio = useRef(null);
    const stop = useRef(null);
    const mounted = useRef(true);
    const [unavailable, setUnavailable] = useState(false);

    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
            stop.current?.();
            audio.current?.close().catch(() => {});
        };
    }, []);

    const preview = async () => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) throw new Error('Audio is unavailable');
            if (!audio.current) audio.current = new AudioContext();
            await audio.current.resume();
            if (!mounted.current) return;
            stop.current?.();
            stop.current = playReminderSound(audio.current, value);
            setUnavailable(false);
        } catch {
            if (mounted.current) setUnavailable(true);
        }
    };

    return <div className="action-plans__sound-picker">
        <div className="action-plans__sound-options">
            <TextField as="select" id={id} label={t('actionPlans.soundChoice')} value={reminderSoundOf(value)}
                onChange={(event) => { stop.current?.(); onChange(event.target.value); }}>
                {reminderSounds.map((sound) => <option key={sound} value={sound}>{t(`actionPlans.sounds.${sound}`)}</option>)}
            </TextField>
            <Button type="button" variant="tonal" onClick={preview}>{t('actionPlans.previewSound')}</Button>
        </div>
        {unavailable && <p role="status" className="action-plans__hint">{t('actionPlans.soundUnavailable')}</p>}
    </div>;
}
