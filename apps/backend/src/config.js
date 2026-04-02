import dotenv from 'dotenv';

dotenv.config();

const {
  PORT = '3000',
  API_BASE_URL = 'http://localhost',
  API_USERNAME,
  API_PASSWORD,
  FRONTEND_ORIGIN = 'http://localhost:5173',
  JWT_ISSUER = 'urn:sample02:api',
  JWT_AUDIENCE = 'urn:sample02:client'
} = process.env;

if (!API_USERNAME || !API_PASSWORD) {
  throw new Error('Missing API_USERNAME or API_PASSWORD in apps/backend/.env');
}

export const config = {
  PORT,
  API_BASE_URL,
  API_USERNAME,
  API_PASSWORD,
  FRONTEND_ORIGIN,
  JWT_ISSUER,
  JWT_AUDIENCE
};
