import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source=readFileSync(new URL('../resources/js/Components/ReflectionMusic.vue',import.meta.url),'utf8')
 .split('<script setup>')[1].split('</script>')[0].replace(/^import .*$/m,'');
const listeners=new Map();
let mounted,unmount,plays=0,blocked=true;
const context=vm.createContext({
 ref:value=>({value}),computed:fn=>({get value(){return fn();}}),
 onMounted:fn=>{mounted=fn;},onBeforeUnmount:fn=>{unmount=fn;},
 document:{addEventListener:(type,fn)=>listeners.set(type,fn),removeEventListener:type=>listeners.delete(type)},
});
vm.runInContext(source+'\nglobalThis.player={audio,playing,muted,loading,control,toggle};',context);
const p=context.player;
p.audio.value={muted:true,volume:0,pause(){},async play(){plays++;if(blocked)throw {name:'NotAllowedError'};p.playing.value=true;}};
p.control.value={contains:target=>target==='button'};
const tick=()=>new Promise(resolve=>setImmediate(resolve));
mounted();await tick();
assert.equal(listeners.size,2,'blocked autoplay arms fallback');
listeners.get('click')({isTrusted:false,target:'page'});
listeners.get('click')({isTrusted:true,target:'button'});
assert.equal(plays,1,'synthetic events and control gestures do not double-start');
blocked=false;
listeners.get('click')({isTrusted:true,target:'page'});await tick();
assert.equal(plays,2);assert.equal(p.muted.value,false);assert.equal(listeners.size,0);
await p.toggle(false);
assert.equal(p.muted.value,true,'manual mute stays on');assert.equal(listeners.size,0);
unmount();assert.equal(listeners.size,0);
console.log('PASS: autoplay fallback, single gesture playback, manual mute and cleanup');
