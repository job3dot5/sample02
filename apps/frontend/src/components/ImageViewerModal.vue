<script setup>
import { onUnmounted, ref, watch } from 'vue';
import { routes } from '../config/routes.js';

const props = defineProps({
  imageId: {
    type: Number,
    default: null
  }
});

const emit = defineEmits(['close']);

const isViewerLoading = ref(false);
const viewerError = ref('');
const viewerImageUrl = ref('');

function clearViewerImageUrl() {
  if (viewerImageUrl.value) {
    URL.revokeObjectURL(viewerImageUrl.value);
    viewerImageUrl.value = '';
  }
}

async function loadImage(id) {
  if (typeof id !== 'number') {
    return;
  }

  isViewerLoading.value = true;
  viewerError.value = '';
  clearViewerImageUrl();

  try {
    const response = await fetch(routes.imageById(id));

    if (!response.ok) {
      let errorMessage = "L'image n'a pas pu etre chargee.";
      try {
        const payload = await response.json();
        errorMessage = payload?.detail || payload?.error || errorMessage;
      } catch {
        const textPayload = await response.text();
        if (textPayload) {
          errorMessage = textPayload;
        }
      }
      throw new Error(errorMessage);
    }

    const blob = await response.blob();
    viewerImageUrl.value = URL.createObjectURL(blob);
  } catch (err) {
    viewerError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
  } finally {
    isViewerLoading.value = false;
  }
}

function close() {
  emit('close');
}

watch(
  () => props.imageId,
  (nextId) => {
    if (typeof nextId === 'number') {
      void loadImage(nextId);
    }
  },
  { immediate: true }
);

onUnmounted(() => {
  clearViewerImageUrl();
});
</script>

<template>
  <div class="modal-backdrop" @click.self="close">
    <section class="modal">
      <header class="modal-header">
        <h2>Image #{{ props.imageId }}</h2>
        <button type="button" class="close-button" @click="close">Fermer</button>
      </header>

      <div class="modal-content">
        <div v-if="isViewerLoading" class="loader-wrap">
          <span class="spinner" aria-hidden="true"></span>
          <p>Chargement de l'image...</p>
        </div>

        <p v-else-if="viewerError" class="error">{{ viewerError }}</p>
        <img v-else-if="viewerImageUrl" :src="viewerImageUrl" alt="Apercu image" class="modal-image" />
      </div>
    </section>
  </div>
</template>
