import http from 'http';
import fs from 'fs';
import path from 'path';
import url from 'url';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Import API handlers
import eventsHandler from './api/events.ts';
import checkoutHandler from './api/checkout.ts';
import ticketHandler from './api/ticket.ts';
import verifyHandler from './api/verify.ts';
import plannerHandler from './api/planner.ts';
import uploadHandler from './api/upload.ts';

const PORT = process.env.PORT || 3000;
const PUBLIC_DIR = path.join(__dirname, 'public');

const MIME_TYPES = {
 '.html': 'text/html; charset=utf-8',
 '.css': 'text/css; charset=utf-8',
 '.js': 'application/javascript; charset=utf-8',
 '.json': 'application/json; charset=utf-8',
 '.png': 'image/png',
 '.jpg': 'image/jpeg',
 '.svg': 'image/svg+xml',
 '.ico': 'image/x-icon'
};

function enhanceResponse(res) {
 res.status = function(statusCode) {
 res.statusCode = statusCode;
 return res;
 };
 res.json = function(data) {
 res.setHeader('Content-Type', 'application/json; charset=utf-8');
 res.end(JSON.stringify(data));
 return res;
 };
}

const server = http.createServer(async (req, res) => {
 enhanceResponse(res);
 const parsedUrl = url.parse(req.url, true);
 const pathname = parsedUrl.pathname;

 // Enable CORS
 res.setHeader('Access-Control-Allow-Origin', '*');
 res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, OPTIONS');
 res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

 if (req.method === 'OPTIONS') {
 res.statusCode = 200;
 res.end();
 return;
 }

 // Parse Body for POST / PUT / PATCH requests
 let body = {};
 if (req.method === 'POST' || req.method === 'PUT' || req.method === 'PATCH') {
 try {
 const chunks = [];
 for await (const chunk of req) {
 chunks.push(chunk);
 }
 const raw = Buffer.concat(chunks).toString();
 if (raw) {
 body = JSON.parse(raw);
 }
 } catch (e) {
 body = {};
 }
 }

 req.query = parsedUrl.query;
 req.body = body;

 // Route API requests
 if (pathname.startsWith('/api/')) {
 try {
 if (pathname === '/api/events' || pathname === '/api/events/') {
 await eventsHandler(req, res);
 } else if (pathname === '/api/checkout' || pathname === '/api/checkout/') {
 await checkoutHandler(req, res);
 } else if (pathname === '/api/ticket' || pathname === '/api/ticket/') {
 await ticketHandler(req, res);
 } else if (pathname === '/api/verify' || pathname === '/api/verify/') {
 await verifyHandler(req, res);
 } else if (pathname === '/api/planner' || pathname === '/api/planner/') {
 await plannerHandler(req, res);
 } else if (pathname === '/api/upload' || pathname === '/api/upload/') {
 await uploadHandler(req, res);
 } else {
 res.status(404).json({ error: 'API endpoint not found' });
 }
 } catch (err) {
 console.error('API Error:', err);
 res.status(500).json({ error: 'Internal Server Error' });
 }
 return;
 }

 // Rewrite / Routing for Planner Pages
 let filePath = '';
 if (pathname === '/planner' || pathname === '/planner/') {
 filePath = path.join(PUBLIC_DIR, 'planner', 'index.html');
 } else if (pathname === '/planner/create' || pathname === '/planner/create/') {
 filePath = path.join(PUBLIC_DIR, 'planner', 'create.html');
 } else if (pathname.startsWith('/planner/events/')) {
 filePath = path.join(PUBLIC_DIR, 'planner', 'event-manage.html');
 } else {
 filePath = path.join(PUBLIC_DIR, pathname === '/' ? 'index.html' : pathname);
 // Clean URL rewrites (e.g. /event -> /event.html)
 if (!path.extname(filePath)) {
 if (fs.existsSync(filePath + '.html')) {
 filePath += '.html';
 }
 }
 }

 if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
 const ext = path.extname(filePath).toLowerCase();
 const contentType = MIME_TYPES[ext] || 'application/octet-stream';
 res.writeHead(200, { 'Content-Type': contentType });
 fs.createReadStream(filePath).pipe(res);
 } else {
 const indexPath = path.join(PUBLIC_DIR, 'index.html');
 if (fs.existsSync(indexPath)) {
 res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
 fs.createReadStream(indexPath).pipe(res);
 } else {
 res.writeHead(404, { 'Content-Type': 'text/plain' });
 res.end('Not Found');
 }
 }
});

server.listen(PORT, () => {
 console.log(`\n=================================================`);
 console.log(` GatherPass is running!`);
 console.log(` http://localhost:${PORT}`);
 console.log(` Planner: http://localhost:${PORT}/planner`);
 console.log(`=================================================\n`);
});
