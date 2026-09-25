import type { VercelRequest, VercelResponse } from '@vercel/node';
import { getTicketByToken } from '../lib/db.js';

export default async function handler(req: VercelRequest, res: VercelResponse) {
 res.setHeader('Access-Control-Allow-Origin', '*');
 res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
 res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

 if (req.method === 'OPTIONS') {
 return res.status(200).end();
 }

 try {
 const { token } = req.query;

 if (!token || typeof token !== 'string') {
 return res.status(400).json({ success: false, error: 'Ticket token is required' });
 }

 const ticket = await getTicketByToken(token.trim());
 if (!ticket) {
 return res.status(404).json({ success: false, error: 'Ticket not found' });
 }

 return res.status(200).json({
 success: true,
 ticket
 });
 } catch (error: any) {
 console.error('API /ticket error:', error);
 return res.status(500).json({ success: false, error: 'Internal server error' });
 }
}
