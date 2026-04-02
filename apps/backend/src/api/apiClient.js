import { getOpenApiClient } from './openApiClient.js';
import { getBearerToken } from '../auth/tokenService.js';

function isObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function decodeBody(value) {
  if (!value) {
    return value;
  }

  if (Buffer.isBuffer(value)) {
    return value.toString('utf8');
  }

  if (value instanceof ArrayBuffer || ArrayBuffer.isView(value)) {
    return Buffer.from(value).toString('utf8');
  }

  return value;
}

function tryParseJson(value) {
  if (typeof value !== 'string') {
    return value;
  }

  const trimmed = value.trim();
  if (!trimmed) {
    return '';
  }

  try {
    return JSON.parse(trimmed);
  } catch {
    return value;
  }
}

function toErrorBody(response) {
  const responseBody = tryParseJson(decodeBody(response?.data));

  if (isObject(responseBody)) {
    return responseBody;
  }

  if (typeof responseBody === 'string' && !responseBody.trim()) {
    return {
      error: 'Upstream API error',
      details: response?.statusText || 'Upstream returned an empty error response'
    };
  }

  return {
    error: 'Upstream API error',
    details:
      typeof responseBody === 'string'
        ? responseBody
        : `Unexpected upstream error payload (${Object.prototype.toString.call(responseBody)})`
  };
}

async function invokeOperation(operationId, params, data, axiosConfig) {
  const client = await getOpenApiClient();
  const operation = client[operationId];

  if (typeof operation !== 'function') {
    throw new Error(`OpenAPI operation not found: ${operationId}`);
  }

  return operation(params, data, axiosConfig);
}

export async function proxyApiRequest(
  operationId,
  req,
  { binary = false, params = {}, data } = {}
) {
  const mergedParams = {
    ...req.query,
    ...params
  };

  const run = async (forceRefresh) => {
    const token = await getBearerToken({ forceRefresh });
    return invokeOperation(operationId, mergedParams, data, {
      headers: {
        Authorization: `Bearer ${token}`
      },
      responseType: binary ? 'arraybuffer' : 'json'
    });
  };

  let upstream;

  try {
    upstream = await run(false);
  } catch (error) {
    if (error?.response?.status === 401) {
      try {
        upstream = await run(true);
      } catch (retryError) {
        if (!retryError?.response) {
          return {
            status: 502,
            body: {
              error: 'Upstream API unreachable',
              details: retryError?.message ?? 'Unknown upstream connectivity error'
            }
          };
        }

        return {
          status: retryError?.response?.status ?? 500,
          body: toErrorBody(retryError?.response)
        };
      }
    } else {
      if (!error?.response) {
        return {
          status: 502,
          body: {
            error: 'Upstream API unreachable',
            details: error?.message ?? 'Unknown upstream connectivity error'
          }
        };
      }

      return {
        status: error?.response?.status ?? 500,
        body: toErrorBody(error?.response)
      };
    }
  }

  if (binary) {
    return {
      status: upstream.status ?? 200,
      binary: upstream.data,
      contentType:
        upstream.headers?.['content-type'] ?? 'application/octet-stream'
    };
  }

  return {
    status: upstream.status ?? 200,
    body: upstream.data
  };
}
