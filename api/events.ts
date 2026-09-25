import type { VercelRequest, VercelResponse } from '@vercel/node';
import { getActiveEvents, getEventById, EventRecord } from '../lib/db.js';

export default async function handler(req: VercelRequest, res: VercelResponse) {
 res.setHeader('Access-Control-Allow-Origin', '*');
 res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
 res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

 if (req.method === 'OPTIONS') {
 return res.status(200).end();
 }

 try {
 const { id, search } = req.query;

 if (id) {
 const eventId = parseInt(id as string, 10);
 if (isNaN(eventId)) {
 return res.status(400).json({ success: false, error: 'Invalid event ID' });
 }

 const event = await getEventById(eventId);
 if (!event) {
 return res.status(404).json({ success: false, error: 'Event not found' });
 }

 const basePrice = Number(event.base_ticket_price);
 const platformFee = Math.round(basePrice * 0.05 * 100) / 100;
 const totalUnit = basePrice + platformFee;

 return res.status(200).json({
 success: true,
 event: {
 ...event,
 base_ticket_price: basePrice,
 platform_fee: platformFee,
 total_price: totalUnit
 }
 });
 }

 const events = await getActiveEvents(search as string);
 const enrichedEvents = events.map((ev: EventRecord) => {
 const base = Number(ev.base_ticket_price);
 const fee = Math.round(base * 0.05 * 100) / 100;
 return {
 ...ev,
 base_ticket_price: base,
 platform_fee: fee,
 total_price: base + fee
 };
 });

 return res.status(200).json({
 success: true,
 count: enrichedEvents.length,
 events: enrichedEvents
 });
 } catch (error: any) {
 console.error('API /events error:', error);
 return res.status(500).json({ success: false, error: 'Internal server error' });
 }
}
