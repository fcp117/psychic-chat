<script setup>
import {computed} from 'vue';
import {wheelGeometry} from '@/lib/birth-chart-presentation.mjs';
const props=defineProps({chart:Object});const geometry=computed(()=>wheelGeometry(props.chart));
</script>
<template>
 <svg v-if="geometry" viewBox="0 0 600 600" role="img" aria-label="Birth chart wheel with zodiac signs, whole-sign houses, planets, degrees and aspects. Exact positions are listed in the table below." class="mx-auto block h-auto w-full max-w-2xl rounded-xl bg-white" style="aspect-ratio:1">
  <circle cx="300" cy="300" r="298" fill="white"/>
  <circle v-for="r in [135,205,235,282]" :key="r" cx="300" cy="300" :r="r" fill="none" stroke="#bfd0d0" stroke-width="1.3"/>
  <g v-for="s in geometry.signs" :key="s.name"><title>{{ s.name }}</title><line :x1="s.start.x" :y1="s.start.y" :x2="s.end.x" :y2="s.end.y" stroke="#bfd0d0"/><path :d="s.path" :transform="`translate(${s.x-10},${s.y-10}) scale(.85)`" fill="none" :stroke="s.color" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></g>
  <line v-for="(t,i) in geometry.ticks" :key="'tick'+i" :x1="t.start.x" :y1="t.start.y" :x2="t.end.x" :y2="t.end.y" stroke="#bfd0d0"/>
  <text v-for="h in geometry.houses" :key="h.number" :x="h.x" :y="h.y" text-anchor="middle" dominant-baseline="middle" fill="#526e75" font-size="14">{{ h.number }}</text>
  <line v-for="a in geometry.aspects" :key="a.a+a.b" :x1="a.from.x" :y1="a.from.y" :x2="a.to.x" :y2="a.to.y" :stroke="['square','opposition'].includes(a.name)?'#be493b':'#409676'" stroke-width="1" opacity=".7"/>
  <g v-for="a in geometry.angles" :key="a.name"><line :x1="a.start.x" :y1="a.start.y" :x2="a.end.x" :y2="a.end.y" stroke="#19333a" stroke-width="2"/><text v-if="['AC','MC'].includes(a.name)" :x="a.end.x+3" :y="a.end.y+(a.name==='MC'?12:-4)" fill="#19333a" stroke="white" stroke-width="3" paint-order="stroke" font-size="11" font-weight="bold">{{ a.name }}</text></g>
  <g v-for="l in geometry.labels" :key="l.name"><title>{{ l.name }} · {{ l.degree }} degrees{{ l.retrograde?' · retrograde':'' }}</title><line :x1="l.dot.x" :y1="l.dot.y" :x2="l.x" :y2="l.y" stroke="#d9dfe3" stroke-width=".6"/><circle :cx="l.dot.x" :cy="l.dot.y" r="1.8" fill="#7843b0"/><circle :cx="l.x" :cy="l.y" r="14" fill="white"/><path :d="l.path" :transform="`translate(${l.x-9},${l.y-13}) scale(.8)`" fill="none" stroke="#19333a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><text :x="l.x" :y="l.y+18" text-anchor="middle" fill="#526e75" font-size="11">{{ l.degree }}°{{ l.retrograde?' R':'' }}</text></g>
 </svg>
</template>
