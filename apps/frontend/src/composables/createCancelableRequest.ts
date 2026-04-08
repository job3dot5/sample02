export interface CancelableRequest {
  request: (input: RequestInfo | URL, options?: RequestInit) => Promise<Response>;
  cancel: () => void;
}

export function createCancelableRequest(): CancelableRequest {
  let controller: AbortController | null = null;

  async function request(input: RequestInfo | URL, options: RequestInit = {}): Promise<Response> {
    if (controller) {
      controller.abort();
    }

    controller = new AbortController();

    return fetch(input, {
      ...options,
      signal: controller.signal
    });
  }

  function cancel(): void {
    if (controller) {
      controller.abort();
      controller = null;
    }
  }

  return {
    request,
    cancel
  };
}
