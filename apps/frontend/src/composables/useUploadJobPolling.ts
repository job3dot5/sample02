import { onUnmounted, ref } from 'vue';
import { routes } from '../config/routes';
import { createCancelableRequest } from './createCancelableRequest';

interface ApiErrorPayload {
  detail?: string;
  details?: string;
  title?: string;
  error?: string;
  message?: string;
}

interface UploadJobStatusPayload extends ApiErrorPayload {
  status?: string;
  image_id?: number | null;
}

interface ApiErrorMessageParams {
  status: number;
  payload: unknown;
  fallback: string;
}

type ApiErrorMessageResolver = (params: ApiErrorMessageParams) => string;

const STATUS_REFRESH_DEBOUNCE_MS = 300;
const JOB_STATUS_TIMEOUT_MS = 30_000;

export function useUploadJobPolling(getApiErrorMessage: ApiErrorMessageResolver) {
  const statusLoading = ref<boolean>(false);
  const uploadFinalMessage = ref<string>('');
  const uploadJobStatus = ref<string>('');
  const uploadedImageId = ref<number | null>(null);
  const uploadError = ref<string>('');

  let refreshTimer: ReturnType<typeof setTimeout> | null = null;
  let activePollRequestId = 0;
  let activePollingSessionId = 0;
  const { request: requestJobStatus, cancel: cancelJobStatusRequest } = createCancelableRequest();

  function stopPolling(): void {
    if (refreshTimer) {
      clearTimeout(refreshTimer);
      refreshTimer = null;
    }
    cancelJobStatusRequest();
    activePollingSessionId += 1;
    statusLoading.value = false;
  }

  function resetPollingState(): void {
    uploadFinalMessage.value = '';
    uploadJobStatus.value = '';
    uploadedImageId.value = null;
    uploadError.value = '';
  }

  function debouncePollJobStatus(currentJobId: string, sessionId: number): void {
    if (refreshTimer) {
      clearTimeout(refreshTimer);
    }

    refreshTimer = setTimeout(() => {
      void pollJobStatus(currentJobId, sessionId);
    }, STATUS_REFRESH_DEBOUNCE_MS);
  }

  function startPolling(currentJobId: string): void {
    stopPolling();
    const sessionId = ++activePollingSessionId;
    void pollJobStatus(currentJobId, sessionId);
  }

  async function pollJobStatus(currentJobId: string, sessionId: number): Promise<void> {
    if (sessionId !== activePollingSessionId) {
      return;
    }

    const requestId = ++activePollRequestId;
    let didTimeout = false;
    const timeoutTimer = setTimeout(() => {
      didTimeout = true;
      cancelJobStatusRequest();
    }, JOB_STATUS_TIMEOUT_MS);

    statusLoading.value = true;

    try {
      const response = await requestJobStatus(routes.imageJobsById(currentJobId));
      const payload = (await response.json()) as UploadJobStatusPayload;

      if (!response.ok) {
        throw new Error(
          getApiErrorMessage({
            status: response.status,
            payload,
            fallback: 'Statut du job indisponible.'
          })
        );
      }

      const status = payload.status || 'unknown';
      uploadJobStatus.value = status;
      uploadedImageId.value = payload.image_id ?? null;

      if (status === 'completed') {
        uploadFinalMessage.value = 'Upload termine.';
        stopPolling();
        return;
      }

      if (status === 'failed') {
        uploadFinalMessage.value = payload.error || 'Le traitement a echoue.';
        stopPolling();
        return;
      }

      debouncePollJobStatus(currentJobId, sessionId);
    } catch (err) {
      if (err instanceof DOMException && err.name === 'AbortError') {
        if (didTimeout && sessionId === activePollingSessionId) {
          uploadError.value = 'Le suivi du job a depasse 30 secondes.';
          stopPolling();
        }
        return;
      }
      uploadError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
      stopPolling();
    } finally {
      clearTimeout(timeoutTimer);
      if (requestId === activePollRequestId && sessionId === activePollingSessionId) {
        statusLoading.value = false;
      }
    }
  }

  onUnmounted(() => {
    stopPolling();
  });

  return {
    statusLoading,
    uploadFinalMessage,
    uploadJobStatus,
    uploadedImageId,
    uploadError,
    resetPollingState,
    stopPolling,
    startPolling
  };
}
