import React from 'react';
import { useTranslation } from 'react-i18next';
import AllowanceRulesForm from '../components/allowance/AllowanceRulesForm';
import AllowanceWeek from '../components/allowance/AllowanceWeek';
import BookingForm from '../components/allowance/BookingForm';
import GoalBoard from '../components/allowance/GoalBoard';
import GoalForm from '../components/allowance/GoalForm';
import LedgerList from '../components/allowance/LedgerList';
import PayoutForm from '../components/allowance/PayoutForm';
import Section from '../components/Section';
import WalletCards from '../components/allowance/WalletCards';
import Button from '../components/md3/Button';
import Dialog from '../components/md3/Dialog';
import IconButton from '../components/md3/IconButton';
import Icon from '../components/md3/Icon';
import { CircularProgress } from '../components/md3/Progress';
import allowanceService from '../services/allowanceService';
import teamService from '../services/teamService';
import '../styles/allowance.css';

function Allowance({ user }) {
    const { t } = useTranslation();
    const [tab, setTab] = React.useState(null);
    const [adminTeams, setAdminTeams] = React.useState(null);
    const [teamId, setTeamId] = React.useState(null);
    const [members, setMembers] = React.useState([]);
    const [memberId, setMemberId] = React.useState(null);
    const [wallet, setWallet] = React.useState(null);
    const [memberWallet, setMemberWallet] = React.useState(null);
    const [ledger, setLedger] = React.useState(null);
    const [goals, setGoals] = React.useState(null);
    const [memberGoals, setMemberGoals] = React.useState(null);
    const [rules, setRules] = React.useState(null);
    const [refresh, setRefresh] = React.useState(0);
    const [error, setError] = React.useState(null);
    const [dialog, setDialog] = React.useState(null);

    const reload = () => setRefresh((count) => count + 1);

    const quiet = (promise) => Promise.resolve(promise).catch(() => undefined);

    const run = (promise) => promise
        .then((result) => {
            setError(null);
            reload();
            return result;
        })
        .catch((failure) => {
            setError(failure?.response?.data?.error || t('errors.generic'));
            throw failure;
        });

    React.useEffect(() => {
        const membersOf = (id) => teamService.getTeamMembers(id)
            .then((data) => (data.members || []).filter((member) => member.role !== 'admin'))
            .catch(() => []);

        teamService.getTeams()
            .then((data) => (data.teams || []).filter((team) => team.role === 'admin'))
            .then((administered) => Promise.all(
                administered.map((team) => membersOf(team.id).then((people) => [team, people.length]))
            ))
            .then((counted) => {
                const looksAfter = counted.filter(([, people]) => people > 0).map(([team]) => team);

                setAdminTeams(looksAfter);
                setTeamId((current) => current || looksAfter[0]?.id || null);
                setTab((current) => current || (looksAfter.length > 0 ? 'settle' : 'mine'));
            })
            .catch(() => {
                setAdminTeams([]);
                setTab('mine');
            });
    }, []);

    React.useEffect(() => {
        if (!teamId) {
            setMembers([]);
            return;
        }

        teamService.getTeamMembers(teamId)
            .then((data) => {
                const people = (data.members || []).filter((member) => member.role !== 'admin');
                setMembers(people);
                setMemberId((current) => current || people[0]?.userId || null);
            })
            .catch(() => setMembers([]));
    }, [teamId]);

    React.useEffect(() => {
        if (tab !== 'mine') {
            return;
        }

        allowanceService.getWallet().then(setWallet).catch(() => setWallet(null));
        allowanceService.getLedger().then(setLedger).catch(() => setLedger(null));
        allowanceService.getGoals().then(setGoals).catch(() => setGoals(null));
    }, [tab, refresh]);

    React.useEffect(() => {
        if (!teamId || tab === 'mine') {
            return;
        }

        allowanceService.getRules(teamId).then(setRules).catch(() => setRules(null));
    }, [teamId, tab, refresh]);

    React.useEffect(() => {
        if (!memberId || tab !== 'settle') {
            return;
        }

        allowanceService.getWallet(memberId).then(setMemberWallet).catch(() => setMemberWallet(null));
        allowanceService.getGoals(memberId).then(setMemberGoals).catch(() => setMemberGoals(null));
    }, [memberId, tab, refresh]);

    const administers = (adminTeams || []).length > 0;
    const tabs = administers ? ['settle', 'rules'] : [];

    if (tab === null) {
        return (
            <div className="allowance-page">
                <CircularProgress label={t('common.loading')} />
            </div>
        );
    }

    return (
        <div className="allowance-page">
            <header className="allowance-page__head">
                <h2>
                    <Icon name="wallet" size={24} />
                    {t('allowance.title')}
                </h2>
            </header>

            {tabs.length > 1 && (
                <div className="allowance-page__tabs" role="tablist">
                    {tabs.map((id) => (
                        <button
                            key={id}
                            type="button"
                            role="tab"
                            aria-selected={tab === id}
                            className={`allowance-tab${tab === id ? ' is-active' : ''}`}
                            onClick={() => setTab(id)}
                        >
                            {t(`allowance.tabs.${id}`)}
                        </button>
                    ))}
                </div>
            )}

            {error && <p className="form-error" role="alert">{error}</p>}

            {administers && adminTeams.length > 1 && (
                <div className="allowance-page__teams" role="group" aria-label={t('allowance.whichTeam')}>
                    {adminTeams.map((team) => (
                        <button
                            key={team.id}
                            type="button"
                            className={`member-chip${teamId === team.id ? ' is-shown' : ''}`}
                            aria-pressed={teamId === team.id}
                            onClick={() => { setTeamId(team.id); setMemberId(null); }}
                        >
                            {team.name}
                        </button>
                    ))}
                </div>
            )}

            {tab === 'mine' && (
                <>
                    <Section id="wallet" icon="wallet" title={t('allowance.myWallet')}>
                        <WalletCards
                            wallet={wallet}
                            onConfirmPayout={(payoutId) => quiet(run(allowanceService.confirmPayout(payoutId)))}
                        />
                    </Section>

                    <Section id="weeks" icon="calendar" title={t('allowance.myWeeks')}>
                        <AllowanceWeek userId={user?.id} refreshToken={refresh} mine />
                    </Section>

                    <Section
                        id="goals"
                        icon="goal"
                        title={t('allowance.myGoals')}
                        actions={(
                            <IconButton
                                icon="add"
                                variant="filled"
                                label={t('allowance.planGoal')}
                                onClick={() => setDialog('goal')}
                            />
                        )}
                    >
                        <GoalBoard
                            goals={goals}
                            onPutAside={(goalId, amount) => run(allowanceService.putAside(goalId, amount))}
                            onTakeBack={(goalId, amount) => run(allowanceService.takeBack(goalId, amount))}
                            onSpend={(goalId, amount, name) => quiet(run(allowanceService.spendGoal(goalId, amount, name)))}
                            onClose={(goalId) => quiet(run(allowanceService.closeGoal(goalId)))}
                        />
                    </Section>

                    <Section
                        id="ledger"
                        icon="schedule"
                        title={t('allowance.myMoney')}
                        actions={(
                            <>
                                <IconButton
                                    icon="add"
                                    variant="tonal"
                                    label={t('allowance.addIncome')}
                                    onClick={() => setDialog('income')}
                                />
                                <IconButton
                                    icon="remove"
                                    variant="tonal"
                                    label={t('allowance.addExpense')}
                                    onClick={() => setDialog('expense')}
                                />
                            </>
                        )}
                    >
                        <LedgerList ledger={ledger} />
                    </Section>

                    <Dialog
                        open={dialog === 'income'}
                        onClose={() => setDialog(null)}
                        headline={t('allowance.addIncome')}
                    >
                        <BookingForm
                            kind="income"
                            onSubmit={(booking) => run(allowanceService.addIncome(booking))}
                            onCancel={() => setDialog(null)}
                        />
                    </Dialog>

                    <Dialog
                        open={dialog === 'expense'}
                        onClose={() => setDialog(null)}
                        headline={t('allowance.addExpense')}
                    >
                        <BookingForm
                            kind="expense"
                            onSubmit={(booking) => run(allowanceService.addExpense(booking))}
                            onCancel={() => setDialog(null)}
                        />
                    </Dialog>

                    <Dialog
                        open={dialog === 'goal'}
                        onClose={() => setDialog(null)}
                        headline={t('allowance.planGoal')}
                    >
                        <GoalForm
                            onPlan={(goal) => run(allowanceService.planGoal(goal))}
                            onCancel={() => setDialog(null)}
                        />
                    </Dialog>
                </>
            )}

            {tab === 'settle' && (
                <>
                    <div className="allowance-page__members" role="group" aria-label={t('allowance.whoToSettle')}>
                        {members.map((member) => (
                            <button
                                key={member.userId}
                                type="button"
                                className={`member-chip${memberId === member.userId ? ' is-shown' : ''}`}
                                aria-pressed={memberId === member.userId}
                                onClick={() => setMemberId(member.userId)}
                            >
                                {member.userName}
                            </button>
                        ))}
                        {members.length === 0 && <p className="empty-hint">{t('allowance.noMembersYet')}</p>}
                    </div>

                    {memberId && (
                        <>
                            <Section id="member-weeks" icon="calendar" title={t('allowance.memberWeeks')}>
                            <AllowanceWeek
                                userId={memberId}
                                refreshToken={refresh}
                                renderActions={(week) => (
                                    <div className="allowance-week__actions">
                                        {week.closure === null ? (
                                            <>
                                                <Button
                                                    variant="filled"
                                                    disabled={!week.isOver}
                                                    onClick={() => quiet(run(allowanceService.closeWeek(memberId, week.weekStart)))}
                                                >
                                                    {t('allowance.closeWeek')}
                                                </Button>
                                                {!week.isOver && (
                                                    <p className="empty-hint">{t('allowance.weekNotOverYet')}</p>
                                                )}
                                            </>
                                        ) : (
                                            <Button
                                                variant="outlined"
                                                onClick={() => quiet(run(allowanceService.reopenWeek(memberId, week.weekStart)))}
                                            >
                                                {t('allowance.reopenWeek')}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            />
                            </Section>

                            <Section id="member-wallet" icon="wallet" title={t('allowance.memberWallet')}>
                                <WalletCards wallet={memberWallet} readOnly />

                                <PayoutForm
                                    wallet={memberWallet}
                                    onOffer={(payout) => run(allowanceService.offerPayout({ ...payout, userId: memberId }))}
                                />
                            </Section>

                            <Section id="member-goals" icon="goal" title={t('allowance.memberGoals')} defaultOpen={false}>
                                <GoalBoard goals={memberGoals} readOnly />
                            </Section>
                        </>
                    )}
                </>
            )}

            {tab === 'rules' && (
                <AllowanceRulesForm
                    rules={rules}
                    onSave={(rule) => run(allowanceService.setRule({ ...rule, teamId }))}
                    onRemove={(pointsAccount) => quiet(run(allowanceService.removeRule(teamId, pointsAccount)))}
                />
            )}
        </div>
    );
}

export default Allowance;
