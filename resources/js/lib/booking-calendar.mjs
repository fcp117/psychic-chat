export function dayKey(value, zone) {
 try {
  const parts = new Intl.DateTimeFormat('en-CA', {timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date(value));
  const get = type => parts.find(part => part.type === type).value;
  return get('year')+'-'+get('month')+'-'+get('day');
 } catch { return ''; }
}
export function availableSlots(windows, busy, coachId, minutes, previousId = null, now = Date.now()) {
 const span = (Number(minutes)+10)*60000, result = new Set();
 if(!Number.isFinite(span)||span<=0)return [];
 const own = windows.filter(a=>Number(a.coach_id)===Number(coachId));
 const blocked = [...own.filter(a=>a.is_available===false||a.is_available===0),...busy.filter(b=>Number(b.coach_id)===Number(coachId)&&b.id!==previousId)];
 for(const a of own.filter(a=>a.is_available!==false&&a.is_available!==0)){
  const end=Date.parse(a.ends_at);
  for(let t=Date.parse(a.starts_at);t+span<=end&&t<=now+90*86400000;t+=900000){
   if(t<now+3600000)continue;
   if(blocked.some(b=>Date.parse(b.starts_at)<t+span&&Date.parse(b.ends_at)>t))continue;
   result.add(new Date(t).toISOString());
  }
 }
 return [...result].sort();
}
