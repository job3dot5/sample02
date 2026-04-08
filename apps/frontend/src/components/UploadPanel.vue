<script setup lang="ts">
import { onUnmounted, ref } from 'vue';
import { routes } from '../config/routes';
import { createCancelableRequest } from '../composables/createCancelableRequest';
import { useUploadJobPolling } from '../composables/useUploadJobPolling';

interface ApiErrorPayload {
  detail?: string;
  details?: string;
  title?: string;
  error?: string;
  message?: string;
}

interface UploadQueuePayload extends ApiErrorPayload {
  data?: {
    job_id?: string;
    status?: string;
  };
}

const selectedFile = ref<File | null>(null);
const isUploading = ref<boolean>(false);
const uploadAckMessage = ref<string>('');
const uploadJobId = ref<string>('');
let activeUploadRequestId = 0;
const { request: requestUploadImage, cancel: cancelUploadImageRequest } = createCancelableRequest();
const {
  statusLoading,
  uploadFinalMessage,
  uploadJobStatus,
  uploadedImageId,
  uploadError,
  resetPollingState,
  stopPolling,
  startPolling
} = useUploadJobPolling(getApiErrorMessage);

function getApiErrorMessage({
  status,
  payload,
  fallback
}: {
  status: number;
  payload: unknown;
  fallback: string;
}): string {
  if (status === 413) {
    return 'Fichier trop volumineux. Taille maximale: 2 Mo.';
  }

  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const errorPayload = payload as ApiErrorPayload;
  const rawMessage =
    errorPayload.detail ||
    errorPayload.details ||
    errorPayload.title ||
    errorPayload.error ||
    errorPayload.message ||
    fallback;

  if (typeof rawMessage !== 'string') {
    return fallback;
  }

  const isHtmlPayload = /<[^>]+>/.test(rawMessage);
  const cleanMessage = isHtmlPayload
    ? rawMessage
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
    : rawMessage.trim();

  return cleanMessage || fallback;
}

function resetUploadStatusView(): void {
  uploadAckMessage.value = '';
  uploadJobId.value = '';
  resetPollingState();
}

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement | null;
  const file = input?.files?.[0] ?? null;
  selectedFile.value = file;
}

async function uploadImage(): Promise<void> {
  if (!selectedFile.value) {
    uploadError.value = 'Merci de selectionner une image.';
    return;
  }

  const requestId = ++activeUploadRequestId;
  isUploading.value = true;
  uploadError.value = '';
  resetUploadStatusView();
  stopPolling();

  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);

    const response = await requestUploadImage(routes.images, {
      method: 'POST',
      body: formData
    });
    const payload = (await response.json()) as UploadQueuePayload;

    if (!response.ok) {
      throw new Error(
        getApiErrorMessage({
          status: response.status,
          payload,
          fallback: "Echec de l'envoi."
        })
      );
    }

    const queuedData = payload.data;

    if (!queuedData?.job_id) {
      throw new Error('La reponse ne contient pas de job_id.');
    }

    if (requestId !== activeUploadRequestId) {
      return;
    }

    uploadJobId.value = queuedData.job_id;
    uploadJobStatus.value = queuedData.status || 'queued';
    uploadAckMessage.value =
      "L'image a ete envoyee, vous pouvez fermer cette page ou attendre pour voir le suivi de l'upload.";

    startPolling(queuedData.job_id);
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') {
      return;
    }
    uploadError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
  } finally {
    if (requestId === activeUploadRequestId) {
      isUploading.value = false;
    }
  }
}

onUnmounted(() => {
  cancelUploadImageRequest();
  stopPolling();
});
</script>

<template>
  <section class="panel">
    <h1>Upload d'image</h1>
    <p class="subtitle">Envoyez une image puis suivez l'avancement du job en direct.</p>

    <form class="upload-form" @submit.prevent="uploadImage">
      <label for="imageFile" class="label">Selectionner une image</label>
      <input id="imageFile" type="file" accept="image/*" class="file-input" @change="onFileChange" />
      <button type="submit" :disabled="isUploading || !selectedFile">
        {{ isUploading ? 'Envoi...' : "Envoyer l'image" }}
      </button>
    </form>

    <p v-if="uploadAckMessage" class="info">{{ uploadAckMessage }}</p>
    <p v-if="uploadJobId" class="status">Job: <strong>{{ uploadJobId }}</strong></p>
    <p v-if="uploadJobStatus" class="status">
      Statut actuel: <strong>{{ uploadJobStatus }}</strong>
      <span v-if="statusLoading" class="dot" aria-hidden="true"></span>
    </p>
    <p v-if="uploadedImageId !== null" class="status">
      Image creee avec ID: <strong>{{ uploadedImageId }}</strong>
    </p>
    <p v-if="uploadFinalMessage" class="success">
      {{ uploadJobStatus === 'completed' ? 'Upload termine.' : uploadFinalMessage }}
    </p>
    <p v-if="uploadError" class="error">{{ uploadError }}</p>
  </section>
</template>
