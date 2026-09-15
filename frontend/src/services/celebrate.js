const COLOURS = ['#ffd166', '#06d6a0', '#ef476f', '#118ab2', '#f78c6b', '#c77dff'];

const PIECES = 36;

const chime = () => {
    const Audio = window.AudioContext || window.webkitAudioContext;

    if (!Audio) {
        return;
    }

    const context = new Audio();
    const at = context.currentTime;

    [523.25, 659.25, 783.99].forEach((hertz, step) => {
        const tone = context.createOscillator();
        const level = context.createGain();

        tone.type = 'triangle';
        tone.frequency.value = hertz;
        level.gain.setValueAtTime(0.0001, at + step * 0.08);
        level.gain.exponentialRampToValueAtTime(0.18, at + step * 0.08 + 0.02);
        level.gain.exponentialRampToValueAtTime(0.0001, at + step * 0.08 + 0.35);

        tone.connect(level).connect(context.destination);
        tone.start(at + step * 0.08);
        tone.stop(at + step * 0.08 + 0.4);
    });

    setTimeout(() => context.close().catch(() => undefined), 900);
};

const confetti = () => {
    const stage = document.createElement('div');
    stage.className = 'celebration';
    stage.setAttribute('aria-hidden', 'true');

    for (let piece = 0; piece < PIECES; piece++) {
        const bit = document.createElement('i');
        bit.style.setProperty('--x', `${Math.random() * 100}%`);
        bit.style.setProperty('--tilt', `${Math.random() * 360}deg`);
        bit.style.setProperty('--drift', `${(Math.random() - 0.5) * 240}px`);
        bit.style.setProperty('--delay', `${Math.random() * 0.35}s`);
        bit.style.setProperty('--colour', COLOURS[piece % COLOURS.length]);
        stage.appendChild(bit);
    }

    document.body.appendChild(stage);
    setTimeout(() => stage.remove(), 2600);
};

const quietPlease = () => window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true;

/**
 * A small burst for something worth being pleased about.
 */
export const celebrate = ({ withSound = true } = {}) => {
    if (!quietPlease()) {
        confetti();
    }

    if (withSound) {
        try {
            chime();
        } catch {
            // the browser would not let us make a noise
        }
    }
};

export default celebrate;
