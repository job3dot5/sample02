import { config } from '../config.js';
import { getOpenApiClient } from '../api/openApiClient.js';

let cachedToken = null;
let cachedTokenExpMs = 0;

function parseJwtPayload(token) {
  try {
    const [, payloadPart] = token.split('.');
    if (!payloadPart) {
      return null;
    }

    return JSON.parse(Buffer.from(payloadPart, 'base64url').toString('utf8'));
  } catch {
    return null;
  }
}

function parseJwtExpMs(payload) {
  return typeof payload?.exp === 'number' ? payload.exp * 1000 : 0;
}

function isAudienceValid(audience) {
  if (typeof audience === 'string') {
    return audience === config.JWT_AUDIENCE;
  }

  if (Array.isArray(audience)) {
    return audience.includes(config.JWT_AUDIENCE);
  }

  return false;
}

function validateJwtClaims(payload) {
  if (!payload || typeof payload !== 'object') {
    throw new Error('Invalid JWT payload format');
  }

  if (payload.iss !== config.JWT_ISSUER) {
    throw new Error(
      `Invalid token issuer: expected "${config.JWT_ISSUER}", got "${payload.iss ?? 'missing'}"`
    );
  }

  if (!isAudienceValid(payload.aud)) {
    const gotAudience = Array.isArray(payload.aud)
      ? payload.aud.join(',')
      : payload.aud ?? 'missing';
    throw new Error(
      `Invalid token audience: expected "${config.JWT_AUDIENCE}", got "${gotAudience}"`
    );
  }
}

function isTokenUsable() {
  if (!cachedToken || !cachedTokenExpMs) {
    return false;
  }

  const safetyWindowMs = 10_000;
  return Date.now() + safetyWindowMs < cachedTokenExpMs;
}

async function fetchTokenFromApi() {
  const client = await getOpenApiClient();
  const response = await client.login(undefined, {
    username: config.API_USERNAME,
    password: config.API_PASSWORD
  });

  const payload = response.data ?? {};
  const token = payload.token ?? payload.access_token;

  if (!token) {
    throw new Error('No token found in login response payload');
  }

  const jwtPayload = parseJwtPayload(token);
  validateJwtClaims(jwtPayload);

  cachedToken = token;
  cachedTokenExpMs = parseJwtExpMs(jwtPayload) || Date.now() + 60_000;
  return token;
}

export async function getBearerToken({ forceRefresh = false } = {}) {
  if (!forceRefresh && isTokenUsable()) {
    return cachedToken;
  }

  return fetchTokenFromApi();
}
