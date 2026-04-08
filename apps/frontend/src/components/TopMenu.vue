<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import type { AppView } from '../types/app';

const route = useRoute();

const currentView = computed<AppView | null>(() => {
  const routeName = route.name;
  return routeName === 'upload' || routeName === 'list' ? routeName : null;
});

const isUploadActive = computed(() => currentView.value === 'upload');
const isListActive = computed(() => currentView.value === 'list');
</script>

<template>
  <header class="top-menu">
    <RouterLink class="menu-button menu-link" :class="{ active: isUploadActive }" to="/upload">
      Upload image
    </RouterLink>
    <RouterLink class="menu-button menu-link" :class="{ active: isListActive }" to="/list">
      Lister images
    </RouterLink>
  </header>
</template>
