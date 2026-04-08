<script setup lang="ts">
import { ref } from 'vue';
import TopMenu from './components/TopMenu.vue';
import UploadPanel from './components/UploadPanel.vue';
import ImageListPanel from './components/ImageListPanel.vue';
import type { AppView } from './types/app';

const activeView = ref<AppView>('upload');
const listRefreshToken = ref<number>(0);

function switchView(nextView: AppView): void {
  activeView.value = nextView;

  if (nextView === 'list') {
    listRefreshToken.value += 1;
  }
}
</script>

<template>
  <main class="container">
    <section class="card">
      <TopMenu :active-view="activeView" @change-view="switchView" />
      <UploadPanel v-show="activeView === 'upload'" />
      <ImageListPanel v-show="activeView === 'list'" :refresh-token="listRefreshToken" />
    </section>
  </main>
</template>
