<script setup lang="ts">
import TopMenu from './components/TopMenu.vue';
</script>

<template>
  <main class="container">
    <section class="card">
      <TopMenu />
      <RouterView />
    </section>
  </main>
</template>
