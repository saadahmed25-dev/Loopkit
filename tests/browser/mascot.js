const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
const wait=(ms)=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1400,height:900});
 const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);

 console.log('=== On a Loopkit admin screen ===');
 await p.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_collections',{waitUntil:'networkidle2'});
 await wait(500);
 let r=await p.evaluate(()=>{
   const d=document.querySelector('.lkm-dock'); if(!d) return {there:false};
   const box=d.getBoundingClientRect(); const svg=d.querySelector('.lkm');
   const eye=getComputedStyle(d.querySelector('.lkm-eye')).fill;
   return {there:true, right:Math.round(innerWidth-box.right), bottom:Math.round(innerHeight-box.bottom),
           eye, moods:Object.keys(window.loopkitMascot.tips).length,
           handle:typeof window.LoopkitMascot};
 });
 console.log('  dock:',JSON.stringify(r));
 t(r.there,'The mascot is on the page');
 t(r.right>=10&&r.right<=26 && r.bottom>=10&&r.bottom<=26,'Parked in the bottom-right corner');
 t(r.eye==='rgb(11, 18, 32)','Eyes use the fixed dark colour, not the theme ink');
 t(r.moods>=20,'Widget tips reached the page ('+r.moods+')');
 t(r.handle==='object','Other code can nudge it');
 await p.screenshot({path:'/tmp/mas-admin.png'});

 console.log('=== It reacts ===');
 await p.evaluate(()=>window.LoopkitMascot.mood('is-happy',0)); await wait(150);
 t(await p.evaluate(()=>document.querySelector('.lkm').classList.contains('is-happy')),'Happy on demand');
 await p.evaluate(()=>document.dispatchEvent(new Event('loopkit:deleted'))); await wait(150);
 t(await p.evaluate(()=>document.querySelector('.lkm').classList.contains('is-angry')),'Angry when something is deleted');
 await p.evaluate(()=>document.dispatchEvent(new Event('loopkit:added'))); await wait(150);
 t(await p.evaluate(()=>document.querySelector('.lkm').classList.contains('is-happy')),'Happy when something is added');
 await p.evaluate(()=>{const e=new Event('dragstart',{bubbles:true});document.body.dispatchEvent(e);}); await wait(150);
 t(await p.evaluate(()=>document.querySelector('.lkm').classList.contains('is-excited')),'Excited while dragging');

 console.log('=== It speaks, and can be hushed ===');
 await p.evaluate(()=>window.LoopkitMascot.say('Collection List','Bind the fields once.','is-thinking')); await wait(250);
 let s=await p.evaluate(()=>{const bb=document.querySelector('.lkm-bubble');
   return {open:bb.classList.contains('is-open'), head:bb.querySelector('b').textContent,
           vis:getComputedStyle(bb).visibility};});
 t(s.open && s.vis==='visible','The bubble opens');
 t(s.head==='Collection List','With the widget name as its heading');
 await p.click('.lkm-bubble-close'); await wait(250);
 t(!(await p.evaluate(()=>document.querySelector('.lkm-bubble').classList.contains('is-open'))),'The close button hushes it');

 console.log('=== It stays off when told ===');
 await p.evaluate(()=>localStorage.setItem('loopkitMascot','off'));
 await p.reload({waitUntil:'networkidle2'}); await wait(400);
 t(!(await p.$('.lkm-dock')),'Switched off, it does not come back on reload');
 await p.evaluate(()=>localStorage.removeItem('loopkitMascot'));
 await p.reload({waitUntil:'networkidle2'}); await wait(400);
 t(!!(await p.$('.lkm-dock')),'And returns when the setting is cleared');

 console.log('=== Not on other screens ===');
 await p.goto('http://127.0.0.1:8080/wp-admin/edit.php',{waitUntil:'networkidle2'}); await wait(300);
 t(!(await p.$('.lkm-dock')),'Absent from the Posts screen');

 t(errs.length===0,'No JavaScript errors ('+errs.join(' | ')+')');
 console.log('\n=== Mascot: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();
