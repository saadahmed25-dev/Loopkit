const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1500,height:950});
 const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
 await p.goto('http://127.0.0.1:8080/wp-admin/post.php?post='+process.argv[2]+'&action=loopkit',{waitUntil:'networkidle2'});
 await wait(3000);

 // record everything the editor announces
 await p.evaluate(()=>{
   window.__ev=[];
   ['loopkit:added','loopkit:deleted','loopkit:moved'].forEach(n=>
     document.addEventListener(n,e=>window.__ev.push({n:n,d:(e.detail||{})})));
 });
 const seen=()=>p.evaluate(()=>window.__ev.splice(0));
 const mood=()=>p.evaluate(()=>{const s=document.querySelector('.lkm');
   return s?((s.getAttribute('class')||'').match(/is-[a-z]+/)||[''])[0]:'no mascot';});

 console.log('=== The editor announces what it did ===');
 t(!!(await p.$('.lkm-dock')),'The mascot is in the builder');

 // add a heading through the editor's own function
 // window.LK is configuration, not an API. Drive the editor the way a
 // person does: double-click a widget in the panel.
 const addOne = async () => {
   // After selecting or deleting, the panel shows that element's settings,
   // so the widget list has to be brought back before it can be clicked.
   await p.evaluate(()=>{
     const back=document.querySelector('#lk-panel-back, .lk-panel-back, [data-panel="widgets"]');
     if(back) back.click();
   });
   await wait(500);
   const w = await p.$('.lk-widget-item[data-widget="heading"]');
   if(!w) throw new Error('widget list not reachable');
   await w.evaluate(e=>e.scrollIntoView({block:'center'}));
   await wait(200);
   await w.click({clickCount:2});
   await wait(900);
 };
 await addOne();
 let ev=await seen();
 console.log('  after adding:',JSON.stringify(ev));
 t(ev.filter(x=>x.n==='loopkit:added').length===1,
   'Adding a widget fires loopkit:added exactly once ('+ev.length+' events)');
 t(ev.some(x=>x.d&&x.d.action),'The event says what happened');
 t((await mood())==='is-happy','And the mascot is happy, not guessing from a drop');

 console.log('=== Deleting ===');
 await p.evaluate(()=>{ window.__ev=[]; });
 // Delete through the canvas handle, which is what a person clicks.
 const canvas = p.frames().find(f=>f.name()==='lk-canvas-iframe');
 const del = await canvas.evaluate(()=>{
   const btn=document.querySelector('.lk-element-handle .lk-handle-delete');
   if(!btn) return 'no delete button';
   btn.click(); return 'handle';
 });
 await wait(900);
 ev=await seen();
 console.log('  via '+del+':',JSON.stringify(ev));
 t(ev.filter(x=>x.n==='loopkit:deleted').length===1,
   'Deleting fires loopkit:deleted exactly once ('+ev.length+' events)');
 t((await mood())==='is-angry','The mascot objects');
 const bub=await p.evaluate(()=>{const bb=document.querySelector('.lkm-bubble');
   return {open:bb.classList.contains('is-open'),line:bb.querySelector('span').textContent};});
 t(bub.open && bub.line.length>5,'And says something: "'+bub.line+'"');

 // "One add, one event" is already answered by the first add — counting it
 // there avoids driving the panel a second time, which is UI plumbing, not
 // the thing under test.

 t(errs.length===0,'No JavaScript errors ('+errs.slice(0,1).join('')+')');
 console.log('\n=== Canvas events: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();
