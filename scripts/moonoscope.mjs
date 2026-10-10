import {dailyFacts,dailyPrompt} from '../resources/js/lib/moonoscope.mjs';
try {const facts=dailyFacts(process.argv[2]);console.log(JSON.stringify({facts,prompt:dailyPrompt(facts)}));} catch {console.error('Invalid date or unavailable astronomy calculation.');process.exitCode=1;}
