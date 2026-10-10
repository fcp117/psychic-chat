import {SIGNS,chartWheelPoint} from './birth-chart.mjs';
import {SYMBOLS} from './astrology-symbols.mjs';
export const REPORT_DISCLAIMER='Positions calculated with Astronomy Engine (tropical zodiac, true equinox of date). This reading is offered for reflection and personal insight. Astrological interpretations are not scientifically established personality or outcome predictions.';
export const INVITATION='Your chart holds many layers, and some of its most meaningful messages come through in conversation. In a one-on-one interpretation session, we explore your chart together, connect its patterns to what is unfolding in your life right now, and bring greater clarity and understanding to your path.';
export function wheelGeometry(chart){
 if(chart.unknown||!Number.isFinite(chart.asc))return null;
 const asc=chart.asc;
 const labels=[];
 for(const [i,p] of chart.points.entries()){
  if(['Ascendant','Midheaven'].includes(p.name))continue;
  const dot=chartWheelPoint(p.longitude,205,asc);let label;
  outer:for(const shift of [0,8,-8,16,-16,24,-24,32,-32,40,-40,48,-48,56,-56,64,-64,72,-72,80,-80,88,-88,96,-96]){const c=chartWheelPoint(p.longitude+shift,179,asc);if(labels.every(l=>Math.hypot(l.x-c.x,l.y-c.y)>35)){label=c;break outer;}}
  label??=chartWheelPoint(p.longitude,179,asc);labels.push({...label,dot,index:i+1,name:p.name,path:SYMBOLS[p.name],degree:Math.floor(p.longitude%30),retrograde:p.retrograde});
 }
 const rising=Math.floor(asc/30);
 return {
  signs:SIGNS.map((name,i)=>({name,path:SYMBOLS[name],color:['#ba4536','#29825c','#197881','#7843b0'][i%4],...chartWheelPoint(i*30+15,263,asc),start:chartWheelPoint(i*30,205,asc),end:chartWheelPoint(i*30,282,asc)})),
  houses:Array.from({length:12},(_,i)=>({number:i+1,...chartWheelPoint((rising+i)*30+15,220,asc)})),
  ticks:Array.from({length:72},(_,i)=>({start:chartWheelPoint(i*5,205,asc),end:chartWheelPoint(i*5,i%6===0?192:199,asc)})),
  angles:[{name:'AC',longitude:asc},{name:'MC',longitude:chart.mc},{name:'DC',longitude:asc+180},{name:'IC',longitude:chart.mc+180}].map(a=>({...a,start:chartWheelPoint(a.longitude,135,asc),end:chartWheelPoint(a.longitude,205,asc),label:chartWheelPoint(a.longitude,213,asc)})),
  labels,aspects:chart.aspects.map(a=>({...a,from:chartWheelPoint(chart.points.find(p=>p.name===a.a).longitude,135,asc),to:chartWheelPoint(chart.points.find(p=>p.name===a.b).longitude,135,asc)}))
 };
}
