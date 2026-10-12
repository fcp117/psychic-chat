import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=readFileSync(new URL('../resources/js/Components/ReflectionMusic.vue',import.meta.url),'utf8')
 .split('<script setup>')[1].split('</script>')[0].replace(/^import .*$/gm,'');
const listeners=new Map(),watchers=[];
let mounted,unmount,plays=0,saved=false;
const page={props:{auth:{user:null}}};
const context=vm.createContext({
 ref:value=>({value}),computed:fn=>({get value(){return fn();}}),
 watch:(getter,fn)=>watchers.push({getter,fn}),usePage:()=>page,route:n=>n,
 axios:{put:async(_,{muted})=>{saved=muted;},get:async()=>({data:{muted:saved}})},
 onMounted:fn=>{mounted=fn;},onBeforeUnmount:fn=>{unmount=fn;},
 document:{addEventListener:(type,fn)=>listeners.set(type,fn),removeEventListener:type=>listeners.delete(type)},
 window:{addEventListener(){},removeEventListener(){},setInterval(){},clearInterval(){}},
});
vm.runInContext(source+'\nglobalThis.player={audio,playing,muted,control,toggle,sync};',context);
const p=context.player;
p.audio.value={muted:true,volume:0,pause(){p.playing.value=false;},async play(){plays++;p.playing.value=true;}};
p.control.value={contains:target=>target==='button'};
const tick=()=>new Promise(resolve=>setImmediate(resolve));
mounted();await tick();
assert.equal(plays,0,'guests do not autoplay before interaction');
listeners.get('click')({isTrusted:false,target:'page'});
listeners.get('click')({isTrusted:true,target:'button'});
assert.equal(plays,0);
listeners.get('click')({isTrusted:true,target:'page'});await tick();
assert.equal(plays,1);assert.equal(p.muted.value,false);
await p.toggle();assert.equal(p.muted.value,true);
page.props.auth.user={id:7,music_muted:true};watchers[0].fn();
assert.equal(p.muted.value,true);assert.equal(listeners.size,0,'saved mute never arms playback');
await p.toggle();assert.equal(saved,false);assert.equal(page.props.auth.user.music_muted,false);
await p.toggle();assert.equal(saved,true);
saved=false;await p.sync();assert.equal(p.muted.value,false,'other-device preference syncs');
saved=true;await p.sync();assert.equal(p.muted.value,true);
unmount();assert.equal(listeners.size,0);
console.log('PASS guest first gesture, saved account mute, preference writes, cross-device refresh, cleanup');
