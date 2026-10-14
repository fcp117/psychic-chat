// Staff workspace checks using real Vue templates and synthetic data.
// Usage: node tests/staff-layout-check.mjs <path-to-playwright/index.mjs>
import { readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { parse, compileScript } from '@vue/compiler-sfc';
import assert from 'node:assert/strict';
const { chromium } = await import(process.argv[2] ? pathToFileURL(resolve(process.argv[2])).href : 'playwright');
const root = resolve(import.meta.dirname, '..');
const manifest = JSON.parse(readFileSync(resolve(root, 'public/build/manifest.json')));
const css = readFileSync(resolve(root, 'public/build', manifest['resources/js/app.js'].css[0]), 'utf8');
const stub = `import { h, reactive } from '/vue.js';
export const Head={render:()=>null};
export const Link={props:['href'],setup:(p,{slots})=>()=>h('a',{href:p.href},slots.default?.())};
export const usePage=()=>({props:window.fixturePage,component:'Chat/Room'});
export const useForm=d=>reactive({...d,errors:{},processing:false,get(){},post(){},patch(){},reset(){},clearErrors(){}});
export const router={reload(){}};`;
const server=createServer((req,res)=>{
 try {
  const pathname=new URL(req.url,'http://localhost').pathname;
  res.setHeader('Content-Type',pathname.endsWith('.css')?'text/css':'text/javascript');
  if(pathname==='/vue.js') return res.end(readFileSync(resolve(root,'node_modules/vue/dist/vue.esm-browser.js')));
  if(pathname==='/inertia.js') return res.end(stub);
  if(pathname==='/style.css') return res.end(css);
  if(pathname==='/axios.js') return res.end('export default {post:async()=>new Promise(()=>{}),get:async()=>({data:{}})}');
  if(pathname.startsWith('/lib/') && pathname.endsWith('.mjs')) return res.end(readFileSync(resolve(root,'resources/js',pathname.slice(1)),'utf8'));
  if(pathname.endsWith('.vue')) {
   const file=resolve(root,'resources/js',pathname.slice(1));
   assert.ok(file.startsWith(resolve(root,'resources/js')));
   if(pathname.includes('/Layouts/')) return res.end(`import {h} from '/vue.js';export default {setup:(_,{slots})=>()=>h('div',{class:'app-content'},slots.default?.())}`);
   const {descriptor}=parse(readFileSync(file,'utf8'));
   const compiled=compileScript(descriptor,{id:pathname,inlineTemplate:true}).content
    .replaceAll("from 'vue'","from '/vue.js'").replaceAll('from "vue"','from "/vue.js"')
    .replaceAll("from '@inertiajs/vue3'","from '/inertia.js'")
    .replaceAll("from 'axios'","from '/axios.js'")
    .replace(/from ['"]@\/(.*?)['"]/g,"from '/$1'");
   return res.end(compiled);
  }
  res.setHeader('Content-Type','text/html');
  return res.end(`<html class="dark"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/style.css"><div id="app"></div><script type="module">
import {createApp} from '/vue.js';
window.route=(name)=>name?'/'+name:{current:()=>false};
window.fixturePage={auth:{user:{id:1,role:'user',name:'Client',available_credits:10}},coachSite:{},features:{}};
const coach={id:2,name:'Test Spiritual Coach',rate_per_hour:60,rating:{average:5,count:2},show_rate_notice:true};
const session={id:1,conversation_id:1,client_id:1,counselor_id:2,status:'completed',client:{id:1,name:'Client'},counselor:coach};
const files={schedule:'Staff/Schedule',health:'Staff/Health',emails:'Staff/Emails',reports:'Admin/Reports',privacy:'Privacy/Requests',inbox:'Support/Inbox'};
const key=new URL(location).searchParams.get('page')||'schedule';
if(new URL(location).searchParams.get('role')==='counselor'){fixturePage.auth.user={id:2,role:'counselor',name:coach.name};}
const props={session,sessions:key==='index'?{data:[session]}:[session],conversationId:1,initialMessages:[{id:1,sender_id:1,sender:{name:'Client'},content:'A message that should stay within the phone screen.',created_at:new Date().toISOString()}],currentUser:fixturePage.auth.user,psychics:{data:[coach],links:[]},filters:{q:''},billing:{disconnect_seconds:30}};
props.initialMessages=Array.from({length:50},(_,i)=>({id:i+1,sender_id:1,sender:{name:'Client'},content:'An older message to verify the conversation scrolls internally.',created_at:new Date().toISOString()}));
const future=new Date(Date.now()+86400000);future.setUTCHours(9,0,0,0);
Object.assign(props,{bookings:{data:[],links:[]},coaches:[coach],availability:[{id:1,coach_id:2,is_available:true,starts_at:future.toISOString(),ends_at:new Date(+future+6*3600000).toISOString()}],busy:[],durations:[5,10,20,30,60],balance:50,reserved:0,selectedCoachId:2});
fixturePage.errors={};
fixturePage.flash={};fixturePage.auth.user.role=key==='schedule'?'manager':'support';fixturePage.staffSections=key==='schedule'?['schedule','reports','birth-chart','moonoscope','tarot']:['support','privacy','reports','email','health'];
Object.assign(props,{filters:{month:'2026-10',coach:'',q:'',role:''},windows:[],bookings:[],incidents:{data:[]},history:{data:[]},recipients:{data:[{id:10,name:'Sample Member',email:'sample@example.invalid',role:'user'}]},templates:{support_update:{label:'Support update',subject:'Support update',intro:'We have an update.'}},roles:['user','counselor','encoder','manager','support','admin'],reports:{data:[{id:1,status:'open',reported_name:'Sample Coach',role:'counselor',reason:'other',details:'Example report details for the test.'}]},blocks:[],filter:'all',requests:{data:[{id:1,kind:'account',status:'pending',name:'Sample Member',reason:'Please review the account closure request.'}]},notice:null,threads:{data:[]},thread:null,messages:null,category:''});

const component=(await import('/Pages/'+files[key]+'.vue')).default;const app=createApp(component,props);app.config.globalProperties.route=window.route;app.config.globalProperties.$page={props:fixturePage};app.mount('#app');
</script></html>`);
 } catch(error){res.statusCode=500;res.end(String(error));}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const browser=await chromium.launch({headless:true,...(process.env.MOBILE_TEST_BROWSER ? {executablePath:process.env.MOBILE_TEST_BROWSER} : {})});
try {
 for(const width of [375,1280]) {
  for(const screen of ['schedule','health','emails','reports','privacy','inbox']) {
   const page=await browser.newPage({viewport:{width,height:844}});
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.goto(`http://127.0.0.1:${server.address().port}/?page=${screen}`);
   await page.getByRole('navigation',{name:'Admin sections'}).waitFor();
   assert.deepEqual(errors,[],screen+' runtime errors');
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),screen+' horizontal overflow');
   assert.equal(await page.getByRole('link',{name:'Accounts',exact:true}).count(),0,'staff cannot see account administration');
   if(screen==='schedule')await page.getByRole('button',{name:'2026-10-02',exact:true}).click();
   if(screen==='emails'){
    await page.getByRole('checkbox').first().check();
    await page.getByLabel('Case-specific details').fill('We have reviewed your request and will follow up.');
    await page.getByRole('button',{name:'Preview emails'}).click();
    await page.getByText('Confirm individual emails').waitFor();
    assert.ok(await page.getByText('To: Sample Member').isVisible());
   }
   if(screen==='reports'){await page.getByRole('button',{name:'Recommend to Manager'}).click();await page.getByLabel('Recommended action').waitFor();}
   if(screen==='privacy'){await page.getByRole('button',{name:'Review for Admin'}).click();await page.getByLabel('Support assessment').waitFor();assert.equal(await page.getByText('Approve irreversible deletion / closure').count(),0);}
   assert.deepEqual(errors,[],screen+' interaction errors');
   if(screen==='schedule'&&width===1280)await page.screenshot({path:'C:/Users/mattp/Documents/Codex/2026-09-27/can-x20/staff-calendar-check.png',fullPage:true});
   console.log(`PASS staff ${screen} at ${width}px`);await page.close();
  }
 }
} finally {await browser.close();server.close();}
