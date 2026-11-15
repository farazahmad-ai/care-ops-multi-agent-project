export interface RequestPayload {
  message: string;
  intent?: string;
  parameters?: Record<string, unknown>;
}

export type RequestStatus =
  | "received"
  | "routing"
  | "in_progress"
  | "awaiting_approval"
  | "done"
  | "denied"
  | "error";

export interface RequestResponse {
  trace_id: string;
  status: RequestStatus;
  action_taken: string;
  result: Record<string, unknown> | null;
  execution_time_ms: number;
  cost_gbp: number;
  error?: string | null;
}

export interface UserProfile {
  sub: string;
  email?: string;
  preferred_username?: string;
  name?: string;
  roles?: string[];
}
