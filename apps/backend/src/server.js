import cors from 'cors';
import express from 'express';
import { config } from './config.js';
import { apiRouter } from './routes/apiRoutes.js';

const app = express();
app.use(express.json());
app.use(
  cors({
    origin: config.FRONTEND_ORIGIN
  })
);
app.use(apiRouter);

app.listen(Number(config.PORT), () => {
  console.log(`Backend listening on http://localhost:${config.PORT}`);
});
