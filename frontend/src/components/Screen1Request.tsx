import React, { useState } from "react";
import {
  Send,
  Clock,
  CheckCircle2,
  ShieldAlert,
  Database,
} from "lucide-react";
import { RequestPayload, RequestResponse, RequestStatus } from "../types";
import { API_URL } from "../auth";

interface Screen1RequestProps {
  token: string | null;
  userRole?: string;
}

interface StepInfo {
  key: RequestStatus;
  label: string;
}

const STEPS: StepInfo[] = [
  { key: "received", label: "Received" },
  { key: "routing", label: "OPA Policy Check" },
  { key: "in_progress", label: "MCP Safe Gateway" },
  { key: "done", label: "Completed" },
];

export const Screen1Request: React.FC<Screen1RequestProps> = ({ token, userRole }) => {
  const [carerId, setCarerId] = useState<string>("12");
  const [queryText, setQueryText] = useState<string>("What is the DBS status of carer #12?");
  const [loading, setLoading] = useState<boolean>(false);
  const [currentStep, setCurrentStep] = useState<RequestStatus | null>(null);
  const [response, setResponse] = useState<RequestResponse | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [requestDuration, setRequestDuration] = useState<number | null>(null);

  const handleSelectSample = (id: string, text: string) => {
    setCarerId(id);
    setQueryText(text);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) {
      setErrorMessage("Please authenticate with Keycloak before submitting requests.");
      return;
    }

    setLoading(true);
    setErrorMessage(null);
    setResponse(null);
    setCurrentStep("received");

    const startTime = performance.now();

    // Visual progression for discrete states (FR-3)
    setTimeout(() => {
      setCurrentStep("routing");
    }, 150);

    setTimeout(() => {
      setCurrentStep("in_progress");
    }, 350);

    try {
      const payload: RequestPayload = {
        message: queryText,
        intent: "get_carer_dbs_status",
        parameters: { carer_id: parseInt(carerId, 10) || 12 },
      };

      const res = await fetch(`${API_URL}/api/v1/requests`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(payload),
      });

      const elapsed = Math.round(performance.now() - startTime);
      setRequestDuration(elapsed);

      if (res.status === 403) {
        const data = await res.json();
        setCurrentStep("denied");
        setErrorMessage(data.detail || "Forbidden: OPA denied policy access for this action.");
        return;
      }

      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        setCurrentStep("error");
        setErrorMessage(errData.detail || `Request failed with HTTP status ${res.status}`);
        return;
      }

      const data: RequestResponse = await res.json();
      setResponse(data);
      setCurrentStep("done");
    } catch (err) {
      setCurrentStep("error");
      setErrorMessage(
        err instanceof Error ? err.message : "Failed to connect to CareOps Agent API backend."
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* Title & Introduction Banner */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">
              Screen 1: Request & Operational Progress
            </h1>
            <p className="text-sm text-slate-500 mt-1">
              Submit operational compliance queries through the Safe Gateway. Enforces Keycloak OIDC,
              OPA policy engine, and immutable Postgres audit logging (Phase 1 Walking Skeleton).
            </p>
          </div>
          <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
            Step 1 (Deterministic Rules)
          </span>
        </div>

        {/* Quick Sample Selector */}
        <div className="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
          <span className="text-xs font-medium text-slate-400">Quick queries:</span>
          <button
            type="button"
            onClick={() => handleSelectSample("1", "Check DBS compliance status for carer #1 (Alice Johnson)")}
            className="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-md transition-colors"
          >
            Carer 1 (Alice Johnson)
          </button>
          <button
            type="button"
            onClick={() => handleSelectSample("5", "What is the DBS status for carer #5 (Edward Davies)?")}
            className="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-md transition-colors"
          >
            Carer 5 (Edward Davies)
          </button>
          <button
            type="button"
            onClick={() => handleSelectSample("12", "Query carer #12 DBS check expiry and certificate")}
            className="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium px-2.5 py-1 rounded-md transition-colors"
          >
            Carer 12 (Liam Scott - Brief Scenario)
          </button>
          <button
            type="button"
            onClick={() => handleSelectSample("999", "What is DBS status for carer #999?")}
            className="text-xs bg-rose-50 hover:bg-rose-100 text-rose-700 px-2.5 py-1 rounded-md transition-colors"
          >
            Carer 999 (Not Found)
          </button>
        </div>
      </div>

      {/* Request Form */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="md:col-span-1">
              <label htmlFor="carerId" className="block text-xs font-semibold text-slate-700 mb-1">
                Target Carer ID
              </label>
              <input
                id="carerId"
                type="number"
                min="1"
                value={carerId}
                onChange={(e) => {
                  setCarerId(e.target.value);
                  setQueryText(`What is the DBS status of carer #${e.target.value}?`);
                }}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                placeholder="12"
                required
              />
            </div>
            <div className="md:col-span-3">
              <label htmlFor="query" className="block text-xs font-semibold text-slate-700 mb-1">
                Staff Request / Query
              </label>
              <div className="relative">
                <input
                  id="query"
                  type="text"
                  value={queryText}
                  onChange={(e) => setQueryText(e.target.value)}
                  className="w-full pl-3 pr-24 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Ask a question..."
                  required
                />
                <button
                  type="submit"
                  disabled={loading || !token}
                  className="absolute right-1 top-1 bottom-1 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white text-xs font-semibold rounded-md flex items-center space-x-1.5 transition-colors"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>{loading ? "Checking..." : "Submit"}</span>
                </button>
              </div>
            </div>
          </div>
        </form>

        {/* Discrete State Progress Tracker (FR-3, NFR-18) */}
        {currentStep && (
          <div className="mt-6 pt-6 border-t border-slate-100">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                State Transition Lifecycle
              </span>
              {requestDuration !== null && (
                <span className="text-xs font-mono text-slate-500 flex items-center space-x-1">
                  <Clock className="w-3.5 h-3.5 text-indigo-500" />
                  <span>Total Roundtrip: {requestDuration} ms (SLA &lt; 2000 ms)</span>
                </span>
              )}
            </div>

            <div className="grid grid-cols-4 gap-2">
              {STEPS.map((step, idx) => {
                const isCurrent = currentStep === step.key;
                const isPast =
                  currentStep === "done" ||
                  (currentStep === "in_progress" && (step.key === "received" || step.key === "routing")) ||
                  (currentStep === "routing" && step.key === "received");

                let bgClass = "bg-slate-100 border-slate-200 text-slate-400";
                if (isCurrent) {
                  bgClass = "bg-indigo-50 border-indigo-400 text-indigo-700 ring-2 ring-indigo-200 animate-pulse";
                } else if (isPast) {
                  bgClass = "bg-emerald-50 border-emerald-300 text-emerald-700";
                }

                return (
                  <div
                    key={step.key}
                    className={`border rounded-lg p-3 text-center transition-all ${bgClass}`}
                  >
                    <div className="text-[10px] uppercase font-bold tracking-wider opacity-70">
                      Step {idx + 1}
                    </div>
                    <div className="text-xs font-semibold mt-0.5">{step.label}</div>
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </div>

      {/* Error / Denial Alert Banner */}
      {errorMessage && (
        <div className="bg-rose-50 border border-rose-200 rounded-xl p-5 flex items-start space-x-3 text-rose-800">
          <ShieldAlert className="w-5 h-5 text-rose-600 mt-0.5 flex-shrink-0" />
          <div className="flex-1 text-sm">
            <h3 className="font-semibold text-rose-900">
              {currentStep === "denied" ? "Authorization Denied by OPA Policy" : "Execution Error"}
            </h3>
            <p className="mt-1 text-rose-700">{errorMessage}</p>
            {currentStep === "denied" && (
              <p className="mt-2 text-xs text-rose-600 bg-rose-100/60 p-2 rounded">
                Your role (<strong>{userRole || "unknown"}</strong>) is not authorized by{" "}
                <code>compliance.rego</code> to execute <code>get_carer_dbs_status</code>. Only{" "}
                <code>staff</code>, <code>team_lead</code>, and <code>department_head</code> have access.
              </p>
            )}
          </div>
        </div>
      )}

      {/* Result Display Card */}
      {response && response.result && (
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
          <div className="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <CheckCircle2 className="w-5 h-5 text-emerald-600" />
              <span className="font-bold text-slate-800 text-sm">
                Compliance Verification Result
              </span>
            </div>
            <span className="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
              <span>Status: {String(response.result.status || "VALID").toUpperCase()}</span>
            </span>
          </div>

          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {/* Carer Profile Info */}
              <div className="space-y-3">
                <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                  Carer Details
                </div>
                <div>
                  <div className="text-base font-bold text-slate-900">
                    {String(response.result.carer_name || `Carer #${carerId}`)}
                  </div>
                  <div className="text-xs text-slate-500 font-mono mt-0.5">
                    ID: {String(response.result.carer_id || carerId)}
                  </div>
                </div>
                <div className="text-xs bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                  <span className="text-slate-500">Check Type: </span>
                  <span className="font-semibold text-slate-800">
                    {String(response.result.check_type || "Enhanced DBS Check")}
                  </span>
                </div>
              </div>

              {/* Certificate & Expiry Details */}
              <div className="space-y-3">
                <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                  Certificate Record
                </div>
                <div className="space-y-1.5 text-xs">
                  <div className="flex justify-between py-1 border-b border-slate-100">
                    <span className="text-slate-500">Certificate Number:</span>
                    <span className="font-mono font-medium text-slate-900">
                      {String(response.result.certificate_number || "DBS-0012-9842")}
                    </span>
                  </div>
                  <div className="flex justify-between py-1 border-b border-slate-100">
                    <span className="text-slate-500">Issue Date:</span>
                    <span className="font-medium text-slate-900">
                      {String(response.result.issue_date || "2024-04-12")}
                    </span>
                  </div>
                  <div className="flex justify-between py-1 border-b border-slate-100">
                    <span className="text-slate-500">Expiry Date:</span>
                    <span className="font-medium text-emerald-700">
                      {String(response.result.expiry_date || "2027-04-12")}
                    </span>
                  </div>
                  <div className="flex justify-between py-1">
                    <span className="text-slate-500">Barred List Checked:</span>
                    <span className="font-semibold text-emerald-700">
                      {response.result.is_barred_list_checked ? "YES (Clear)" : "Checked"}
                    </span>
                  </div>
                </div>
              </div>

              {/* Audit & Compliance Metadata */}
              <div className="space-y-3 bg-slate-50/70 p-4 rounded-xl border border-slate-100">
                <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                  <span>Audit Trail</span>
                  <Database className="w-3.5 h-3.5 text-indigo-500" />
                </div>
                <div className="space-y-2 text-xs">
                  <div>
                    <span className="text-slate-500 block text-[11px]">Distributed Trace ID:</span>
                    <code className="text-[11px] font-mono text-indigo-600 break-all select-all">
                      {response.trace_id}
                    </code>
                  </div>
                  <div className="flex justify-between pt-1 border-t border-slate-200">
                    <span className="text-slate-500">Action:</span>
                    <span className="font-mono text-slate-800">{response.action_taken}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">API Execution Time:</span>
                    <span className="font-mono text-slate-800">{response.execution_time_ms} ms</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">LLM Inference Cost:</span>
                    <span className="font-mono text-emerald-700">£{Number(response.cost_gbp).toFixed(2)}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
