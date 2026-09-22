import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import schoolTimetableService from '../services/schoolTimetableService';
import dayPlanningService from '../services/dayPlanningService';
import teamService from '../services/teamService';
import { Button, Chip, Icon, TextField, CircularProgress } from '../components/md3';
import '../styles/school-timetable.css';

const BLANK = { schoolId: '', login: '', password: '' };

function SchoolTimetable({ manages }) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [teams, setTeams] = useState([]);
    const [teamId, setTeamId] = useState(null);
    const [members, setMembers] = useState([]);
    const [tags, setTags] = useState([]);
    const [account, setAccount] = useState(null);
    const [signIn, setSignIn] = useState(BLANK);
    const [week, setWeek] = useState(() => schoolTimetableService.mondayOf(new Date()));
    const [summary, setSummary] = useState(null);

    const load = useCallback(async (chosen) => {
        try {
            setLoading(true);
            setError(null);

            const data = await teamService.getTeams();
            const mine = (data.teams || []).filter((team) => team.role === 'admin');
            setTeams(mine);

            const team = chosen || mine[0]?.id || null;
            setTeamId(team);

            if (!team) {
                setAccount(null);
                return;
            }

            const [held, people, labels] = await Promise.all([
                schoolTimetableService.config(team),
                teamService.getTeamMembers(team),
                dayPlanningService.tags(team),
            ]);

            setMembers(people.members || []);
            setTags(labels.tags || []);
            setAccount(held.account);
            setSignIn(held.account
                ? { schoolId: held.account.schoolId, login: held.account.login, password: '' }
                : BLANK);
        } catch (err) {
            console.error('Failed to load school timetable config:', err);
            setError(t('schoolTimetable.loadError'));
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        if (manages) {
            load();
        } else {
            setLoading(false);
        }
    }, [manages, load]);

    const work = async (task, failure) => {
        try {
            setBusy(true);
            setError(null);
            await task();
        } catch (err) {
            console.error('School timetable action failed:', err);
            setError(t(failure));
        } finally {
            setBusy(false);
        }
    };

    const handleSave = (event) => {
        event.preventDefault();

        return work(async () => {
            const { account: kept } = await schoolTimetableService.saveConfig({
                teamId,
                schoolId: signIn.schoolId.trim(),
                login: signIn.login.trim(),
                ...(signIn.password ? { password: signIn.password } : {}),
            });

            setAccount(kept);
            setSignIn({ schoolId: kept.schoolId, login: kept.login, password: '' });
        }, 'schoolTimetable.saveError');
    };

    const handleFindStudents = () => work(async () => {
        const { account: found } = await schoolTimetableService.refreshStudents(teamId, week);
        setAccount(found);
    }, 'schoolTimetable.signInError');

    const handleLink = (studentId, userId) => work(async () => {
        const { account: linked } = await schoolTimetableService.linkStudents(teamId, { [studentId]: userId });
        setAccount(linked);
    }, 'schoolTimetable.saveError');

    const handleTag = (tagId) => work(async () => {
        const { account: tagged } = await schoolTimetableService.saveConfig({
            teamId,
            schoolId: account.schoolId,
            login: account.login,
            tagId,
        });

        setAccount(tagged);
    }, 'schoolTimetable.saveError');

    const handleImport = () => work(async () => {
        const done = await schoolTimetableService.importWeek(teamId, week);
        setSummary(done);
        setAccount(done.account);
    }, 'schoolTimetable.importError');

    const handleForget = () => work(async () => {
        await schoolTimetableService.forgetConfig(teamId);
        setAccount(null);
        setSignIn(BLANK);
        setSummary(null);
    }, 'schoolTimetable.saveError');

    if (!manages) {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <h2>{t('schoolTimetable.accessDeniedTitle')}</h2>
                <p>{t('schoolTimetable.accessDeniedBody')}</p>
            </div>
        );
    }

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    if (!teams.length) {
        return (
            <div className="school-timetable">
                <h2>{t('schoolTimetable.title')}</h2>
                <p className="school-timetable__description">{t('schoolTimetable.noTeam')}</p>
            </div>
        );
    }

    const thisWeek = schoolTimetableService.mondayOf(new Date());
    const nextWeek = schoolTimetableService.weekAfter(thisWeek);

    return (
        <div className="school-timetable">
            <h2>{t('schoolTimetable.title')}</h2>
            <p className="school-timetable__description">{t('schoolTimetable.description')}</p>

            {error && (
                <div className="alert alert-error" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {teams.length > 1 && (
                <div className="school-timetable__chips">
                    {teams.map((team) => (
                        <Chip
                            key={team.id}
                            selected={team.id === teamId}
                            onClick={() => load(team.id)}
                        >
                            {team.name}
                        </Chip>
                    ))}
                </div>
            )}

            <form className="school-timetable__panel" onSubmit={handleSave}>
                <h3>{t('schoolTimetable.signInTitle')}</h3>
                <p className="school-timetable__hint">{t('schoolTimetable.signInHint')}</p>

                <TextField
                    id="school-timetable-school"
                    label={t('schoolTimetable.school')}
                    value={signIn.schoolId}
                    required
                    supportingText={t('schoolTimetable.schoolHint')}
                    onChange={(event) => setSignIn((current) => ({ ...current, schoolId: event.target.value }))}
                />

                <TextField
                    id="school-timetable-login"
                    label={t('schoolTimetable.login')}
                    value={signIn.login}
                    required
                    onChange={(event) => setSignIn((current) => ({ ...current, login: event.target.value }))}
                />

                <TextField
                    id="school-timetable-password"
                    type="password"
                    label={account ? t('schoolTimetable.passwordKept') : t('schoolTimetable.password')}
                    value={signIn.password}
                    required={!account}
                    supportingText={t('schoolTimetable.passwordHint')}
                    onChange={(event) => setSignIn((current) => ({ ...current, password: event.target.value }))}
                />

                <div className="school-timetable__actions">
                    <Button type="submit" icon="check" loading={busy}>{t('common.save')}</Button>
                    {account && (
                        <Button type="button" variant="text" icon="delete" onClick={handleForget} disabled={busy}>
                            {t('schoolTimetable.forget')}
                        </Button>
                    )}
                </div>
            </form>

            {account && (
                <div className="school-timetable__panel">
                    <h3>{t('schoolTimetable.studentsTitle')}</h3>
                    <p className="school-timetable__hint">{t('schoolTimetable.studentsHint')}</p>

                    <div className="school-timetable__actions">
                        <Button type="button" variant="tonal" icon="person" onClick={handleFindStudents} disabled={busy}>
                            {t('schoolTimetable.findStudents')}
                        </Button>
                    </div>

                    {account.students.length === 0 && (
                        <p className="school-timetable__hint">{t('schoolTimetable.noStudents')}</p>
                    )}

                    {account.students.map((student) => (
                        <div className="school-timetable__student" key={student.studentId}>
                            <span className="school-timetable__student-name">{student.studentName}</span>
                            <div className="school-timetable__chips">
                                {members.map((member) => (
                                    <Chip
                                        key={member.userId}
                                        selected={member.userId === student.userId}
                                        onClick={() => handleLink(student.studentId, member.userId === student.userId ? null : member.userId)}
                                    >
                                        {member.givenName || member.userName}
                                    </Chip>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {account && (
                <div className="school-timetable__panel">
                    <h3>{t('schoolTimetable.importTitle')}</h3>
                    <p className="school-timetable__hint">{t('schoolTimetable.importHint')}</p>

                    <div className="school-timetable__student">
                        <span className="school-timetable__student-name">{t('schoolTimetable.tagTitle')}</span>
                        <p className="school-timetable__hint">{t('schoolTimetable.tagHint')}</p>
                        <div className="school-timetable__chips">
                            <Chip selected={!account.tagId} onClick={() => handleTag(null)}>
                                {t('schoolTimetable.noTag')}
                            </Chip>
                            {tags.map((tag) => (
                                <Chip
                                    key={tag.id}
                                    selected={tag.id === account.tagId}
                                    onClick={() => handleTag(tag.id === account.tagId ? null : tag.id)}
                                >
                                    {tag.name}
                                </Chip>
                            ))}
                        </div>
                    </div>

                    <div className="school-timetable__chips">
                        <Chip selected={week === thisWeek} onClick={() => setWeek(thisWeek)}>
                            {t('schoolTimetable.thisWeek')}
                        </Chip>
                        <Chip selected={week === nextWeek} onClick={() => setWeek(nextWeek)}>
                            {t('schoolTimetable.nextWeek')}
                        </Chip>
                    </div>

                    <div className="school-timetable__actions">
                        <Button type="button" icon="calendar" onClick={handleImport} loading={busy}>
                            {t('schoolTimetable.import')}
                        </Button>
                    </div>

                    {summary && (
                        <div className="school-timetable__summary" role="status">
                            <div>
                                {t('schoolTimetable.summary', {
                                    from: summary.weekStart,
                                    to: summary.weekEnd,
                                    added: summary.added,
                                    updated: summary.updated,
                                    removed: summary.removed,
                                    unchanged: summary.unchanged,
                                })}
                            </div>
                            <div className="school-timetable__hint">
                                {t('schoolTimetable.summaryDetails', {
                                    substitutions: summary.substitutions,
                                    unlinked: summary.skipped.unlinked,
                                    cancelled: summary.skipped.cancelled,
                                })}
                            </div>
                        </div>
                    )}

                    {account.importedAt && (
                        <p className="school-timetable__hint">
                            {t('schoolTimetable.lastImport', { at: new Date(account.importedAt).toLocaleString() })}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

export default SchoolTimetable;
