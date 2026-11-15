package careops.compliance_test

import future.keywords.in
import data.careops.compliance

# 1. Staff role allowed
test_staff_allowed {
    compliance.allow with input as {
        "action": "execute",
        "tool": "get_carer_dbs_status",
        "user_role": "staff"
    }
}

# 2. Team Lead role allowed
test_team_lead_allowed {
    compliance.allow with input as {
        "action": "execute",
        "tool": "get_carer_dbs_status",
        "user_role": "team_lead"
    }
}

# 3. Department Head role allowed
test_department_head_allowed {
    compliance.allow with input as {
        "action": "execute",
        "tool": "get_carer_dbs_status",
        "user_role": "department_head"
    }
}

# 4. Unauthorized role denied
test_unauthorized_role_denied {
    not compliance.allow with input as {
        "action": "execute",
        "tool": "get_carer_dbs_status",
        "user_role": "guest"
    }
}

# 5. Invalid action denied
test_invalid_action_denied {
    not compliance.allow with input as {
        "action": "write",
        "tool": "get_carer_dbs_status",
        "user_role": "staff"
    }
}

# 6. Unknown tool denied
test_unknown_tool_denied {
    not compliance.allow with input as {
        "action": "execute",
        "tool": "delete_all_records",
        "user_role": "staff"
    }
}

# 7. Empty input denied
test_empty_input_denied {
    not compliance.allow with input as {}
}
