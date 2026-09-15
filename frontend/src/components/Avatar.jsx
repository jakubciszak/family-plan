import React from 'react';
import { createAvatar } from '@dicebear/core';
import {
    adventurer,
    avataaars,
    bigSmile,
    bottts,
    croodles,
    funEmoji,
    lorelei,
    micah,
    miniavs,
    notionists,
    openPeeps,
    personas,
    pixelArt,
    thumbs,
} from '@dicebear/collection';

export const AVATAR_STYLES = {
    adventurer,
    avataaars,
    'big-smile': bigSmile,
    bottts,
    croodles,
    'fun-emoji': funEmoji,
    lorelei,
    micah,
    miniavs,
    notionists,
    'open-peeps': openPeeps,
    personas,
    'pixel-art': pixelArt,
    thumbs,
};

export const STYLE_NAMES = Object.keys(AVATAR_STYLES);

const drawn = new Map();

export const avatarDataUri = (style, seed) => {
    const key = `${style}|${seed}`;

    if (!drawn.has(key)) {
        const collection = AVATAR_STYLES[style] || bottts;
        drawn.set(key, createAvatar(collection, { seed: String(seed || 'kot'), radius: 50 }).toDataUri());
    }

    return drawn.get(key);
};

const initialsOf = (name) => String(name || '?').trim().charAt(0).toUpperCase() || '?';

function Avatar({ face, name, size = 40, className = '' }) {
    const classes = ['avatar-face', className].filter(Boolean).join(' ');
    const style = { width: size, height: size };

    if (face?.avatar?.pictureId) {
        return (
            <img
                className={classes}
                style={style}
                src={`/api/personalisation/pictures/${face.avatar.pictureId}`}
                alt={name || ''}
            />
        );
    }

    if (face?.avatar?.style) {
        return (
            <img
                className={classes}
                style={style}
                src={avatarDataUri(face.avatar.style, face.avatar.seed)}
                alt={name || ''}
            />
        );
    }

    return (
        <span className={`${classes} avatar-face--initials`} style={style} aria-hidden="true">
            {initialsOf(name)}
        </span>
    );
}

export default Avatar;
