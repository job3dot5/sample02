<script setup>
defineProps({
  activeView: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['change-view']);

function changeView(view) {
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
