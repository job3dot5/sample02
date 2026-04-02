<script setup>
import { onUnmounted, ref } from 'vue';
import { routes } from '../config/routes.js';

const selectedFile = ref(null);
const isUploading = ref(false);
const statusLoading = ref(false);
const uploadAckMessage = ref('');
const uploadFinalMessage = ref('');
const uploadJobStatus = ref('');
const uploadJobId = ref('');
const uploadedImageId = ref(null);
const uploadError = ref('');
let pollTimer = null;

function getApiErrorMessage({ status, payload, fallback }) {
  if (status === 413) {
    return 'Fichier trop volumineux. Taille maximale: 2 Mo.';
  }

  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const rawMessage =
    payload.detail ||
    payload.details ||
    payload.title ||
    payload.error ||
    payload.message ||
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

function resetUploadStatusView() {
  uploadAckMessage.value = '';
  uploadFinalMessage.value = '';
  uploadJobStatus.value = '';
  uploadJobId.value = '';
  uploadedImageId.value = null;
}

function stopPolling() {
  if (pollTimer) {
    clearTimeout(pollTimer);
    pollTimer = null;
  }
}

async function pollJobStatus(currentJobId) {
  statusLoading.value = true;

  try {
    const response = await fetch(routes.imageJobsById(currentJobId));
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(
        getApiErrorMessage({
          status: response.status,
          payload,
          fallback: 'Statut du job indisponible.'
        })
      );
    }

    const status = payload?.status || 'unknown';
    uploadJobStatus.value = status;
    uploadedImageId.value = payload?.image_id ?? null;

    if (status === 'completed') {
      uploadFinalMessage.value = 'Upload termine.';
      stopPolling();
      return;
    }

    if (status === 'failed') {
      uploadFinalMessage.value = payload?.error || 'Le traitement a echoue.';
      stopPolling();
      return;
    }

    pollTimer = setTimeout(() => {
      void pollJobStatus(currentJobId);
    }, 500);
  } catch (err) {
    uploadError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
    stopPolling();
  } finally {
    statusLoading.value = false;
  }
}

function onFileChange(event) {
  const [file] = event.target.files || [];
  selectedFile.value = file ?? null;
}

async function uploadImage() {
  if (!selectedFile.value) {
    uploadError.value = 'Merci de selectionner une image.';
    return;
  }

  isUploading.value = true;
  uploadError.value = '';
  resetUploadStatusView();
  stopPolling();

  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);

    const response = await fetch(routes.images, {
      method: 'POST',
      body: formData
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(
        getApiErrorMessage({
          status: response.status,
          payload,
          fallback: "Echec de l'envoi."
        })
      );
    }

    const queuedData = payload?.data;

    if (!queuedData?.job_id) {
      throw new Error('La reponse ne contient pas de job_id.');
    }

    uploadJobId.value = queuedData.job_id;
    uploadJobStatus.value = queuedData.status || 'queued';
    uploadAckMessage.value =
      "L'image a ete envoyee, vous pouvez fermer cette page ou attendre pour voir le suivi de l'upload.";

    void pollJobStatus(queuedData.job_id);
  } catch (err) {
    uploadError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
  } finally {
    isUploading.value = false;
  }
}

onUnmounted(() => {
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
