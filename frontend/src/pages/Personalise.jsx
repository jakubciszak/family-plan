import React from 'react';
import { useTranslation } from 'react-i18next';
import Section from '../components/Section';
import ArrangeList from '../components/personalise/ArrangeList';
import AvatarPicker from '../components/personalise/AvatarPicker';
import BackdropPicker from '../components/personalise/BackdropPicker';
import ColourPicker from '../components/personalise/ColourPicker';
import Button from '../components/md3/Button';
import Icon from '../components/md3/Icon';
import Switch from '../components/md3/Switch';
import TextField from '../components/md3/TextField';
import { CircularProgress } from '../components/md3/Progress';
import usePersonalisation from '../hooks/usePersonalisation';
import celebrate from '../services/celebrate';
import '../styles/personalise.css';

function Personalise({ user }) {
    const { t } = useTranslation();
    const { own, save } = usePersonalisation();
    const [nickname, setNickname] = React.useState('');
    const [error, setError] = React.useState(null);

    React.useEffect(() => {
        setNickname(own?.nickname || '');
    }, [own?.nickname]);

    if (!own) {
        return (
            <div className="personalise-page">
                <CircularProgress label={t('common.loading')} />
            </div>
        );
    }

    const change = (changes) => save(changes).catch((failure) => {
        setError(failure?.response?.data?.error || t('errors.generic'));
    });

    const placeName = (group) => (place) => t(`personalise.places.${group}.${place}`, place);

    return (
        <div className="personalise-page">
            <header className="personalise-page__head">
                <h2>
                    <Icon name="stars" size={24} />
                    {t('personalise.title')}
                </h2>
                <p className="empty-hint">{t('personalise.lead')}</p>
            </header>

            {error && <p className="form-error" role="alert">{error}</p>}

            <Section id="me" icon="account" title={t('personalise.meSection')}>
                <TextField
                    id="own-nickname"
                    label={t('personalise.nickname')}
                    value={nickname}
                    maxLength={40}
                    supportingText={t('personalise.nicknameHint')}
                    onChange={(event) => setNickname(event.target.value)}
                    onBlur={() => nickname !== (own.nickname || '') && change({ nickname })}
                />

                <AvatarPicker
                    avatar={own.avatar}
                    name={own.nickname || user?.name}
                    onPick={(avatar) => change({ avatar })}
                />
            </Section>

            <Section id="colour" icon="lightMode" title={t('personalise.colourSection')}>
                <ColourPicker theme={own.theme} onPick={(theme) => change({ theme })} />
            </Section>

            <Section id="backdrop" icon="install" title={t('personalise.backdropSection')} defaultOpen={false}>
                <BackdropPicker backdrop={own.backdrop} onPick={(backdrop) => change({ backdrop })} />
            </Section>

            <Section id="home" icon="tasks" title={t('personalise.homeSection')} defaultOpen={false}>
                <ArrangeList
                    id="home"
                    known={own.places.home}
                    order={own.home}
                    labels={placeName('home')}
                    onChange={(home) => change({ home })}
                />
            </Section>

            <Section id="navigation" icon="menu" title={t('personalise.navSection')} defaultOpen={false}>
                <ArrangeList
                    id="navigation"
                    known={own.places.navigation}
                    order={own.navigation}
                    labels={placeName('navigation')}
                    onChange={(navigation) => change({ navigation })}
                />
            </Section>

            <Section id="celebration" icon="trophy" title={t('personalise.celebrationSection')} defaultOpen={false}>
                <div className="personalise-toggle">
                    <span id="celebrates-label">{t('personalise.celebrates')}</span>
                    <Switch
                        id="celebrates"
                        checked={own.celebrates}
                        labelledBy="celebrates-label"
                        onChange={(celebrates) => change({ celebrates })}
                    />
                </div>

                <div className="personalise-toggle">
                    <span id="sound-label">{t('personalise.makesSound')}</span>
                    <Switch
                        id="makes-sound"
                        checked={own.makesSound}
                        labelledBy="sound-label"
                        onChange={(makesSound) => change({ makesSound })}
                    />
                </div>

                <Button
                    variant="tonal"
                    icon="stars"
                    onClick={() => celebrate({ withSound: own.makesSound })}
                >
                    {t('personalise.tryIt')}
                </Button>
            </Section>
        </div>
    );
}

export default Personalise;
