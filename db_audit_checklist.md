# Comprehensive Security & Performance Audit Checklist  
## 1. Audit DB Constraints: Foreign Keys & Unique Indexes

### A. Foreign Key Constraints

- [x] **role_capability**: All FKs enforced (role_id, capability_id)
- [x] **user_role_assignments**: FKs for role_id, branch_id enforced; user_id FK missing (commented out)
- [x] **user_policies**: No FKs enforced for user_id, branch_id
- [x] **transactions**: user_id FK missing (commented out)
- [x] **sessions**: user_id FK missing
- [x] **audit_logs**: actor_id, subject_id FKs missing

**Action:**  
- Enforce missing FKs for all relationship fields (user_id, branch_id, etc.) unless there is a documented reason not to.

### B. Unique Indexes

- [x] **users**: email unique
- [x] **roles**: name, slug unique
- [x] **branches**: code unique
- [x] **capabilities**: name, key unique
- [x] **user_role_assignments**: (user_id, role_id, branch_id) unique
- [x] **user_policies**: (user_id, branch_id, capability_key) unique
- [x] **personal_access_tokens**: token unique
- [x] **failed_jobs**: uuid unique
- [x] **role_capability**: (role_id, capability_id) unique (composite PK)
- [x] **All other tables**: PKs are unique

**Action:**  
- Review audit_logs and transactions for any business keys that should be unique and enforce as needed.

### C. General Recommendations

- [x] Document any intentional omissions of FKs or unique constraints.
- [x] Test all new constraints on a copy of production data before deployment.

---

**See detailed findings and recommendations in:**  
- [`db_audit_fk_report.md`](db_audit_fk_report.md)
- [`db_audit_unique_report.md`](db_audit_unique_report.md)
- [`db_audit_recommendations.md`](db_audit_recommendations.md)