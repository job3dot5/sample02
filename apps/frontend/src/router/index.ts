import { createRouter, createWebHistory } from 'vue-router';
import type { AppView } from '../types/app';
import UploadPanel from '../components/UploadPanel.vue';
import ImageListPanel from '../components/ImageListPanel.vue';

const routes = [
  {
    path: '/',
    redirect: '/upload'
  },
  {
    path: '/upload',
    name: 'upload' satisfies AppView,
    component: UploadPanel
  },
  {
    path: '/list',
    name: 'list' satisfies AppView,
    component: ImageListPanel
  }
];

export const router = createRouter({
  history: createWebHistory(),
  routes
});
