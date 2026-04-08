<script setup lang="ts">
import type { AppView } from '../types/app';

defineProps<{
  activeView: AppView;
}>();

const emit = defineEmits<{
  (e: 'change-view', view: AppView): void;
}>();

function changeView(view: AppView): void {
  emit('change-view', view);
}
</script>

<template>
  <header class="top-menu">
    <button
      class="menu-button"
      :class="{ active: activeView === 'upload' }"
      type="button"
      @click="changeView('upload')"
    >
      Upload image
    </button>
    <button
      class="menu-button"
      :class="{ active: activeView === 'list' }"
      type="button"
      @click="changeView('list')"
    >
      Lister images
    </button>
  </header>
</template>
