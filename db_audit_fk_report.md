# Foreign Key Constraint Audit

## Tables with Foreign Key Constraints

- **role_capability**
  - `role_id` → roles.id (cascade)
  - `capability_id` → capabilities.id (cascade)
- **user_role_assignments**
  - `role_id` → roles.id (cascade)
  - `branch_id` → branches.id (null on delete)
  - `user_id` → users.id (**NOT ENFORCED**, FK commented out)
- **sessions**
  - `user_id` → users.id (nullable, indexed, but not enforced as FK)
- **user_policies**
  - `user_id`, `branch_id` (no FKs enforced)
- **transactions**
  - `user_id` (FK commented out, not enforced)
- **audit_logs**
  - `actor_id`, `subject_id` (no FKs enforced)
- **Other tables** (branches, roles, capabilities, etc.): No FKs needed or all PKs.

## Summary of Missing/Weak FKs

- **user_role_assignments.user_id**: FK definition is commented out. Should enforce FK to users.id.
- **user_policies.user_id**: No FK. Should enforce FK to users.id.
- **user_policies.branch_id**: No FK. Should enforce FK to branches.id.
- **transactions.user_id**: FK definition is commented out. Should enforce FK to users.id.
- **sessions.user_id**: No explicit FK. Should enforce FK to users.id.
- **audit_logs.actor_id**: No FK. Should enforce FK to users.id (nullable).
- **audit_logs.subject_id**: No FK. If always references a table, consider enforcing FK.

## Recommendations

- Enforce all commented-out or missing FKs for user_id, branch_id, and similar relationship fields.
- For audit_logs, if subject_id/actor_id always reference a specific table, enforce FKs; otherwise, document why not.
- Review all relationship tables for missing FKs and add them for data integrity.
