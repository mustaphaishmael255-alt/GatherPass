import type { VercelRequest, VercelResponse } from '@vercel/node';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { uploadEventFlyerToS3, isAWSConfigured } from '../lib/aws.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const UPLOADS_DIR = path.join(__dirname, '..', 'public', 'uploads');

export default async function handler(req: VercelRequest, res: VercelResponse) {
 res.setHeader('Access-Control-Allow-Origin', '*');
 res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
 res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

 if (req.method === 'OPTIONS') {
 return res.status(200).end();
 }

 if (req.method !== 'POST') {
 return res.status(405).json({ success: false, error: 'Method not allowed' });
 }

 try {
 const { image, filename, contentType } = req.body || {};

 if (!image) {
 return res.status(400).json({ success: false, error: 'No image data provided.' });
 }

 // Extract base64 payload
 const matches = image.match(/^data:([A-Za-z-+\/]+);base64,(.+)$/);
 let mime = contentType || 'image/jpeg';
 let buffer: Buffer;

 if (matches && matches.length === 3) {
 mime = matches[1];
 buffer = Buffer.from(matches[2], 'base64');
 } else {
 buffer = Buffer.from(image, 'base64');
 }

 const ext = mime.includes('png') ? '.png' : mime.includes('webp') ? '.webp' : '.jpg';
 const safeName = `banner-${Date.now()}-${Math.random().toString(36).substring(2, 8)}${ext}`;

 // Cloud AWS S3 check
 if (isAWSConfigured()) {
 const s3Result = await uploadEventFlyerToS3(safeName, buffer, mime);
 return res.status(200).json({
 success: true,
 url: s3Result.url,
 source: 'Amazon S3 [LIVE]'
 });
 }

 // Local / Serverless storage write
 if (!fs.existsSync(UPLOADS_DIR)) {
 fs.mkdirSync(UPLOADS_DIR, { recursive: true });
 }

 const localPath = path.join(UPLOADS_DIR, safeName);
 fs.writeFileSync(localPath, buffer);

 const publicUrl = `/uploads/${safeName}`;

 return res.status(200).json({
 success: true,
 url: publicUrl,
 source: 'Local Storage [PROTOTYPE]'
 });
 } catch (error: any) {
 console.error('API /upload error:', error);
 return res.status(500).json({ success: false, error: 'Image upload failed' });
 }
}
