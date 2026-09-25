import { createClient, SupabaseClient } from '@supabase/supabase-js';

export interface EventRecord {
  id: number;
  name: string;
  description: string;
  event_date: string;
  venue: string;
  base_ticket_price: number;
  capacity: number;
  available_tickets: number;
  picture: string;
  planner_name?: string;
  is_active: boolean;
  created_at?: string;
}

export interface TicketRecord {
  id?: number;
  event_id: number;
  buyer_name: string;
  buyer_email: string;
  buyer_phone: string;
  quantity: number;
  ticket_type: string;
  ticket_price: number;
  platform_fee: number;
  total_paid: number;
  ticket_token: string;
  status: 'valid' | 'used' | 'cancelled';
  created_at?: string;
  updated_at?: string;
  event_name?: string;
  event_date?: string;
  venue?: string;
  planner_name?: string;
}

// In-memory fallback dataset - Clean Initial State (Tickets Sold: 0, Tickets Remaining: Capacity, Revenue: GH₵0.00)
let mockEvents: EventRecord[] = [
  {
    id: 1,
    name: 'Accra Tech Summit 2026',
    description: 'The premier West African technology conference bringing together founders, investors, and engineers for keynote talks, product demos, and unmatched networking.',
    event_date: '2026-11-15T09:00:00Z',
    venue: 'Grand Arena, Accra International Conference Centre',
    base_ticket_price: 150.00,
    capacity: 500,
    available_tickets: 500,
    picture: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&q=80',
    planner_name: 'Tech Summit Africa',
    is_active: true
  },
  {
    id: 2,
    name: 'Afrobeats & Vibes Festival',
    description: 'An outdoor celebration of African music, culinary arts, fashion, and culture. Live headline sets from top artists and world-class sound design.',
    event_date: '2026-12-05T16:00:00Z',
    venue: 'Laboma Beachfront, Accra',
    base_ticket_price: 80.00,
    capacity: 1500,
    available_tickets: 1500,
    picture: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=1200&q=80',
    planner_name: 'Live Nation West Africa',
    is_active: true
  },
  {
    id: 3,
    name: 'Founder Breakfast & Networking',
    description: 'An exclusive morning roundtable for startup founders, angel investors, and venture builders. High-impact conversations over artisan coffee.',
    event_date: '2026-10-20T07:30:00Z',
    venue: 'Impact Hub Osu, Accra',
    base_ticket_price: 45.00,
    capacity: 60,
    available_tickets: 60,
    picture: 'https://images.unsplash.com/photo-1515187029135-18ee286d815b?w=1200&q=80',
    planner_name: 'GatherPass Community',
    is_active: true
  }
];

let mockTickets: TicketRecord[] = [];

// Initialize Supabase if credentials exist
const supabaseUrl = process.env.SUPABASE_URL || process.env.NEXT_PUBLIC_SUPABASE_URL || '';
const supabaseKey = process.env.SUPABASE_SERVICE_ROLE_KEY || process.env.SUPABASE_ANON_KEY || process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY || '';

export const supabase: SupabaseClient | null = (supabaseUrl && supabaseKey) 
  ? createClient(supabaseUrl, supabaseKey) 
  : null;

export const isSupabaseConfigured = () => Boolean(supabase);

// Data Access Layer

export async function getActiveEvents(searchQuery?: string): Promise<EventRecord[]> {
  if (supabase) {
    let query = supabase.from('events').select('*').eq('is_active', true);
    if (searchQuery && searchQuery.trim() !== '') {
      const q = searchQuery.trim();
      query = query.or(`name.ilike.%${q}%,description.ilike.%${q}%,venue.ilike.%${q}%`);
    }
    const { data, error } = await query.order('event_date', { ascending: true });
    if (!error && data && data.length > 0) {
      return data as EventRecord[];
    }
  }

  // Fallback to in-memory store
  if (searchQuery && searchQuery.trim() !== '') {
    const q = searchQuery.toLowerCase();
    return mockEvents.filter(e => e.is_active && 
      (e.name.toLowerCase().includes(q) || e.description.toLowerCase().includes(q) || e.venue.toLowerCase().includes(q))
    );
  }
  return mockEvents.filter(e => e.is_active);
}

export async function getEventById(id: number): Promise<EventRecord | null> {
  if (supabase) {
    const { data, error } = await supabase.from('events').select('*').eq('id', id).single();
    if (!error && data) {
      return data as EventRecord;
    }
  }
  return mockEvents.find(e => e.id === id && e.is_active) || null;
}

