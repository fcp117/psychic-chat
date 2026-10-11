// Browser layout checks using real Vue templates and synthetic, non-private data.
// Usage: node tests/mobile-layout-check.mjs <path-to-playwright/index.mjs>
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
const files={room:'Chat/Room',index:'Chat/Index',directory:'Chat/FindPsychic'};
const key=new URL(location).searchParams.get('page')||'room';
if(new URL(location).searchParams.get('role')==='counselor'){fixturePage.auth.user={id:2,role:'counselor',name:coach.name};}
const props={session,sessions:key==='index'?{data:[session]}:[session],conversationId:1,initialMessages:[{id:1,sender_id:1,sender:{name:'Client'},content:'A message that should stay within the phone screen.',created_at:new Date().toISOString()}],currentUser:fixturePage.auth.user,psychics:{data:[coach],links:[]},filters:{q:''},billing:{disconnect_seconds:30}};
const component=(await import('/Pages/'+files[key]+'.vue')).default;const app=createApp(component,props);app.config.globalProperties.route=window.route;app.mount('#app');
</script></html>`);
 } catch(error){res.statusCode=500;res.end(String(error));}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const browser=await chromium.launch({headless:true,...(process.env.MOBILE_TEST_BROWSER ? {executablePath:process.env.MOBILE_TEST_BROWSER} : {})});
try {
 for(const width of [320,375,390,430,768,1280]) {
  for(const screen of ['index','room','directory']) {
   for(const role of screen==='room'?['user','counselor']:['user']) {
    const page=await browser.newPage({viewport:{width,height:844}});
    const errors=[];page.on('pageerror',e=>{errors.push(e.message);console.error(e.message);});
    page.on('response',async r=>{if(r.status()>=400)console.error(r.url(),await r.text());});
    await page.goto(`http://127.0.0.1:${server.address().port}/?page=${screen}&role=${role}`);
    await page.locator('.conversation-layout').waitFor();
    assert.deepEqual(errors,[]);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${screen} ${width} page overflow`);
    if(width<768) {
     if(screen==='room') { assert.equal(await page.locator('.conversation-sidebar').isVisible(),false);await page.getByText('← All conversations').waitFor(); }
     if(screen==='directory') {
      await page.getByRole('button',{name:/Test Spiritual Coach/}).click();
      assert.equal(await page.locator('.coach-directory > aside').isVisible(),false);
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'coach detail overflow');
      if(width===390 && process.env.MOBILE_TEST_SCREENSHOT) await page.screenshot({path:process.env.MOBILE_TEST_SCREENSHOT,fullPage:true});
      await page.getByRole('button',{name:'← All Spiritual Coaches'}).click();
      assert.equal(await page.locator('.coach-directory > aside').isVisible(),true);
     }
    } else assert.equal(await page.locator('.conversation-layout > aside').isVisible(),true);
    await page.close();console.log(`PASS ${screen} ${role} at ${width}px`);
   }
  }
 }
} finally {await browser.close();server.close();}
