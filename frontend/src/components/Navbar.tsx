import React from "react";
import { Shield, Lock, UserCheck, LogOut } from "lucide-react";

interface NavbarProps {
  currentScreen: 1 | 2 | 3;
  onSelectScreen: (screen: 1 | 2 | 3) => void;
  userEmail?: string;
  userRole?: string;
  onLogout: () => void;
}

export const Navbar: React.FC<NavbarProps> = ({
  currentScreen,
  onSelectScreen,
  userEmail,
  userRole,
  onLogout,
}) => {
  return (
    <header className="bg-slate-900 border-b border-slate-800 text-white sticky top-0 z-50 shadow-md">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo and Brand */}
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center text-white shadow-indigo-500/20 shadow-lg">
              <Shield className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <span className="font-bold text-lg tracking-tight">CareOps</span>
                <span className="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                  Platform
                </span>
              </div>
              <p className="text-xs text-slate-400">UK Care Staffing Automation</p>
            </div>
          </div>

          {/* Three Screen Navigation (AGENTS.md frontend scope) */}
          <nav className="flex space-x-1 sm:space-x-2">
            <button
              onClick={() => onSelectScreen(1)}
              className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                currentScreen === 1
                  ? "bg-slate-800 text-indigo-400 border border-slate-700"
                  : "text-slate-300 hover:bg-slate-800 hover:text-white"
              }`}
            >
              1. Requests & Progress
            </button>

            <button
              onClick={() => onSelectScreen(2)}
              className={`px-3 py-2 rounded-md text-sm font-medium transition-colors flex items-center space-x-1.5 ${
                currentScreen === 2
                  ? "bg-slate-800 text-indigo-400 border border-slate-700"
                  : "text-slate-400 hover:bg-slate-800/50 hover:text-slate-300"
              }`}
            >
              <span>2. Approvals</span>
              <span className="text-[10px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded border border-slate-700">
                Phase 4
              </span>
            </button>

            <button
              onClick={() => onSelectScreen(3)}
              className={`px-3 py-2 rounded-md text-sm font-medium transition-colors flex items-center space-x-1.5 ${
                currentScreen === 3
                  ? "bg-slate-800 text-indigo-400 border border-slate-700"
                  : "text-slate-400 hover:bg-slate-800/50 hover:text-slate-300"
              }`}
            >
              <span>3. Dashboard</span>
              <span className="text-[10px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded border border-slate-700">
                Phase 5
              </span>
            </button>
          </nav>

          {/* User Profile and Auth Status */}
          <div className="flex items-center space-x-4">
            {userEmail ? (
              <div className="flex items-center space-x-3">
                <div className="text-right hidden sm:block">
                  <div className="text-xs font-medium text-slate-200">{userEmail}</div>
                  <div className="text-[11px] font-mono text-emerald-400 flex items-center justify-end space-x-1">
                    <UserCheck className="w-3 h-3" />
                    <span>Role: {userRole || "staff"}</span>
                  </div>
                </div>

                <button
                  onClick={onLogout}
                  title="Sign out"
                  className="p-2 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition-colors"
                >
                  <LogOut className="w-5 h-5" />
                </button>
              </div>
            ) : (
              <div className="flex items-center space-x-2 text-xs text-amber-400 bg-amber-950/40 border border-amber-800/60 px-2.5 py-1.5 rounded-lg">
                <Lock className="w-4 h-4" />
                <span>Not Authenticated</span>
              </div>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};
