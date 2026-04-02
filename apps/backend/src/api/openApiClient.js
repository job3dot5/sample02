import https from 'https';
import { OpenAPIClientAxios } from 'openapi-client-axios';
import { config } from '../config.js';

let clientPromise = null;

function buildApiBaseUrl() {
  return config.API_BASE_URL.endsWith('/')
    ? config.API_BASE_URL.slice(0, -1)
    : config.API_BASE_URL;
}

function createHttpsAgent() {
  const shouldRejectUnauthorized =
    process.env.NODE_TLS_REJECT_UNAUTHORIZED !== '0';

  return new https.Agent({
    rejectUnauthorized: shouldRejectUnauthorized
  });
}

export async function getOpenApiClient() {
  if (!clientPromise) {
    const baseUrl = buildApiBaseUrl();
    const api = new OpenAPIClientAxios({
      definition: `${baseUrl}/docs/openapi.v1.yaml`,
      axiosConfigDefaults: {
        baseURL: baseUrl,
        httpsAgent: createHttpsAgent()
      }
    });

    clientPromise = api.init();
  }

  return clientPromise;
}
