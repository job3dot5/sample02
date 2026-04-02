const backendUrl = import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:3000';

export const routes = {
  health: `${backendUrl}/health`,
  images: `${backendUrl}/images`,
  imageById: (id) => `${backendUrl}/images/${id}`,
  imageJobsById: (jobId) => `${backendUrl}/image-jobs/${jobId}`
};
