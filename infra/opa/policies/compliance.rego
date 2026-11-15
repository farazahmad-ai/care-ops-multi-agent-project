package careops.compliance

import future.keywords.in

default allow = false

# Authorized business roles for DBS status lookups (FR-1, FR-2, ADR-003)
authorized_dbs_roles := {"staff", "team_lead", "department_head"}

# Allow rule: verified user role + recognized tool + execute action
allow {
    input.action == "execute"
    input.tool == "get_carer_dbs_status"
    input.user_role in authorized_dbs_roles
}

# Detailed denial reasons for audit logging (FR-18, NFR-11)
reasons[reason] {
    not input.user_role in authorized_dbs_roles
    reason := sprintf("User role '%v' is not authorized to execute DBS lookups", [input.user_role])
}

reasons[reason] {
    input.tool != "get_carer_dbs_status"
    reason := sprintf("Tool '%v' is not permitted by this policy", [input.tool])
}

reasons[reason] {
    input.action != "execute"
    reason := sprintf("Action '%v' is not permitted", [input.action])
}
