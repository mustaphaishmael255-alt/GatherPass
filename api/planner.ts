import type { VercelRequest, VercelResponse } from '@vercel/node';
import { 
  getAllEvents, 
  getEventById, 
  createEventRecord, 
  updateEventRecord, 
  getPlannerMetrics, 
  getEventAttendees, 
  getRecentAttendees,
  EventRecord 
} from '../lib/db.js';
import { logCloudWatchMetric } from '../lib/aws.js';

export default async function handler(req: VercelRequest, res: VercelResponse) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  const { id } = req.query;
  const eventId = id ? parseInt(id as string, 10) : null;

  try {
    // 1. GET Requests
    if (req.method === 'GET') {
      // Single event management query
      if (eventId && !isNaN(eventId)) {
        const event = await getEventById(eventId);
        if (!event) {
          return res.status(404).json({ success: false, error: 'Event not found' });
        }
        const attendees = await getEventAttendees(eventId);
        const ticketsSold = event.capacity - event.available_tickets;
        const revenue = attendees.reduce((sum, a) => sum + Number(a.total_paid || 0), 0);
        const checkIns = attendees.filter(a => a.status === 'used').reduce((sum, a) => sum + Number(a.quantity || 1), 0);

        return res.status(200).json({
          success: true,
          event,
          stats: {
            ticketsSold: Math.max(0, ticketsSold),
            ticketsRemaining: event.available_tickets,
            totalRevenue: Math.round(revenue * 100) / 100,
            checkIns
          },
          attendees
        });
      }

      // Organizer dashboard overview
      const metrics = await getPlannerMetrics();
      const events = await getAllEvents();
      const recentAttendees = await getRecentAttendees();

      return res.status(200).json({
        success: true,
        organizer: {
          name: 'Alex Danquah',
          email: 'alex@gatherpass.com',
          accountType: 'Demo Organizer Account',
          disclaimer: 'This organizer dashboard is a hackathon prototype using demo data.',
          verified: true
        },
        metrics: {
          ...metrics,
          activeEvents: (metrics as any).activeEvents ?? metrics.publishedEvents ?? metrics.totalEvents ?? 0
        },
        events,
        attendees: recentAttendees,
        recentAttendees
      });
    }

    // 2. POST Requests (Create Event / Save as Draft / Publish)
    if (req.method === 'POST') {
      const { 
        name, 
        description, 
        date, 
        time, 
        event_date, 
        venue, 
        base_ticket_price, 
        capacity, 
        picture, 
        planner_name, 
        status 
      } = req.body || {};

      if (!name || !venue || base_ticket_price === undefined) {
        return res.status(400).json({
          success: false,
          error: 'Event name, venue, and pass price are required.'
        });
      }

      // Format ISO event date
      let isoDate = event_date;
      if (!isoDate && date) {
        isoDate = time ? `${date}T${time}:00Z` : `${date}T18:00:00Z`;
      }
      if (!isoDate) {
        isoDate = new Date(Date.now() + 86400000 * 14).toISOString();
      }

      const price = parseFloat(base_ticket_price) || 0;
      const cap = parseInt(capacity, 10) || 100;
      const isPublished = status !== 'draft';

      const newEvent = await createEventRecord({
        name: name.trim(),
        description: (description || '').trim(),
        event_date: isoDate,
        venue: venue.trim(),
        base_ticket_price: price,
        capacity: cap,
        available_tickets: cap,
        picture: picture || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&q=80',
        planner_name: planner_name || 'Alex Danquah (Demo Organizer)',
        is_active: isPublished
      });

      // AWS CloudWatch audit
      await logCloudWatchMetric({
        action: 'TICKET_RESERVED',
        eventId: newEvent.id,
        timestamp: new Date().toISOString(),
        metadata: { action: isPublished ? 'EVENT_PUBLISHED' : 'EVENT_DRAFT_SAVED', event_name: newEvent.name }
      });

      return res.status(201).json({
        success: true,
        message: isPublished ? 'Event published successfully!' : 'Event saved as draft.',
        event: newEvent
      });
    }

    // 3. PUT / PATCH Requests (Edit Event & Toggle Publish / Unpublish)
    if (req.method === 'PUT' || req.method === 'PATCH') {
      if (!eventId || isNaN(eventId)) {
        return res.status(400).json({ success: false, error: 'Valid event ID is required' });
      }

      const existing = await getEventById(eventId);
      if (!existing) {
        return res.status(404).json({ success: false, error: 'Event not found' });
      }

      const body = req.body || {};
      const updates: Partial<EventRecord> = {};

      if (body.name !== undefined) updates.name = body.name.trim();
      if (body.description !== undefined) updates.description = body.description.trim();
      if (body.venue !== undefined) updates.venue = body.venue.trim();
      if (body.base_ticket_price !== undefined) updates.base_ticket_price = parseFloat(body.base_ticket_price);
      if (body.capacity !== undefined) updates.capacity = parseInt(body.capacity, 10);
      if (body.available_tickets !== undefined) updates.available_tickets = parseInt(body.available_tickets, 10);
      if (body.picture !== undefined) updates.picture = body.picture;
      if (body.is_active !== undefined) updates.is_active = Boolean(body.is_active);

      if (body.date || body.time || body.event_date) {
        if (body.event_date) {
          updates.event_date = body.event_date;
        } else if (body.date) {
          updates.event_date = body.time ? `${body.date}T${body.time}:00Z` : `${body.date}T18:00:00Z`;
        }
      }

      const updated = await updateEventRecord(eventId, updates);

      // AWS CloudWatch log
      await logCloudWatchMetric({
        action: 'TICKET_RESERVED',
        eventId,
        timestamp: new Date().toISOString(),
        metadata: { action: 'EVENT_UPDATED', updates }
      });

      return res.status(200).json({
        success: true,
        message: 'Event updated successfully.',
        event: updated
      });
    }

    return res.status(405).json({ success: false, error: 'Method not allowed' });
  } catch (error: any) {
    console.error('API /planner error:', error);
    return res.status(500).json({ success: false, error: 'Planner API processing error' });
  }
}
