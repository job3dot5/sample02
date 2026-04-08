const backendUrl: string = import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:3000';

type RouteId = number | string;

interface Routes {
  health: string;
  images: string;
  imageById: (id: RouteId) => string;
  imageJobsById: (jobId: RouteId) => string;
}

export const routes: Routes = {
  health: `${backendUrl}/health`,
  images: `${backendUrl}/images`,
  imageById: (id: RouteId): string => `${backendUrl}/images/${id}`,
  imageJobsById: (jobId: RouteId): string => `${backendUrl}/image-jobs/${jobId}`
};
