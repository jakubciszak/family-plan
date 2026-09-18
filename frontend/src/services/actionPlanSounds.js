const patterns = {
    soft: [{ frequency: 660, at: 0, duration: 0.85, volume: 0.12 }],
    bell: [
        { frequency: 880, at: 0, duration: 1.2, volume: 0.09 },
        { frequency: 1760, at: 0, duration: 0.6, volume: 0.03 },
    ],
    double: [
        { frequency: 660, at: 0, duration: 0.25, volume: 0.1 },
        { frequency: 660, at: 0.35, duration: 0.25, volume: 0.1 },
    ],
    melody: [
        { frequency: 523.25, at: 0, duration: 0.35, volume: 0.09 },
        { frequency: 659.25, at: 0.25, duration: 0.35, volume: 0.09 },
        { frequency: 783.99, at: 0.5, duration: 0.55, volume: 0.09 },
    ],
};

export const reminderSounds = Object.keys(patterns);
export const reminderSoundOf = (sound) => reminderSounds.includes(sound) ? sound : 'soft';

export const playReminderSound = (context, sound) => {
    if (!context || context.state !== 'running') throw new Error('Audio is unavailable');
    const nodes = [];
    const stop = () => nodes.forEach(({ oscillator, gain }) => {
        oscillator.stop();
        oscillator.disconnect();
        gain.disconnect();
    });
    try {
        const start = context.currentTime;
        patterns[reminderSoundOf(sound)].forEach((note) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = note.frequency;
            gain.gain.setValueAtTime(0, start + note.at);
            gain.gain.linearRampToValueAtTime(note.volume, start + note.at + 0.04);
            gain.gain.exponentialRampToValueAtTime(0.001, start + note.at + note.duration);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(start + note.at);
            oscillator.stop(start + note.at + note.duration + 0.02);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
            nodes.push({ oscillator, gain });
        });
    } catch (error) {
        stop();
        throw error;
    }
    return stop;
};
