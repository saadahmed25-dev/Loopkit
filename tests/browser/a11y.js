/**
 * Accessibility audit of the builder.
 *
 *   node tests/browser/a11y.js <page-id>
 *
 * Measures what a script can measure: accessible names, focus visibility,
 * keyboard reachability. It does not tell you whether the builder can
 * actually be used with a keyboard — that needs someone to unplug the mouse.
 */
const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const b=await puppeteer.launch({executablePath:process.env.CHROME||chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1500,height:950});
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
 await p.goto('http://127.0.0.1:8080/wp-admin/post.php?post='+process.argv[2]+'&action=loopkit',{waitUntil:'networkidle2'});
 await wait(3000);

 // select the heading so the settings panel renders its controls
 const cv=p.frames().find(f=>f.name()==='lk-canvas-iframe');
 if(cv){ await cv.evaluate(()=>{const e=document.querySelector('.lk-editable[data-id="wid1"]');
   let c=e&&e.firstElementChild; while(c&&c.classList.contains('lk-element-handle')) c=c.nextElementSibling;
   if(c) c.click();}); }
 await wait(1500);

 const r = await p.evaluate(() => {
   const vis = el => { const s=getComputedStyle(el), b=el.getBoundingClientRect();
     return s.display!=='none' && s.visibility!=='hidden' && b.width>0 && b.height>0; };
   const all=[...document.querySelectorAll('*')].filter(vis);
   const name = e => (e.getAttribute('aria-label')||'').trim()||(e.getAttribute('title')||'').trim()||
     (e.textContent||'').trim()||(e.labels&&e.labels.length?[...e.labels].map(l=>l.textContent.trim()).join(' '):'');
   const controls=all.filter(e=>/^(BUTTON|A|INPUT|SELECT|TEXTAREA)$/.test(e.tagName));
   const unnamed=controls.filter(e=>!name(e));
   const inputs=all.filter(e=>/^(INPUT|SELECT|TEXTAREA)$/.test(e.tagName)&&!['hidden','submit','button'].includes(e.type));
   const unlabelled=inputs.filter(e=>!name(e));
   // Something inside a focusable parent is already reachable — counting it
   // separately made a fixed problem look unfixed.
   const clickableNotFocusable=all.filter(e=>
     !/^(BUTTON|A|INPUT|SELECT|TEXTAREA)$/.test(e.tagName) && e.tabIndex<0 &&
     getComputedStyle(e).cursor==='pointer' &&
     (e.className||'').toString().match(/lk-/) &&
     !e.closest('button, a, [tabindex]:not([tabindex="-1"])'));
   return {
     visible: all.length, controls: controls.length,
     unnamed: unnamed.length,
     unnamedSample: [...new Set(unnamed.map(e=>e.tagName.toLowerCase()+'.'+(e.className||'').toString().split(' ')[0]))].slice(0,8),
     inputs: inputs.length, unlabelled: unlabelled.length,
     unlabelledSample: [...new Set(unlabelled.map(e=>(e.className||'').toString().split(' ')[0]||e.tagName.toLowerCase()))].slice(0,8),
     clickableNotFocusable: clickableNotFocusable.length,
     cnfSample: [...new Set(clickableNotFocusable.map(e=>(e.className||'').toString().split(' ').slice(0,2).join('.')))].slice(0,8),
   };
 });
 let pass=0, fail=0;
 const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
 console.log('  state:', JSON.stringify(r,null,1));
 t(r.unnamed===0,   'Every control has an accessible name ('+r.unnamed+' without)');
 t(r.unlabelled===0,'Every field has a label ('+r.unlabelled+' without)');
 t(r.clickableNotFocusable===0,
   'Nothing clickable is out of the keyboard\u2019s reach ('+r.clickableNotFocusable+')');

 // focus ring: does anything show where you are?
 const focus=[];
 for(let i=0;i<18;i++){
   await p.keyboard.press('Tab');
   const f=await p.evaluate(()=>{
     const e=document.activeElement; if(!e||e===document.body) return null;
     const s=getComputedStyle(e);
     const ring = (s.outlineStyle!=='none' && parseFloat(s.outlineWidth)>0) ||
                  (s.boxShadow && s.boxShadow!=='none');
     return {tag:e.tagName.toLowerCase(), cls:(e.className||'').toString().split(' ')[0], ring:ring};
   });
   if(f) focus.push(f);
 }
 const noRing = focus.filter(f=>!f.ring);
 console.log('  tab stops reached: '+focus.length+',  with no visible focus: '+noRing.length);
 t(focus.length>10, 'The keyboard reaches the builder at all ('+focus.length+' tab stops)');
 t(noRing.length===0, 'Every tab stop shows where you are'+
   (noRing.length?': '+JSON.stringify([...new Set(noRing.map(f=>f.tag+'.'+f.cls))].slice(0,6)):''));
 const extra = await p.evaluate(()=>({
   h1: document.querySelectorAll('h1').length,
   iframeTitle: !!(document.querySelector('#lk-canvas-iframe')||{}).title,
   landmarks: document.querySelectorAll('[role="banner"],[role="main"],[role="complementary"]').length,
 }));
 t(extra.h1>=1, 'The screen has a heading');
 t(extra.iframeTitle, 'The canvas iframe is named');
 t(extra.landmarks>=3, 'Top bar, panel and canvas are all landmarks ('+extra.landmarks+')');
 console.log('\n=== accessibility: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();
