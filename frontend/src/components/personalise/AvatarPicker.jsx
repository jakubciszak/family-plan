import React from 'react';
import { useTranslation } from 'react-i18next';
import Avatar, { STYLE_NAMES, avatarDataUri } from '../Avatar';
import Button from '../md3/Button';
import personalisationService from '../../services/personalisationService';
import { shrinkForUpload } from '../../services/picture';

const SEEDS = [
    'rakieta', 'jez', 'smok', 'kotek', 'burza', 'malina', 'kometa', 'wafel',
    'tygrys', 'lisek', 'balon', 'gwiazda', 'pizza', 'sowa', 'delfin', 'kaktus',
];

function AvatarPicker({ avatar, name, onPick }) {
    const { t } = useTranslation();
    const [style, setStyle] = React.useState(avatar?.style || 'bottts');
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
            const data = await shrinkForUpload(picked, 'avatar');
            const kept = await personalisationService.keepPicture('avatar', data);
            await onPick({ pictureId: kept.id });
        } catch (failure) {
            setError(failure?.response?.data?.error || t('personalise.pictureFailed'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="avatar-picker" data-testid="avatar-picker">
            <div className="avatar-picker__now">
                <Avatar face={{ avatar }} name={name} size={72} />
                <Button
                    variant="tonal"
                    icon="add"
                    loading={busy}
                    onClick={() => file.current?.click()}
                >
                    {t('personalise.ownPicture')}
                </Button>
                <input
                    ref={file}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    hidden
                    onChange={(event) => upload(event.target.files?.[0])}
                />
            </div>

            {error && <p className="form-error" role="alert">{error}</p>}

            <div className="avatar-picker__styles" role="group" aria-label={t('personalise.avatarStyle')}>
                {STYLE_NAMES.map((name) => (
                    <button
                        key={name}
                        type="button"
                        className={`avatar-picker__style${style === name ? ' is-chosen' : ''}`}
                        aria-pressed={style === name}
                        title={name}
                        onClick={() => setStyle(name)}
                    >
                        <img src={avatarDataUri(name, avatar?.seed || SEEDS[0])} alt={name} />
                    </button>
                ))}
            </div>

            <div className="avatar-picker__seeds">
                {SEEDS.map((seed) => (
                    <button
                        key={seed}
                        type="button"
                        className={`avatar-picker__seed${avatar?.seed === seed && avatar?.style === style ? ' is-chosen' : ''}`}
                        title={seed}
                        onClick={() => onPick({ style, seed })}
                    >
                        <img src={avatarDataUri(style, seed)} alt={seed} />
                    </button>
                ))}
            </div>

            <Button
                variant="text"
                icon="restore"
                onClick={() => onPick({ style, seed: `${Math.random().toString(36).slice(2, 10)}` })}
            >
                {t('personalise.surpriseMe')}
            </Button>
        </div>
    );
}

export default AvatarPicker;
