# API authorization boundaries

`ROLE_ADMIN` is a service administrator, not a parent. Public registration creates
`ROLE_USER`; creating a family gives that user an `admin` membership in that family.
A family administrator must never be promoted to `ROLE_ADMIN` to configure their home.

| Operation | Allowed caller |
| --- | --- |
| List accounts and read basic profiles | Own account and current family members; service administrators can read all accounts |
| Read a user's aggregate points through `/api/users/{id}/points` | Self, administrator of a shared family, or service administrator |
| Create an account through `/api/users` or reset its password | Service administrator only; family membership alone is insufficient |
| Register an account | Public registration, always `ROLE_USER` |
| Read bonus rules | Current members of the owning family |
| Create, edit, activate or deactivate bonus rules | Administrator of the owning family |
| Reference a task type from a bonus rule | Task type must belong to the rule's family |
| Create a task | Administrator of that family; creator comes from authentication, never the payload |
| Assign a task during creation | Assignee must belong to the same family |
| Read, complete or unassign a legacy task | Current family membership, plus the existing operation-specific checks |

An inaccessible account or bonus rule returns 404 so that a supplied identifier does
not reveal whether a foreign resource exists. A member attempting an administrator-only
operation receives 403. Removing membership revokes family access immediately.

`FamilyIsolationApiTest` exercises two families whose parents have `ROLE_USER`, including
role escalation, password resets, direct object access, mutations, creator spoofing,
assignment and notifications. Existing service-administrator tests preserve the
explicit administration workflow.

This change secures the existing API boundaries. It does not introduce child accounts,
a password-recovery flow, new family roles or separate financial ledgers per household.
Points and allowance remain user-level aggregates: a shared child's accounting across
multiple homes requires the separate product/model change described in the roadmap.
