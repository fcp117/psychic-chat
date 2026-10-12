import assert from 'node:assert/strict';
import {availableSlots,dayKey} from '../resources/js/lib/booking-calendar.mjs';
const now=Date.parse('2026-10-12T00:00:00Z');
const windows=[{coach_id:2,starts_at:'2026-10-12T02:00:00Z',ends_at:'2026-10-12T05:00:00Z',is_available:true},
 {coach_id:2,starts_at:'2026-10-12T03:00:00Z',ends_at:'2026-10-12T04:00:00Z',is_available:false}];
const busy=[{id:9,coach_id:2,starts_at:'2026-10-12T02:00:00Z',ends_at:'2026-10-12T02:30:00Z'}];
const slots=availableSlots(windows,busy,2,20,null,now);
assert.equal(slots[0],'2026-10-12T02:30:00.000Z');
assert.ok(!slots.includes('2026-10-12T02:45:00.000Z'),'buffer cannot enter blocked time');
assert.ok(!slots.some(s=>s.startsWith('2026-10-12T03:')));
assert.equal(slots.at(-1),'2026-10-12T04:30:00.000Z');
assert.equal(availableSlots(windows,busy,2,20,9,now)[0],'2026-10-12T02:00:00.000Z');
assert.deepEqual(availableSlots(windows,busy,3,20,null,now),[]);
assert.equal(dayKey('2026-10-12T20:00:00Z','Asia/Manila'),'2026-10-13');
assert.equal(dayKey(now,'Invalid/Zone'),'');
console.log('PASS calendar: blocked hours, appointments, buffers, reschedules, coach isolation, local dates');
