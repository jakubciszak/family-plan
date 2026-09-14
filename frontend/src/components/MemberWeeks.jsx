import React from 'react';
import { useTranslation } from 'react-i18next';
import WeekCalendar from './WeekCalendar';
import teamService from '../services/teamService';

const storageKey = (teamId) => `hiddenWeekMembers:${teamId}`;

const readHidden = (teamId) => {
    try {
        return new Set(JSON.parse(localStorage.getItem(storageKey(teamId))) || []);
    } catch {
        return new Set();
    }
};

const writeHidden = (teamId, hidden) => {
    try {
        localStorage.setItem(storageKey(teamId), JSON.stringify([...hidden]));
    } catch {
        // storage unavailable
    }
};

function MemberWeeks({ teamId, refreshToken }) {
    const { t } = useTranslation();
    const [members, setMembers] = React.useState([]);
    const [hidden, setHidden] = React.useState(() => new Set());

    React.useEffect(() => {
        if (!teamId) {
            setMembers([]);
            return undefined;
        }

        let abandoned = false;
        setHidden(readHidden(teamId));

        teamService.getTeamMembers(teamId)
            .then((data) => {
                if (!abandoned) {
                    setMembers((data.members || []).filter((member) => member.role !== 'admin'));
                }
            })
            .catch(() => setMembers([]));

        return () => {
            abandoned = true;
        };
    }, [teamId]);

    if (members.length === 0) {
        return null;
    }

    const toggle = (userId) => {
        setHidden((current) => {
            const next = new Set(current);
            next.has(userId) ? next.delete(userId) : next.add(userId);
            writeHidden(teamId, next);

            return next;
        });
    };

    return (
        <section className="member-weeks" data-testid="member-weeks">
            <div className="member-weeks__who" role="group" aria-label={t('week.whoIsShown')}>
                {members.map((member) => (
                    <button
                        key={member.userId}
                        type="button"
                        className={`member-chip${hidden.has(member.userId) ? '' : ' is-shown'}`}
                        aria-pressed={!hidden.has(member.userId)}
                        title={member.userName}
                        onClick={() => toggle(member.userId)}
                    >
                        <span aria-hidden="true">{member.userName?.trim()?.charAt(0) || '?'}</span>
                        <span className="md-visually-hidden">{member.userName}</span>
                    </button>
                ))}
            </div>

            {members
                .filter((member) => !hidden.has(member.userId))
                .map((member) => (
                    <WeekCalendar
                        key={member.userId}
                        userId={member.userId}
                        title={member.userName}
                        refreshToken={refreshToken}
                    />
                ))}
        </section>
    );
}

export default MemberWeeks;
