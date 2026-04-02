<script setup>
import { onMounted, ref, watch } from 'vue';
import ImageViewerModal from './ImageViewerModal.vue';
import { routes } from '../config/routes.js';

const props = defineProps({
  refreshToken: {
    type: Number,
    default: 0
  }
});

const images = ref([]);
const imagesMeta = ref(null);
const listPage = ref(1);
const isListLoading = ref(false);
const listError = ref('');

const selectedImageId = ref(null);

function prettyJson(value) {
  return JSON.stringify(value, null, 2);
}

function canGoPrevious() {
  return Boolean(imagesMeta.value?.page > 1);
}

function canGoNext() {
  return Boolean(imagesMeta.value?.page < imagesMeta.value?.total_pages);
}

async function loadImages(targetPage = 1) {
  isListLoading.value = true;
  listError.value = '';

  try {
    const response = await fetch(`${routes.images}?page=${targetPage}&per_page=5`);
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload?.detail || payload?.error || 'Impossible de charger les images.');
    }

    images.value = Array.isArray(payload?.data) ? payload.data : [];
    imagesMeta.value = payload?.meta ?? null;
    listPage.value = payload?.meta?.page ?? targetPage;
  } catch (err) {
    listError.value = err instanceof Error ? err.message : 'Erreur inconnue.';
  } finally {
    isListLoading.value = false;
  }
}

function showImage(imageId) {
  selectedImageId.value = imageId;
}

function closeImageModal() {
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
</script>

<template>
  <section class="panel">
    <h1>Liste des images</h1>
    <p class="subtitle">Pagination a 5 images par page.</p>

    <div class="list-toolbar">
      <button type="button" :disabled="isListLoading || !canGoPrevious()" @click="loadImages(listPage - 1)">
        Page precedente
      </button>
      <button type="button" :disabled="isListLoading" @click="loadImages(listPage)">
        Actualiser
      </button>
      <button type="button" :disabled="isListLoading || !canGoNext()" @click="loadImages(listPage + 1)">
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
