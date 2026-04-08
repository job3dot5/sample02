<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import ImageViewerModal from './ImageViewerModal.vue';
import { routes } from '../config/routes';
import { createCancelableRequest } from '../composables/createCancelableRequest';

interface ImageItem {
  id: number;
  [key: string]: unknown;
}

interface ImagesMeta {
  page: number;
  total_pages: number;
  total: number;
}

interface ImagesListPayload {
  data?: unknown;
  meta?: Partial<ImagesMeta>;
  detail?: string;
  error?: string;
}

const props = withDefaults(
  defineProps<{
    refreshToken?: number;
  }>(),
  {
    refreshToken: 0
  }
);

const images = ref<ImageItem[]>([]);
const imagesMeta = ref<ImagesMeta | null>(null);
const listPage = ref<number>(1);
const isListLoading = ref<boolean>(false);
const listError = ref<string>('');
let activeRequestId = 0;
const { request: requestImages, cancel: cancelImagesRequest } = createCancelableRequest();

const selectedImageId = ref<number | null>(null);

function prettyJson(value: unknown): string {
  return JSON.stringify(value, null, 2);
}

const canGoPrevious = computed<boolean>(() => {
  const page = imagesMeta.value?.page ?? 1;
  return page > 1;
});

const canGoNext = computed<boolean>(() => {
  const page = imagesMeta.value?.page ?? 1;
  const totalPages = imagesMeta.value?.total_pages ?? 1;
  return page < totalPages;
});

async function loadImages(targetPage = 1): Promise<void> {
  const requestId = ++activeRequestId;
  isListLoading.value = true;
  listError.value = '';

  try {
    const response = await requestImages(`${routes.images}?page=${targetPage}&per_page=5`);
    const payload = (await response.json()) as ImagesListPayload;

    if (!response.ok) {
      throw new Error(payload.detail || payload.error || 'Impossible de charger les images.');
    }

    images.value = Array.isArray(payload.data) ? (payload.data as ImageItem[]) : [];
    imagesMeta.value = payload.meta
      ? {
          page: payload.meta.page ?? targetPage,
          total_pages: payload.meta.total_pages ?? 1,
          total: payload.meta.total ?? images.value.length
        }
      : null;
    listPage.value = imagesMeta.value?.page ?? targetPage;
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') {
      return;
    }
    listError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
  } finally {
    if (requestId === activeRequestId) {
      isListLoading.value = false;
    }
  }
}

function showImage(imageId: number): void {
  selectedImageId.value = imageId;
}

function closeImageModal(): void {
  selectedImageId.value = null;
}

onMounted(() => {
  void loadImages();
});

watch(
  () => props.refreshToken,
  () => {
    void loadImages(listPage.value);
  }
);

onUnmounted(() => {
  cancelImagesRequest();
});
</script>

<template>
  <section class="panel">
    <h1>Liste des images</h1>
    <p class="subtitle">Pagination a 5 images par page.</p>

    <div class="list-toolbar">
      <button type="button" :disabled="isListLoading || !canGoPrevious" @click="loadImages(listPage - 1)">
        Page precedente
      </button>
      <button type="button" :disabled="isListLoading" @click="loadImages(listPage)">
        Actualiser
      </button>
      <button type="button" :disabled="isListLoading || !canGoNext" @click="loadImages(listPage + 1)">
        Page suivante
      </button>
    </div>

    <p v-if="imagesMeta" class="status">
      Page <strong>{{ imagesMeta.page }}</strong> / <strong>{{ imagesMeta.total_pages }}</strong> -
      {{ imagesMeta.total }} images
    </p>
    <p v-if="isListLoading" class="status">Chargement de la liste...</p>
    <p v-if="listError" class="error">{{ listError }}</p>

    <div v-if="images.length" class="image-list">
      <article v-for="image in images" :key="image.id" class="image-row">
        <div class="image-row-header">
          <strong>Image #{{ image.id }}</strong>
          <button type="button" @click="showImage(image.id)">Afficher l'image</button>
        </div>

        <details>
          <summary>Voir le détail</summary>
          <pre class="json-block">{{ prettyJson(image) }}</pre>
        </details>
      </article>
    </div>

    <p v-else-if="!isListLoading && !listError" class="status">Aucune image sur cette page.</p>
    <ImageViewerModal v-if="selectedImageId !== null" :image-id="selectedImageId" @close="closeImageModal" />
  </section>
</template>
