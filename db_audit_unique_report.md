# Unique Index Audit

## Tables with Unique Indexes

- **users**
  - `email` (unique)
- **roles**
  - `name` (unique)
  - `slug` (unique)
- **branches**
  - `code` (unique)
- **capabilities**
  - `name` (unique)
  - `key` (unique)
- **user_role_assignments**
  - (`user_id`, `role_id`, `branch_id`) (unique composite)
- **user_policies**
  - (`user_id`, `branch_id`, `capability_key`) (unique composite)
- **personal_access_tokens**
  - `token` (unique)
- **failed_jobs**
  - `uuid` (unique)
- **role_capability**
  - (`role_id`, `capability_id`) (primary composite, unique by definition)

## Tables/Fields That May Need Unique Indexes

- **audit_logs**
  - No unique constraints. If `id` is always unique (as PK), this is fine. If any combination of fields should be unique (e.g., to prevent duplicate logs), consider adding.
- **transactions**
  - No unique constraints. If there is a business key (e.g., external reference, transaction code), consider enforcing uniqueness.
- **sessions**
  - `id` is PK (unique). No other unique constraints needed unless business logic requires.
- **job_batches, jobs, cache, cache_locks, password_reset_tokens**
  - All have PKs as unique.

## Recommendations

- Review `audit_logs` and `transactions` for any business keys that should be unique.
- Ensure all join/composite tables have unique constraints on their composite keys (already present).
- No missing unique indexes found for user, role, branch, capability, or token tables.
