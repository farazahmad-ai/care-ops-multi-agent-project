import React, { useState } from "react";
import { useAuth } from "react-oidc-context";
import { Navbar } from "./components/Navbar";
import { Screen1Request } from "./components/Screen1Request";
import { extractRoles, loginWithDirectGrant } from "./auth";
import { Shield, Key } from "lucide-react";

export function App(): React.JSX.Element {
  const auth = useAuth();
  const [currentScreen, setCurrentScreen] = useState<1 | 2 | 3>(1);
  const [directToken, setDirectToken] = useState<string | null>(null);
  const [directUser, setDirectUser] = useState<{ email?: string; role?: string } | null>(null);
  const [authError, setAuthError] = useState<string | null>(null);
  const [isAuthenticating, setIsAuthenticating] = useState<boolean>(false);

  // Active token: either from standard OIDC redirect or direct test user grant
  const activeToken = auth.isAuthenticated ? (auth.user?.access_token ?? null) : directToken;

  // Active user email & primary role
  let userEmail = "";
  let userRole = "";

  if (auth.isAuthenticated && auth.user) {
    userEmail = auth.user.profile.email || auth.user.profile.preferred_username || "Staff User";
    const roles = extractRoles(auth.user);
    userRole = roles.find((r) => ["department_head", "team_lead", "staff"].includes(r)) || roles[0] || "staff";
  } else if (directUser) {
    userEmail = directUser.email || "Direct User";
    userRole = directUser.role || "staff";
  }

  const handleDirectLogin = async (username: string, pass: string, roleName: string) => {
    setIsAuthenticating(true);
    setAuthError(null);
    try {
      const { accessToken, userProfile } = await loginWithDirectGrant(username, pass);
      setDirectToken(accessToken);
      setDirectUser({
        email: (userProfile.email as string) || username,
        role: roleName,
      });
    } catch (err) {
      setAuthError(err instanceof Error ? err.message : "Failed to authenticate test user.");
    } finally {
      setIsAuthenticating(false);
    }
  };

  const handleLogout = () => {
    if (auth.isAuthenticated) {
      auth.removeUser();
      auth.signoutRedirect();
    }
    setDirectToken(null);
    setDirectUser(null);
  };

  return (
    <div className="min-h-screen flex flex-col bg-slate-50 text-slate-800">
      {/* Platform Navigation */}
      <Navbar
        currentScreen={currentScreen}
        onSelectScreen={setCurrentScreen}
        userEmail={activeToken ? userEmail : undefined}
        userRole={activeToken ? userRole : undefined}
        onLogout={handleLogout}
      />

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {!activeToken ? (
          /* Authentication Gate Screen */
          <div className="max-w-xl mx-auto my-8 bg-white border border-slate-200 shadow-sm rounded-2xl p-8 text-center space-y-6">
            <div className="w-16 h-16 bg-indigo-50 border border-indigo-100 rounded-2xl mx-auto flex items-center justify-center text-indigo-600 shadow-sm">
              <Shield className="w-8 h-8" />
            </div>

            <div>
              <h2 className="text-2xl font-bold text-slate-900">CareOps Platform Login</h2>
              <p className="text-sm text-slate-500 mt-2">
                All platform actions require authenticated identity verification via Keycloak OIDC
                and policy authorization via OPA (ADR-003, ADR-007).
              </p>
            </div>

            {authError && (
              <div className="bg-rose-50 border border-rose-200 text-rose-700 text-xs p-3 rounded-lg text-left">
                {authError}
              </div>
            )}

            {/* Standard Keycloak OIDC Redirect */}
            <div className="space-y-3 pt-2">
              <button
                onClick={() => auth.signinRedirect()}
                disabled={isAuthenticating}
                className="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white font-medium text-sm rounded-xl flex items-center justify-center space-x-2 shadow-sm transition-all"
              >
                <Key className="w-4 h-4" />
                <span>Sign in with Keycloak OIDC (SSO)</span>
              </button>
            </div>

            {/* Quick-Switch Pre-Configured Test Users */}
            <div className="pt-4 border-t border-slate-100">
              <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">
                1-Click Test User Sign-In (Direct Token Grant)
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <button
                  type="button"
                  onClick={() =>
                    handleDirectLogin("staff.user@example.test", "StaffPass123!", "staff")
                  }
                  disabled={isAuthenticating}
                  className="p-3 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 border border-slate-200 rounded-xl text-left transition-all"
                >
                  <div className="text-xs font-bold text-slate-800">Sarah Staff</div>
                  <div className="text-[11px] text-emerald-600 font-medium">Role: staff</div>
                  <div className="text-[10px] text-slate-400 mt-0.5">Allows compliance queries</div>
                </button>

                <button
                  type="button"
                  onClick={() =>
                    handleDirectLogin("teamlead.user@example.test", "TeamLead123!", "team_lead")
                  }
                  disabled={isAuthenticating}
                  className="p-3 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 border border-slate-200 rounded-xl text-left transition-all"
                >
                  <div className="text-xs font-bold text-slate-800">Tom Team Lead</div>
                  <div className="text-[11px] text-indigo-600 font-medium">Role: team_lead</div>
                  <div className="text-[10px] text-slate-400 mt-0.5">Approvals access</div>
                </button>

                <button
                  type="button"
                  onClick={() =>
                    handleDirectLogin("unauthorized.user@example.test", "Unauthorized123!", "unauthorized")
                  }
                  disabled={isAuthenticating}
                  className="p-3 bg-slate-50 hover:bg-rose-50 hover:border-rose-200 border border-slate-200 rounded-xl text-left transition-all"
                >
                  <div className="text-xs font-bold text-slate-800">Unauthorized User</div>
                  <div className="text-[11px] text-rose-600 font-medium">No permissions</div>
                  <div className="text-[10px] text-slate-400 mt-0.5">Tests OPA DENY rule</div>
                </button>
              </div>
            </div>
          </div>
        ) : (
          /* Authenticated Screens */
          <div>
            {currentScreen === 1 && (
              <Screen1Request token={activeToken} userRole={userRole} />
            )}

            {currentScreen === 2 && (
              <div className="max-w-4xl mx-auto bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4">
                <div className="w-12 h-12 bg-indigo-50 rounded-xl mx-auto flex items-center justify-center text-indigo-600">
                  <Shield className="w-6 h-6" />
                </div>
                <h2 className="text-xl font-bold text-slate-900">Screen 2: Approvals & Escalation Queue</h2>
                <p className="text-sm text-slate-500 max-w-md mx-auto">
                  High-risk actions and low-confidence outputs will queue here for named human
                  approval with one-click approve/reject/edit. Scheduled for delivery in <strong>Phase 4</strong>.
                </p>
                <button
                  onClick={() => setCurrentScreen(1)}
                  className="inline-flex text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                >
                  &larr; Return to Screen 1 (Requests)
                </button>
              </div>
            )}

            {currentScreen === 3 && (
              <div className="max-w-4xl mx-auto bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4">
                <div className="w-12 h-12 bg-indigo-50 rounded-xl mx-auto flex items-center justify-center text-indigo-600">
                  <Shield className="w-6 h-6" />
                </div>
                <h2 className="text-xl font-bold text-slate-900">Screen 3: Heads-Only Executive Dashboard</h2>
                <p className="text-sm text-slate-500 max-w-md mx-auto">
                  Displays exactly 6 key operational metrics (requests today, success rate, cost today
                  vs £20 budget, open approvals, active agents, audit log stream). Scheduled for delivery
                  in <strong>Phase 5</strong>.
                </p>
                <button
                  onClick={() => setCurrentScreen(1)}
                  className="inline-flex text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                >
                  &larr; Return to Screen 1 (Requests)
                </button>
              </div>
            )}
          </div>
        )}
      </main>

      {/* Platform Architecture & Boundary Footer */}
      <footer className="bg-white border-t border-slate-200 py-6 text-xs text-slate-500">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="flex items-center space-x-2">
            <span className="font-semibold text-slate-700">CareOps Platform:</span>
            <span>FastAPI (8000) &bull; Keycloak OIDC (8081) &bull; OPA (8181) &bull; PostgreSQL (5432) &bull; MCP Safe Gateway</span>
          </div>
          <div className="text-slate-400 text-right">
            <span>Isolation Boundary: Legacy MySQL (3307) unreachable by platform containers (NFR-1)</span>
          </div>
        </div>
      </footer>
    </div>
  );
}
export default App;
