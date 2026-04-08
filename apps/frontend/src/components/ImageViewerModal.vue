<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { routes } from '../config/routes';

interface ImageErrorPayload {
  detail?: string;
  error?: string;
}

const props = withDefaults(
  defineProps<{
    imageId: number | null;
  }>(),
  {
    imageId: null
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const isViewerLoading = ref<boolean>(false);
const viewerError = ref<string>('');
const viewerImageUrl = ref<string>('');

function clearViewerImageUrl(): void {
  if (viewerImageUrl.value) {
    URL.revokeObjectURL(viewerImageUrl.value);
    viewerImageUrl.value = '';
  }
}

async function loadImage(id: number): Promise<void> {
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
        const payload = (await response.json()) as ImageErrorPayload;
        errorMessage = payload.detail || payload.error || errorMessage;
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

function close(): void {
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
