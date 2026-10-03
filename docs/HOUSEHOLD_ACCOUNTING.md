# Household accounting

Every point account, summary wallet, money account, money transaction, payout and
savings goal belongs to `(teamId, userId)`. A weekly closure is unique for
`(teamId, userId, weekStart)`. Task points derive their household from the task or
task template, bonus payouts from their rule. Bonus conditions, leaderboards,
calendars and allowance conversion count only that household's activity.

## API contract

Pass `teamId` in the query for point balances, point calendars, allowance weeks,
wallets, payouts, goals and ledger reads. Pass it in the JSON body when closing or
reopening a week, offering a payout, recording income/expenses or planning a goal.
If exactly one authorized household is available, omission remains supported for
older clients. If more than one is available, omission returns 400. An explicitly
unauthorized household returns 403. Authentication and membership are rechecked on
every request.

Actions addressing a payout or goal by ID derive the household from that resource.
An administrator of another home cannot cancel the payout. The child can confirm a
payout or use a goal only while a member of its owning home. Leaving does not delete
its financial history. Rejoining the same home restores access to that history.
Money cannot be transferred between households, including through a savings goal.
Parents continue to see only payout summaries, not the child's personal expenses.

Mobile and web allowance screens let the user select their household. Task calendars,
point summaries and member calendars carry the selected team. New money operations
require a household. Legacy detached balances use the empty scope, never a wildcard.

## Migration and rollout

Apply PR #108 first, then this change. Update the clients together with the backend;
older clients remain usable for a single authorized household, but cannot select
between multiple households. Back up the database before applying
`Version20261004003000` and run it in a maintenance window with writers stopped.

The migration inspects historical memberships, task ownership and weekly closures
before queuing any changes. For a user with financial records and exactly one
historical household, it assigns all records to that household without changing IDs,
amounts, entries, balances or payout states. A user without any household evidence
keeps a detached ledger. No balance is duplicated or merged.

If there is evidence of multiple households for a user with existing financial
records, migration stops before changing tables. That history needs an explicit,
reviewed allocation migration based on entries and payments; removing memberships
to force a guess is not a remedy. This limitation concerns ambiguous historical
records, not new users joining two homes after migration. The rollback intentionally
refuses to merge independent balances; restore the backup if rollback is needed.

Tests cover separate balances and reversals, rule evaluation, weekly closures,
payout authorization and confirmation, saving/spending, selection ambiguity,
membership revocation, and preservation/refusal of legacy migration data.
