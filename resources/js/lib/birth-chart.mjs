import * as A from 'astronomy-engine';
import {SIGNS,localInstants,findMoonSign} from './moonoscope.mjs';
export {SIGNS};
export const BODIES=['Sun','Moon','Mercury','Venus','Mars','Jupiter','Saturn','Uranus','Neptune','Pluto'];
export const HOUSE_THEMES=['self, body and first impressions','money, values and resources','communication, siblings and learning','home, family and roots','creativity, joy and romance','daily work, health and routines','partnerships and one-on-one relationships','shared resources, intimacy and transformation','travel, higher learning and beliefs','career, reputation and public life','friends, groups and hopes','solitude, the unconscious and spiritual retreat'];
export const norm=x=>((x%360)+360)%360;
const delta=(a,b)=>norm(a-b+180)-180,rad=Math.PI/180;
export function longitude(body,t){return body==='Moon'?A.EclipticGeoMoon(t).lon:A.Ecliptic(A.GeoVector(body,t,true)).elon;}
// Ascending osculating lunar node from angular momentum in the true ecliptic frame.
export function nodeLongitude(t){const s=A.GeoMoonState(t),r=A.Rotation_EQJ_ECT(t);const p=A.RotateVector(r,new A.Vector(s.x,s.y,s.z,t)),v=A.RotateVector(r,new A.Vector(s.vx,s.vy,s.vz,t));const hx=p.y*v.z-p.z*v.y,hy=p.z*v.x-p.x*v.z;return norm(Math.atan2(hx,-hy)/rad);}
export function angles(t,lat,lon){
 const theta=(A.SiderealTime(t)*15+lon)*rad,eps=A.e_tilt(A.MakeTime(t)).tobl*rad,phi=lat*rad;
 let asc=norm(Math.atan2(-Math.cos(theta),Math.sin(theta)*Math.cos(eps)+Math.tan(phi)*Math.sin(eps))/rad+180);
 // Select the eastern horizon intersection, including high-latitude cases.
 const v=A.RotateVector(A.Rotation_ECT_EQD(t),new A.Vector(Math.cos(asc*rad),Math.sin(asc*rad),0,t));
 if(-Math.sin(theta)*v.x+Math.cos(theta)*v.y<0)asc=norm(asc+180);
 return {asc,mc:norm(Math.atan2(Math.sin(theta),Math.cos(theta)*Math.cos(eps))/rad)};
}
export function degrees(lon){const minutes=Math.floor((norm(lon)%30)*60+1e-7);return `${Math.floor(minutes/60)}°${String(minutes%60).padStart(2,'0')}'`;}
const point=(name,lon,retrograde=false)=>({name,longitude:norm(lon),sign:SIGNS[Math.floor(norm(lon)/30)],degree:degrees(lon),retrograde,house:null});
// SVG coordinates increase downward: increasing longitude runs counterclockwise
// from the Ascendant at the left, matching the supplied chart convention.
export function chartWheelPoint(longitude,radius,asc=0){const angle=(180-(longitude-asc))*rad;return {x:300+radius*Math.cos(angle),y:300+radius*Math.sin(angle)};}
export function calculateBirthChart(input,library=[]){
 const {date,zone}=input;const unknown=!!input.unknown;
 const today=new Intl.DateTimeFormat('en-CA',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 if(!date||date>today)throw new Error('Enter a valid birthdate, not a future date.');
 if(!input.name?.trim()||input.name.length>80)throw new Error('Enter a report name (up to 80 characters).');
 if(!input.city?.trim()||input.city.length>120)throw new Error('Enter your city of birth (up to 120 characters).');
 const times=localInstants(date,unknown?'12:00':input.time,zone);
 if(!times.length)throw new Error('That local time did not exist during a clock change. Check the birth record or use unknown time.');
 if(!unknown&&times.length>1&&!['earlier','later'].includes(input.fold))throw new Error('This clock time occurred twice. Choose the earlier or later occurrence from your birth record.');
 const instant=times[!unknown&&input.fold==='later'?times.length-1:0];const t=new Date(instant);
 const lat=Number(input.latitude),lon=Number(input.longitude);
 if(!unknown&&(input.latitude===''||input.longitude===''||!Number.isFinite(lat)||Math.abs(lat)>=89||!Number.isFinite(lon)||Math.abs(lon)>180))throw new Error('Confirm latitude (-89 to 89, excluding poles) and longitude (-180 to 180).');
 const points=BODIES.map(b=>point(b,longitude(b,t),delta(longitude(b,new Date(instant+3600000)),longitude(b,new Date(instant-3600000)))<0));
 const node=nodeLongitude(t);points.push(point('North Node',node),point('South Node',node+180));
 let asc=null,mc=null;const houses=[];
 if(!unknown){({asc,mc}=angles(t,lat,lon));points.push(point('Ascendant',asc),point('Midheaven',mc));const rising=Math.floor(asc/30);
  points.forEach(p=>p.house=(Math.floor(p.longitude/30)-rising+12)%12+1);
  for(let i=0;i<12;i++)houses.push({number:i+1,sign:SIGNS[(rising+i)%12],theme:HOUSE_THEMES[i],points:points.filter(p=>p.house===i+1&&!['Ascendant','Midheaven'].includes(p.name)).map(p=>p.name)});
 }
 // The sample does not specify orbs: explicit implementation convention, editable later.
 const definitions=[['conjunction',0,8],['sextile',60,6],['square',90,6],['trine',120,8],['opposition',180,8]];
 const aspects=[];
 if(!unknown){const eligible=points.filter(p=>!p.name.includes('Node'));for(let i=0;i<eligible.length;i++)for(let j=i+1;j<eligible.length;j++){
  const a=eligible[i],b=eligible[j];if(['Ascendant','Midheaven'].includes(a.name)&&['Ascendant','Midheaven'].includes(b.name))continue;
  const distance=Math.abs(delta(a.longitude,b.longitude));for(const [name,angle,orb] of definitions)if(Math.abs(distance-angle)<=orb){aspects.push({a:a.name,b:b.name,name,orb:+Math.abs(distance-angle).toFixed(2),angle});break;}
 }aspects.sort((a,b)=>a.orb-b.orb);}
 const elements={Fire:0,Earth:0,Air:0,Water:0},modes={Cardinal:0,Fixed:0,Mutable:0};
 if(!unknown)points.slice(0,10).forEach(p=>{const i=Math.floor(p.longitude/30);elements[Object.keys(elements)[i%4]]++;modes[Object.keys(modes)[i%3]]++;});
 const moon=findMoonSign(date,unknown?'':input.time,zone);
 const readings=unknown?[]:['Sun','Moon','Ascendant'].flatMap(name=>{const p=points.find(p=>p.name===name);const entry=library.find(l=>l.point===name&&l.sign===p.sign&&l.body?.trim());return entry?[{heading:`${name==='Ascendant'?'Rising':name} in ${p.sign}`,body:entry.body}]:[];});
 return {name:input.name.trim(),date,time:unknown?null:input.time,city:input.city.trim(),zone,utc:t.toISOString(),offsetMinutes:(Date.parse(date+'T'+(unknown?'12:00':input.time)+':00Z')-instant)/60000,unknown,latitude:unknown?null:lat,longitude:unknown?null:lon,fold:times.length>1?input.fold:null,points,houses,aspects,elements,modes,asc,mc,moon,readings,method:'Tropical zodiac; whole-sign houses; geocentric true equinox of date; osculating lunar nodes.',warnings:unknown?['Birth time unknown: planetary signs are noon estimates, not confirmed placements.','Rising, Midheaven, houses, aspects, element totals and personalized interpretation are omitted.','Moon possibilities are checked across the full local birth date.']:['Small differences in birth time or birthplace can change Rising and houses.','Aspects use configured display conventions: conjunction/opposition/trine 8°, square/sextile 6°.']};
}
