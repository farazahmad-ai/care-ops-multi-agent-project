import { AuthProviderProps } from "react-oidc-context";
import { User } from "oidc-client-ts";

export const KEYCLOAK_URL = import.meta.env.VITE_KEYCLOAK_URL || "http://localhost:8081";
export const REALM = import.meta.env.VITE_KEYCLOAK_REALM || "careops";
export const CLIENT_ID = import.meta.env.VITE_KEYCLOAK_CLIENT_ID || "careops-frontend";
export const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000";

export const oidcConfig: AuthProviderProps = {
  authority: `${KEYCLOAK_URL}/realms/${REALM}`,
  client_id: CLIENT_ID,
  redirect_uri: window.location.origin,
  response_type: "code",
  scope: "openid profile email roles",
  onSigninCallback: () => {
    // Remove oauth state and code from URL search parameters after login
    window.history.replaceState({}, document.title, window.location.pathname);
  },
};

/**
 * Extracts realm roles from decoded JWT payload.
 */
export function extractRoles(user: User | null): string[] {
  if (!user || !user.profile) return [];
  const profile = user.profile as Record<string, unknown>;
  const realmAccess = profile.realm_access as { roles?: string[] } | undefined;
  return realmAccess?.roles || [];
}

/**
 * Direct token grant helper for fast test-user switching without Keycloak redirect.
 */
export async function loginWithDirectGrant(
  username: string,
  password: string
): Promise<{ accessToken: string; userProfile: Record<string, unknown> }> {
  const tokenEndpoint = `${KEYCLOAK_URL}/realms/${REALM}/protocol/openid-connect/token`;
  const body = new URLSearchParams({
    client_id: CLIENT_ID,
    grant_type: "password",
    username,
    password,
    scope: "openid profile email roles",
  });

  const response = await fetch(tokenEndpoint, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
  });

  if (!response.ok) {
    const errText = await response.text();
    throw new Error(`Direct login failed (${response.status}): ${errText}`);
  }

  const data = await response.json();
  const token = data.access_token;

  // Decode JWT payload (base64url)
  const base64Url = token.split(".")[1];
  const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
  const jsonPayload = decodeURIComponent(
    atob(base64)
      .split("")
      .map((c) => "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2))
      .join("")
  );
  const userProfile = JSON.parse(jsonPayload);

  return { accessToken: token, userProfile };
}
