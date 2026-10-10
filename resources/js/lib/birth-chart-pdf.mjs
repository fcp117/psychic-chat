import {PDFDocument,rgb,StandardFonts} from 'pdf-lib';
import fontkit from '@pdf-lib/fontkit';
import {REPORT_DISCLAIMER,INVITATION,wheelGeometry} from './birth-chart-presentation.mjs';
export async function makeBirthChartPdf(chart,fontBytes,bookingUrl){
 const doc=await PDFDocument.create();doc.registerFontkit(fontkit);
 const font=await doc.embedFont(fontBytes,{subset:true}),heading=await doc.embedFont(StandardFonts.TimesRoman);
 const parsed=fontkit.create(fontBytes);
 for(const s of [chart.name,chart.city,...chart.readings.map(r=>r.body)])for(const c of s)if(!parsed.hasGlyphForCodePoint(c.codePointAt(0))&&!/\s/.test(c))throw new Error('The PDF font does not support a character in the name, city or interpretation. Please use a Latin-script spelling for this download. Your on-screen report is unchanged.');
 const ink=rgb(.13,.09,.22),muted=rgb(.38,.34,.45),purple=rgb(.40,.22,.66),line=rgb(.82,.79,.87),paper=rgb(.96,.94,.98);
 let page,y;
 const text=(s,x,top,size=10,color=ink,f=font)=>page.drawText(String(s),{x,y:842-top-size,size,font:f,color});
 const rule=top=>page.drawLine({start:{x:42,y:842-top},end:{x:553,y:842-top},thickness:.5,color:line});
 function wrap(s,width,size=10,f=font){const out=[];for(const para of String(s).split('\n')){let row='';for(const word of para.split(/\s+/)){if(!word)continue;if(f.widthOfTextAtSize(row+(row?' ':'')+word,size)>width&&row){out.push(row);row='';}if(f.widthOfTextAtSize(word,size)>width){for(const ch of word){if(f.widthOfTextAtSize(row+ch,size)>width){out.push(row);row='';}row+=ch;}}else row+=(row?' ':'')+word;}out.push(row);}return out;}
 function paragraph(s,size=10,width=511){for(const row of wrap(s,width,size)){if(y>762)newPage('Your Reading · continued');text(row,42,y,size,muted);y+=size*1.55;}y+=8;}
 function newPage(title){page=doc.addPage([595,842]);page.drawRectangle({x:0,y:748,width:595,height:94,color:paper});text('INTUITION ISLAND  |  BIRTH CHART STUDIO',42,28,9,purple);text(title,42,49,25,purple,heading);y=115;}
 newPage('Read the sky you were born under.');
 paragraph('The Birth Chart of '+chart.name,15);paragraph(`${chart.date} | ${chart.time??'Birth time unknown'} | ${chart.city}`,10);paragraph(`${chart.zone} | ${chart.unknown?'Noon reference only: ':''}${chart.utc}`,9);
 const big=[chart.points[0],chart.points[1],chart.points.find(p=>p.name==='Ascendant')];const top=y;
 big.forEach((p,i)=>{const x=42+i*174;page.drawRectangle({x,y:842-top-67,width:163,height:67,borderColor:line,borderWidth:.6,color:rgb(1,1,1)});text(['SUN','MOON','RISING'][i],x+10,top+9,8,purple);text(p?(chart.unknown&&p.name==='Moon'?chart.moon.signs.join(' / '):p.sign):'Unknown',x+10,top+24,14);text(chart.unknown?'Time not confirmed':`${p.degree}${p.house?', house '+p.house:''}`,x+10,top+46,8,muted);});
 y=top+82;
 if(!chart.unknown){const g=wheelGeometry(chart),scale=Math.min(.77,(730-y)/564),cx=297,cy=y+282*scale;
  const pos=p=>({x:cx+(p.x-300)*scale,y:842-(cy+(p.y-300)*scale)});
  const grid=rgb(.75,.82,.82),wheelInk=rgb(.10,.20,.23),wheelMuted=rgb(.32,.43,.46);
  const symbol=(path,p,size,color)=>page.drawSvgPath(path,{x:p.x-size/2,y:p.y+size/2,scale:size/24,borderColor:color,borderWidth:1.7});
  for(const radius of [135,205,235,282])page.drawCircle({x:cx,y:842-cy,size:radius*scale,borderColor:grid,borderWidth:.7});
  for(const s of g.signs){page.drawLine({start:pos(s.start),end:pos(s.end),color:grid,thickness:.7});const hex=s.color;symbol(s.path,pos(s),21*scale,rgb(...[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16)/255)));}
  for(const t of g.ticks)page.drawLine({start:pos(t.start),end:pos(t.end),color:grid,thickness:.6});
  for(const h of g.houses){const p=pos(h),s=String(h.number),size=13*scale;page.drawText(s,{x:p.x-font.widthOfTextAtSize(s,size)/2,y:p.y-size*.35,size,font,color:wheelMuted});}
  for(const a of g.aspects)page.drawLine({start:pos(a.from),end:pos(a.to),color:['square','opposition'].includes(a.name)?rgb(.7,.4,.45):rgb(.34,.56,.56),thickness:.35});
  for(const a of g.angles){const p=pos(a.end);page.drawLine({start:pos(a.start),end:p,color:wheelInk,thickness:1.3});if(['AC','MC'].includes(a.name))page.drawText(a.name,{x:p.x+2,y:p.y+(a.name==='MC'?-9:3),size:8*scale,font,color:wheelInk});}
  for(const l of g.labels){const p=pos(l),d=pos(l.dot);page.drawLine({start:d,end:p,color:grid,thickness:.35});page.drawCircle({x:d.x,y:d.y,size:1.4,color:purple});page.drawCircle({x:p.x,y:p.y,size:14*scale,color:rgb(1,1,1)});symbol(l.path,{x:p.x,y:p.y+3*scale},19.2*scale,wheelInk);const s=`${l.degree}°${l.retrograde?' R':''}`,size=10*scale;page.drawText(s,{x:p.x-font.widthOfTextAtSize(s,size)/2,y:p.y-18*scale,size,font,color:wheelMuted});}
  text('Zodiac signs, whole-sign houses and aspects. R = retrograde.',70,742,9,muted);
 }else{paragraph('Limited report - birth time unknown',14);chart.warnings.forEach(w=>paragraph(w));paragraph('Possible Moon signs: '+chart.moon.signs.join(' or '));paragraph('An exact chart wheel is intentionally omitted. Enter a confirmed birth time to calculate Rising, houses and aspects.');}
 newPage('Planets and points');
 const row=(cells,top)=>{[42,166,284,376,438].forEach((x,i)=>text(cells[i]??'',x,top,9));};
 row(['Point','Sign',chart.unknown?'Estimate':'Degree','House','Motion'],y);rule(y+17);y+=25;
 chart.points.forEach((p,i)=>{row([`${i+1}. ${p.name}`,chart.unknown&&p.name==='Moon'?chart.moon.signs.join(' / '):p.sign,chart.unknown?'Noon only':p.degree,p.house??'-',!chart.unknown&&p.retrograde?'Retrograde':'-'],y);y+=16;});
 y+=8;if(!chart.unknown){paragraph('Elements: '+Object.entries(chart.elements).map(([k,v])=>`${k} ${v}`).join(', '),9);paragraph('Modes: '+Object.entries(chart.modes).map(([k,v])=>`${k} ${v}`).join(', '),9);text('Whole-sign houses',42,y,18,purple,heading);y+=28;
 chart.houses.forEach(h=>{text(`${h.number}. ${h.sign}`,42,y,9,purple);text(h.theme,156,y,9);y+=12;text(h.points.join(', ')||'-',156,y,8,muted);y+=12;});
 }else paragraph('All signs above are noon estimates except the explicitly listed Moon possibilities. Houses and motion indicators are omitted.');
 if(!chart.unknown){newPage('Major aspects');paragraph('Angular relationships, ordered by closest alignment. Orbs: conjunction/opposition/trine 8°, square/sextile 6°. These are display conventions, not predictions.',9);chart.aspects.forEach(a=>{if(y>748)newPage('Major aspects · continued');text(`${a.a} ${a.name} ${a.b}`,42,y,10);text(`${a.orb.toFixed(1)}°`,500,y,10,purple);rule(y+19);y+=27;});}
 newPage('Your Reading');
 paragraph('Your Big Three',16);paragraph(chart.unknown?'This report does not assign a definite Big Three because your birth time is unknown.':`Sun in ${big[0].sign}. Moon in ${big[1].sign}. Rising in ${big[2].sign}.`);
 for(const r of chart.readings){paragraph(r.heading,12);paragraph(r.body);}
 if(chart.readings.length<3)paragraph('A complete written interpretation has not yet been supplied for these placements. Missing text is not invented; the calculated chart remains available for a personal interpretation.',9);
 paragraph('A Closing Blessing',15);paragraph('May you trust the sky within you.');paragraph('Go deeper with a personal interpretation',15);paragraph(INVITATION);paragraph('Book your session with Intuition Island.',11);
 if(/^https?:\/\//.test(bookingUrl||''))paragraph(bookingUrl,9);
 paragraph(chart.method,8);paragraph(REPORT_DISCLAIMER,8);
 for(const [i,p] of doc.getPages().entries()){p.drawLine({start:{x:42,y:40},end:{x:553,y:40},color:line,thickness:.5});p.drawText('Intuition Island | Personal birth chart',{x:42,y:25,size:8,font,color:muted});p.drawText(`Page ${i+1} of ${doc.getPageCount()}`,{x:481,y:25,size:8,font,color:muted});}
 doc.setTitle('Intuition Island - Birth Chart');doc.setAuthor('Intuition Island');return doc.save();
}
