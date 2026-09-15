import React from 'react';
import { useTranslation } from 'react-i18next';
import IconButton from '../md3/IconButton';
import Switch from '../md3/Switch';

/**
 * An ordered pick of places: what is shown, in what order, and what has been put away.
 */
function ArrangeList({ id, known, order, labels, onChange }) {
    const { t } = useTranslation();
    const hidden = known.filter((place) => !order.includes(place));

    const move = (from, to) => {
        if (to < 0 || to >= order.length) {
            return;
        }

        const next = [...order];
        [next[from], next[to]] = [next[to], next[from]];
        onChange(next);
    };

    return (
        <div className="arrange" data-testid={`arrange-${id}`}>
            <ol className="arrange__shown">
                {order.map((place, index) => (
                    <li key={place} data-place={place}>
                        <span className="arrange__name">{labels(place)}</span>
                        <IconButton
                            icon="expand"
                            label={t('personalise.moveUp')}
                            className="arrange__up"
                            disabled={index === 0}
                            onClick={() => move(index, index - 1)}
                        />
                        <IconButton
                            icon="expand"
                            label={t('personalise.moveDown')}
                            disabled={index === order.length - 1}
                            onClick={() => move(index, index + 1)}
                        />
                        <Switch
                            id={`arrange-${id}-${place}`}
                            checked
                            label={t('personalise.shown')}
                            onChange={() => onChange(order.filter((held) => held !== place))}
                        />
                    </li>
                ))}
                {order.length === 0 && <li className="empty-hint">{t('personalise.nothingShown')}</li>}
            </ol>

            {hidden.length > 0 && (
                <ul className="arrange__hidden">
                    {hidden.map((place) => (
                        <li key={place} data-place={place}>
                            <span className="arrange__name">{labels(place)}</span>
                            <Switch
                                id={`arrange-${id}-${place}`}
                                checked={false}
                                label={t('personalise.shown')}
                                onChange={() => onChange([...order, place])}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default ArrangeList;