export async function createTicketRecord(ticket: TicketRecord): Promise<TicketRecord> {
  if (supabase) {
    const { data, error } = await supabase.from('tickets').insert([ticket]).select().single();
    if (!error && data) {
      // Decrement available tickets in Supabase
      const event = await getEventById(ticket.event_id);
      if (event) {
        const remaining = Math.max(0, event.available_tickets - ticket.quantity);
        await supabase.from('events').update({ available_tickets: remaining }).eq('id', ticket.event_id);
      }
      return data as TicketRecord;
    }
  }

  // Fallback in-memory
  const newTicket = { ...ticket, id: mockTickets.length + 1, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
  mockTickets.push(newTicket);
  
  const event = mockEvents.find(e => e.id === ticket.event_id);
  if (event) {
    event.available_tickets = Math.max(0, event.available_tickets - ticket.quantity);
  }

  return newTicket;
}

export async function getTicketByToken(token: string): Promise<(TicketRecord & { event?: EventRecord }) | null> {
  if (supabase) {
    const { data: ticketData, error: ticketErr } = await supabase.from('tickets').select('*').eq('ticket_token', token).single();
    if (!ticketErr && ticketData) {
      const { data: eventData } = await supabase.from('events').select('*').eq('id', ticketData.event_id).single();
      return {
        ...ticketData,
        event: eventData || undefined,
        event_name: eventData?.name,
        event_date: eventData?.event_date,
        venue: eventData?.venue,
        planner_name: eventData?.planner_name
      };
    }
  }

  // Fallback in-memory
  const t = mockTickets.find(item => item.ticket_token === token);
  if (!t) return null;
  const ev = mockEvents.find(e => e.id === t.event_id);
  return {
    ...t,
    event: ev,
    event_name: ev?.name,
    event_date: ev?.event_date,
    venue: ev?.venue,
    planner_name: ev?.planner_name
  };
}

export async function verifyAndUseTicket(token: string): Promise<{ status: 'valid' | 'already_used' | 'not_found'; ticket?: TicketRecord & { event_name?: string } }> {
  const ticket = await getTicketByToken(token);
  if (!ticket) {
    return { status: 'not_found' };
  }

  if (ticket.status === 'used') {
    return { status: 'already_used', ticket };
  }

  // Mark as used
  if (supabase) {
    await supabase.from('tickets').update({ status: 'used', updated_at: new Date().toISOString() }).eq('ticket_token', token);
  }
  
  const inMem = mockTickets.find(item => item.ticket_token === token);
  if (inMem) {
    inMem.status = 'used';
    inMem.updated_at = new Date().toISOString();
  }
  ticket.status = 'used';

  return { status: 'valid', ticket };
}

export async function createEventRecord(eventData: Omit<EventRecord, 'id'>): Promise<EventRecord> {
  if (supabase) {
    const { data, error } = await supabase.from('events').insert([eventData]).select().single();
    if (!error && data) {
      return data as EventRecord;
    }
  }

  // Fallback in-memory
  const newEv: EventRecord = {
    ...eventData,
    id: mockEvents.length + 1,
    created_at: new Date().toISOString()
  };
  mockEvents.unshift(newEv);
  return newEv;
}

export async function getAllEvents(): Promise<EventRecord[]> {
  if (supabase) {
    const { data, error } = await supabase.from('events').select('*').order('created_at', { ascending: false });
    if (!error && data && data.length > 0) {
      return data as EventRecord[];
    }
  }
  return [...mockEvents];
}

export async function updateEventRecord(id: number, updates: Partial<EventRecord>): Promise<EventRecord | null> {
  if (supabase) {
    const { data, error } = await supabase.from('events').update(updates).eq('id', id).select().single();
    if (!error && data) {
      return data as EventRecord;
    }
  }

  const index = mockEvents.findIndex(e => e.id === id);
  if (index !== -1) {
    mockEvents[index] = { ...mockEvents[index], ...updates };
    return mockEvents[index];
  }
  return null;
}

export async function getEventAttendees(eventId: number): Promise<any[]> {
  if (supabase) {
    const { data } = await supabase.from('tickets').select('*').eq('event_id', eventId).order('created_at', { ascending: false });
    if (data) return data;
  }

  return mockTickets.filter(t => t.event_id === eventId).reverse();
}

export async function getPlannerMetrics(): Promise<{ 
  totalEvents: number; 
  publishedEvents: number; 
  activeEvents: number;
  ticketsSold: number; 
  ticketsRemaining: number; 
  totalRevenue: number; 
  scannedTickets: number;
}> {
  let totalRevenue = 0;
  let ticketsSold = 0;
  let scannedTickets = 0;
  let totalCapacity = 0;
  let ticketsRemaining = 0;

  if (supabase) {
    const { data: tickets } = await supabase.from('tickets').select('*');
    const { data: events } = await supabase.from('events').select('*');

    if (tickets) {
      tickets.forEach(t => {
        totalRevenue += Number(t.total_paid || 0);
        ticketsSold += Number(t.quantity || 1);
        if (t.status === 'used') scannedTickets += Number(t.quantity || 1);
      });
    }

    const publishedCount = events ? events.filter(e => e.is_active).length : 0;
    if (events) {
      events.forEach(e => {
        totalCapacity += Number(e.capacity || 0);
        ticketsRemaining += Number(e.available_tickets || 0);
      });
    }

    return {
      totalEvents: events ? events.length : 0,
      publishedEvents: publishedCount,
      activeEvents: publishedCount,
      ticketsSold,
      ticketsRemaining,
      totalRevenue: Math.round(totalRevenue * 100) / 100,
      scannedTickets
    };
  }

  // In-memory fallback
  mockTickets.forEach(t => {
    totalRevenue += Number(t.total_paid || 0);
    ticketsSold += Number(t.quantity || 1);
    if (t.status === 'used') scannedTickets += Number(t.quantity || 1);
  });

  const published = mockEvents.filter(e => e.is_active).length;
  mockEvents.forEach(e => {
    ticketsRemaining += Number(e.available_tickets || 0);
  });

  return {
    totalEvents: mockEvents.length,
    publishedEvents: published,
    activeEvents: published,
    ticketsSold,
    ticketsRemaining,
    totalRevenue: Math.round(totalRevenue * 100) / 100,
    scannedTickets
  };
}

export async function getRecentAttendees(): Promise<any[]> {
  if (supabase) {
    const { data } = await supabase.from('tickets').select('*, events(name)').order('created_at', { ascending: false }).limit(25);
    if (data) {
      return data.map(t => ({
        ...t,
        event_name: t.events?.name || 'GatherPass Event'
      }));
    }
  }

  return mockTickets.map(t => {
    const ev = mockEvents.find(e => e.id === t.event_id);
    return {
      ...t,
      event_name: ev?.name || 'GatherPass Event'
    };
  }).reverse();
}
