import type { VercelRequest, VercelResponse } from '@vercel/node';
import { verifyAndUseTicket } from '../lib/db.js';
import { logCloudWatchMetric } from '../lib/aws.js';

export default async function handler(req: VercelRequest, res: VercelResponse) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  try {
    let token = '';
    if (req.method === 'POST') {
      token = req.body?.token || req.body?.qr_code || '';
    } else {
      token = (req.query?.token as string) || '';
    }

    token = token.trim();
    if (!token) {
      return res.status(400).json({
        success: false,
        status: 'error',
        message: 'No pass token or QR code provided.'
      });
    }

    const result = await verifyAndUseTicket(token);

    // AWS Cloud Integration: CloudWatch Entry Metric
    await logCloudWatchMetric({
      action: result.status === 'valid' ? 'ENTRY_GRANTED' : 'ENTRY_REJECTED',
      token,
      timestamp: new Date().toISOString(),
      metadata: { status: result.status, ticket_id: result.ticket?.id }
    });

    if (result.status === 'not_found') {
      return res.status(200).json({
        success: false,
        status: 'invalid',
        title: 'Invalid ticket — entry denied',
        message: 'No pass matching this token was found in the database.'
      });
    }

    if (result.status === 'already_used') {
      const t = result.ticket!;
      return res.status(200).json({
        success: false,
        status: 'already_used',
        title: 'Already used — entry denied',
        message: 'This pass has already been used for entry.',
        ticket: t
      });
    }

    // Success valid ticket
    const t = result.ticket!;
    return res.status(200).json({
      success: true,
      status: 'valid',
      title: 'Valid — entry approved',
      message: 'Valid pass. Guest is cleared for entry.',
      ticket: t
    });
  } catch (error: any) {
    console.error('API /verify error:', error);
    return res.status(500).json({ success: false, status: 'error', message: 'Verification processing failed' });
  }
}
