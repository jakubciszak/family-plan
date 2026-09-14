import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import Icon from './md3/Icon';

const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

function Leaderboard({ teamId, currentUserId, refreshToken }) {
    const { t } = useTranslation();
    const [board, setBoard] = React.useState(null);

    React.useEffect(() => {
        if (!teamId) {
            setBoard(null);
            return undefined;
        }

        let abandoned = false;

        taskService.getLeaderboard(teamId)
            .then((data) => {
                if (!abandoned) {
                    setBoard(data);
                }
            })
            .catch(() => setBoard(null));

        return () => {
            abandoned = true;
        };
    }, [teamId, refreshToken]);

    if (!board?.standings?.length) {
        return null;
    }

    const scored = board.standings.some((row) => row.total > 0);

    return (
        <section className="leaderboard" data-testid="leaderboard">
            <h3>
                <Icon name="stars" size={20} />
                {t('leaderboard.title')}
            </h3>

            {scored ? (
                <div className="leaderboard-scroll">
                    <table className="leaderboard-table">
                        <thead>
                            <tr>
                                <th scope="col" className="leaderboard-rank" aria-label="#" />
                                <th scope="col" className="leaderboard-name">{t('leaderboard.member')}</th>
                                <th scope="col" className="leaderboard-total">{t('leaderboard.total')}</th>
                                {board.days.map((day, index) => (
                                    <th
                                        key={day}
                                        scope="col"
                                        className={day === board.today ? 'leaderboard-day is-today' : 'leaderboard-day'}
                                    >
                                        {t(`week.days.${DAY_KEYS[index]}`)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {board.standings.map((row, index) => (
                                <tr
                                    key={row.userId}
                                    className={row.userId === currentUserId ? 'is-me' : undefined}
                                >
                                    <td className="leaderboard-rank">{index + 1}</td>
                                    <th scope="row" className="leaderboard-name">
                                        {row.name}
                                        {row.userId === currentUserId && (
                                            <span className="leaderboard-you">{t('leaderboard.you')}</span>
                                        )}
                                    </th>
                                    <td className="leaderboard-total">{row.total}</td>
                                    {board.days.map((day) => (
                                        <td
                                            key={day}
                                            className={day === board.today ? 'leaderboard-day is-today' : 'leaderboard-day'}
                                        >
                                            {row.perDay[day] || ''}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <p className="empty-hint">{t('leaderboard.empty')}</p>
            )}
        </section>
    );
}

export default Leaderboard;
