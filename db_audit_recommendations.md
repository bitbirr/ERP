# DB Constraints Audit: Recommendations

## Foreign Keys

- **Enforce missing FKs**:
  - `user_role_assignments.user_id` → users.id (currently commented out)
  - `user_policies.user_id` → users.id
  - `user_policies.branch_id` → branches.id
  - `transactions.user_id` → users.id (currently commented out)
  - `sessions.user_id` → users.id
  - `audit_logs.actor_id` → users.id (nullable)
  - `audit_logs.subject_id` → referenced table if always consistent

- **Why**: Enforcing FKs ensures referential integrity, prevents orphaned records, and improves data consistency.

## Unique Indexes

- **Review for business keys**:
  - `audit_logs`: If any combination of fields (e.g., action, subject_type, subject_id, created_at) should be unique, add a unique index.
  - `transactions`: If there is a business key (e.g., external reference, transaction code), enforce uniqueness.

- **Why**: Unique indexes prevent duplicate records and enforce business rules at the DB level.

## General

- **Document exceptions**: If FKs or unique constraints are intentionally omitted (e.g., for performance or flexibility), document the rationale in code comments and/or schema docs.
- **Test migrations**: After adding constraints, test migrations on a copy of production data to catch violations and ensure smooth deployment.
