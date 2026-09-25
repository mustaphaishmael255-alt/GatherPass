import type { VercelRequest, VercelResponse } from '@vercel/node';
import { getEventById, createTicketRecord } from '../lib/db.js';
import { logCloudWatchMetric, sendTicketViaSES } from '../lib/aws.js';
import crypto from 'crypto';

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
 const { event_id, buyer_name, buyer_email, buyer_phone, quantity } = req.body || {};

 const eventId = parseInt(event_id, 10);
 const qty = Math.max(1, Math.min(10, parseInt(quantity, 10) || 1));

 if (isNaN(eventId)) {
 return res.status(400).json({ success: false, error: 'Valid event_id is required' });
 }

 if (!buyer_name || typeof buyer_name !== 'string' || buyer_name.trim() === '') {
 return res.status(400).json({ success: false, error: 'Buyer name is required' });
 }

 if (!buyer_email || typeof buyer_email !== 'string' || !buyer_email.includes('@')) {
 return res.status(400).json({ success: false, error: 'Valid buyer email is required' });
 }

 const event = await getEventById(eventId);
 if (!event) {
 return res.status(404).json({ success: false, error: 'Event not found' });
 }

 if (event.available_tickets < qty) {
 return res.status(400).json({ success: false, error: 'Not enough tickets available for this event' });
 }

 // Server-side authoritative calculation
 const basePrice = Number(event.base_ticket_price);
 const platformFee = Math.round(basePrice * 0.05 * 100) / 100;
 const totalPaid = Math.round((basePrice + platformFee) * qty * 100) / 100;

 // Cryptographic collision-resistant token
 const token = crypto.randomBytes(16).toString('hex');

 const createdTicket = await createTicketRecord({
 event_id: eventId,
 buyer_name: buyer_name.trim(),
 buyer_email: buyer_email.trim().toLowerCase(),
 buyer_phone: (buyer_phone || '').trim(),
 quantity: qty,
 ticket_type: 'General Admission',
 ticket_price: basePrice,
 platform_fee: platformFee,
 total_paid: totalPaid,
 ticket_token: token,
 status: 'valid'
 });

 // AWS Cloud Integration: CloudWatch metrics + SES email notification
 await logCloudWatchMetric({
 action: 'TICKET_RESERVED',
 token,
 eventId,
 timestamp: new Date().toISOString(),
 metadata: { buyer_email, totalPaid, quantity: qty }
 });

 await sendTicketViaSES({
 toEmail: buyer_email,
 buyerName: buyer_name,
 eventName: event.name,
 ticketToken: token,
 totalPaid,
 qrPassUrl: `/ticket.html?token=${token}`
 });

 return res.status(200).json({
 success: true,
 token: token,
 aws_integration: {
 ses: '[PROTOTYPE] Amazon SES Email Dispatch',
 cloudwatch: '[PROTOTYPE] Amazon CloudWatch Metrics Logger'
 },
 ticket: {
 ...createdTicket,
 event_name: event.name,
 event_date: event.event_date,
 venue: event.venue
 }
 });
 } catch (error: any) {
 console.error('API /checkout error:', error);
 return res.status(500).json({ success: false, error: 'Failed to process checkout' });
 }
}
