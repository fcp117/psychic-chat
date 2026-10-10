// Adapted from Moonoscope_Full_Instructions.pdf, Appendix A.
// Astronomy Engine (Don Cross), MIT; dependency retains its license.
import * as A from 'astronomy-engine';
export const SIGNS = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
export const THEMES = ['self, energy and fresh starts','comfort, values and resources','conversations, learning and errands','home, family and rest','joy, creativity and play','routines, wellness and helpful tasks','partners and one-on-one connection','intimacy, shared support and renewal','big ideas, study and exploring','goals, work and reputation','friends, community and hopes','quiet reflection and inner rest'];
const formatters = new Map();
function wall(ms, zone) {
  if (!formatters.has(zone)) formatters.set(zone, new Intl.DateTimeFormat('en-CA', {timeZone:zone,hourCycle:'h23',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit'}));
  const p = Object.fromEntries(formatters.get(zone).formatToParts(new Date(ms)).map(p=>[p.type,p.value]));
  return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}:${p.second}`;
}
function dateCheck(date) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(Date.parse(date+'T00:00:00Z')) || new Date(date+'T00:00:00Z').toISOString().slice(0,10)!==date || +date.slice(0,4)<1900 || +date.slice(0,4)>2100) throw new Error('Use a valid date between 1900 and 2100.');
}
// Enumerate offsets to reject nonexistent DST times and expose repeated times.
export function localInstants(date, time, zone) {
  dateCheck(date);
  if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) throw new Error('Enter a valid birth time.');
  const target=date+'T'+time+':00', base=Date.parse(target+'Z'), offsets=new Set();
  for(let h=-48;h<=48;h+=6) { const ms=base+h*3600000; offsets.add(Date.parse(wall(ms,zone)+'Z')-ms); }
  return [...offsets].map(o=>base-o).filter(ms=>wall(ms,zone)===target).sort((a,b)=>a-b);
}
function first(date,zone) { const found=localInstants(date,'00:00',zone); if(found.length)return found[0]; for(let i=1;i<180;i++){const f=localInstants(date,`${String(Math.floor(i/60)).padStart(2,'0')}:${String(i%60).padStart(2,'0')}`,zone);if(f.length)return f[0];}throw new Error('This calendar day is unavailable in this time zone.'); }
function nextDate(date){return new Date(Date.parse(date+'T00:00:00Z')+86400000).toISOString().slice(0,10);}
export function moonAt(ms){ const lon=((A.EclipticGeoMoon(new Date(ms)).lon%360)+360)%360; return {sign:Math.floor(lon/30),degree:+(lon%30).toFixed(2)}; }
function ingresses(start,end) {
  const found=[]; let prev=start, sign=moonAt(start).sign;
  for(let t=Math.min(start+3600000,end); t<=end; t=Math.min(t+3600000,end)) {
    const next=moonAt(t).sign;
    if(next!==sign){let a=prev,b=t;while(b-a>1000){let m=Math.floor((a+b)/2);if(moonAt(m).sign===sign)a=m;else b=m;}found.push({from:SIGNS[sign],to:SIGNS[next],toIndex:next,utc:new Date(b).toISOString()});sign=next;}
    if(t===end)break; prev=t;
  } return found;
}
export function findMoonSign(date,time,zone){
  dateCheck(date); const start=first(date,zone),end=first(nextDate(date),zone)-1;
  const changes=ingresses(start,end), instants=time?localInstants(date,time,zone):localInstants(date,'12:00',zone);
  if(!instants.length)throw new Error('That local time did not exist due to a clock change. Check the birth record or select unknown time.');
  const positions=instants.map(ms=>({...moonAt(ms),utc:new Date(ms).toISOString()}));
  const signs=time?[...new Set(positions.map(p=>SIGNS[p.sign]))]:[...new Set([SIGNS[moonAt(start).sign],...changes.map(c=>c.to)])];
  return {signs,positions:time?positions:[],unknown:!time,ambiguousTime:instants.length>1,changes,zone};
}
export function dailyFacts(date){
  dateCheck(date); const zone='Asia/Manila',ref=localInstants(date,'06:00',zone)[0],m=moonAt(ref),changes=ingresses(first(date,zone),first(nextDate(date),zone)-1);
  const phases=['New Moon','Waxing Crescent','First Quarter','Waxing Gibbous','Full Moon','Waning Gibbous','Last Quarter','Waning Crescent'];
  return {date,zone,reference:new Date(ref).toISOString(),sign:SIGNS[m.sign],degree:m.degree,phase:phases[Math.floor(((A.MoonPhase(new Date(ref))+22.5)%360)/45)],changes,houses:SIGNS.map((sign,i)=>{const house=((m.sign-i+12)%12)+1;return {sign,house,theme:THEMES[house-1],later:changes.filter(c=>Date.parse(c.utc)>ref).map(c=>({house:((c.toIndex-i+12)%12)+1,theme:THEMES[(c.toIndex-i+12)%12]}))};})};
}
export function dailyPrompt(f){
 return `You write the daily Moonoscope: a horoscope for each of the 12 MOON signs.\nSky facts (calculated, do not change them):\n${JSON.stringify(f)}\nRules for every line:\n1. One or two sentences, 35 words or fewer.\n2. Warm, encouraging and positive. Give one gentle, doable suggestion for the day that fits the house theme and the day's Moon sign mood.\n3. No warnings, no fear, no negative predictions. Never promise outcomes about health, money, legal matters or relationships.\n4. Speak to the reader as "you". No emojis, no hashtags, no sign name at the start of the line.\n5. Make each of the 12 lines distinct in wording.\nIf the Moon changes sign, the wording must work for the whole day.\nReply with only a JSON array of 12 objects in zodiac order, Aries first: [{"sign":"Aries","text":"..."}, ...]`;
}
