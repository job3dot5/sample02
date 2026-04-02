import express from 'express';
import multer from 'multer';
import { proxyApiRequest } from '../api/apiClient.js';

export const apiRouter = express.Router();
const upload = multer({ storage: multer.memoryStorage() });

apiRouter.get('/health', async (req, res) => {
  try {
    const result = await proxyApiRequest('getHealth', req);
    if (result.body) {
      return res.status(result.status).json(result.body);
    }

    return res
      .status(502)
      .json({ error: 'Unexpected response shape from upstream health route' });
  } catch (error) {
    return res.status(500).json({
      error: 'Backend health request failed',
      details: error instanceof Error ? error.message : 'Unknown error'
    });
  }
});

apiRouter.get('/images/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const result = await proxyApiRequest('renderImage', req, {
      params: { id: Number(id) },
      binary: true
    });

    if (result.body) {
      return res.status(result.status).json(result.body);
    }

    return res
      .status(result.status)
      .type(result.contentType)
      .send(Buffer.from(result.binary));
  } catch (error) {
    return res.status(500).json({
      error: 'Image proxy request failed',
      details: error instanceof Error ? error.message : 'Unknown error'
    });
  }
});

apiRouter.get('/images', async (req, res) => {
  try {
    const result = await proxyApiRequest('listImages', req);
    if (result.body) {
      return res.status(result.status).json(result.body);
    }

    return res
      .status(502)
      .json({ error: 'Unexpected response shape from upstream images route' });
  } catch (error) {
    return res.status(500).json({
      error: 'Images list request failed',
      details: error instanceof Error ? error.message : 'Unknown error'
    });
  }
});

apiRouter.post('/images', upload.single('file'), async (req, res) => {
  try {
    if (!req.file) {
      return res
        .status(400)
        .json({ error: 'Missing file field in multipart/form-data payload' });
    }

    const formData = new FormData();
    formData.append(
      'file',
      new Blob([req.file.buffer], {
        type: req.file.mimetype || 'application/octet-stream'
      }),
      req.file.originalname || 'upload.bin'
    );

    const result = await proxyApiRequest('uploadImage', req, {
      data: formData
    });

    if (result.body) {
      return res.status(result.status).json(result.body);
    }

    return res
      .status(502)
      .json({ error: 'Unexpected response shape from upstream upload route' });
  } catch (error) {
    return res.status(500).json({
      error: 'Image upload request failed',
      details: error instanceof Error ? error.message : 'Unknown error'
    });
  }
});

apiRouter.get('/image-jobs/:job_id', async (req, res) => {
  try {
    const { job_id } = req.params;
    const result = await proxyApiRequest('getImageJobStatus', req, {
      params: { job_id }
    });

    if (result.body) {
      return res.status(result.status).json(result.body);
    }

    return res
      .status(502)
      .json({ error: 'Unexpected response shape from upstream image jobs route' });
  } catch (error) {
    return res.status(500).json({
      error: 'Image jobs status request failed',
      details: error instanceof Error ? error.message : 'Unknown error'
    });
  }
});
