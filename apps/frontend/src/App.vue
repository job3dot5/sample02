<script setup>
import { ref } from 'vue';
import TopMenu from './components/TopMenu.vue';
import UploadPanel from './components/UploadPanel.vue';
import ImageListPanel from './components/ImageListPanel.vue';

const activeView = ref('upload');
const listRefreshToken = ref(0);

function switchView(nextView) {
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
