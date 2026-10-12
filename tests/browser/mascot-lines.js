const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1400,height:900});
 const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
 await p.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_collections',{waitUntil:'networkidle2'});
 await wait(400);

 console.log('=== Every widget has something to say ===');
 const tips=await p.evaluate(()=>window.loopkitMascot.tips);
 t(Object.keys(tips).length===72,'All 72 widgets have a line ('+Object.keys(tips).length+')');
 const short=Object.entries(tips).filter(([k,v])=>!v.tip||v.tip.length<40).map(([k])=>k);
 t(short.length===0,'None are throwaway one-liners'+(short.length?' ('+short.join(', ')+')':''));
 const real=['text-editor','contact-form','image-gallery','google-maps','image-carousel','flip-box','marquee','hotspot'];
 t(real.every(k=>tips[k]),'The keys match the registry, not invented ones');
 const labelled=Object.values(tips).filter(v=>v.label&&v.label.length).length;
 t(labelled===72,'Each carries its widget name as a heading');

 console.log('=== Looking at it too long ===');
 const box=await (await p.$('.lkm-dock .lkm')).boundingBox();
 await p.mouse.move(box.x+box.width/2, box.y+box.height/2);
 await wait(1200);
 t(!(await p.evaluate(()=>document.querySelector('.lkm-bubble').classList.contains('is-open'))),
   'A glance does not set it off');
 await wait(1800);
 let shy=await p.evaluate(()=>{const bb=document.querySelector('.lkm-bubble');
   return {open:bb.classList.contains('is-open'),head:bb.querySelector('b').textContent,
           line:bb.querySelector('span').textContent};});
 t(shy.open,'After about three seconds it says something');
 // The set grew when the line started repeating; match the shape, not a list,
 // or every new line breaks the test.
 t(shy.line.length>10 && shy.line!==shy.head,'And it is shy about it: "'+shy.line+'"');
 await p.screenshot({path:'/tmp/mas-shy.png'});

 // and again on the next long look, with a different line
 const first = shy.line;
 await p.click('.lkm-bubble-close'); await wait(300);
 await p.mouse.move(10,10); await wait(200);
 await p.mouse.move(box.x+box.width/2, box.y+box.height/2); await wait(3600);
 const again = await p.evaluate(()=>{const bb=document.querySelector('.lkm-bubble');
   return {open:bb.classList.contains('is-open'), line:bb.querySelector('span').textContent};});
 t(again.open,'It says it again on the next long look, not once a session');
 t(again.line!==first,'With a different line: "'+again.line+'"');
 // and it clears itself so the look after that starts fresh
 await p.mouse.move(10,10); await wait(3400);
 t(!(await p.evaluate(()=>document.querySelector('.lkm-bubble').classList.contains('is-open'))),
   'It lets go on its own rather than staying open');

 console.log('=== Deleting something ===');
 await p.evaluate(()=>document.dispatchEvent(new Event('loopkit:deleted'))); await wait(300);
 let del=await p.evaluate(()=>{const bb=document.querySelector('.lkm-bubble');
   return {open:bb.classList.contains('is-open'),line:bb.querySelector('span').textContent,
           angry:document.querySelector('.lkm').classList.contains('is-angry')};});
 t(del.open,'It speaks up');
 t(/deleting|did nothing|hope you meant|favourite/i.test(del.line),'It objects: "'+del.line+'"');
 t(del.angry,'And looks angry while it does');
 await p.screenshot({path:'/tmp/mas-delete.png'});
 await wait(3400);
 t(!(await p.evaluate(()=>document.querySelector('.lkm-bubble').classList.contains('is-open'))),
   'Then it lets it go, rather than sulking on screen');

 // a real delete button, not just the event
 // The button needs text, or it has no box and the click lands on the dock
 // above it — which is what made this fail the first time, not the product.
 await p.evaluate(()=>{
   const x=document.createElement('button');
   x.className='delete-item'; x.id='fake'; x.textContent='Delete';
   // Clear of the admin sidebar and above it: placed at left:20px the click
   // landed on a menu link instead and navigated away — the fixture's fault,
   // not the mascot's.
   x.style.cssText='position:fixed;left:600px;top:300px;z-index:99999';
   document.body.appendChild(x);
 });
 await p.click('#fake'); await wait(300);
 const after = await p.evaluate(()=>{
   const bb=document.querySelector('.lkm-bubble');
   return {dock:!!document.querySelector('.lkm-dock'), bubble:!!bb,
           open: bb ? bb.classList.contains('is-open') : null,
           line: bb ? bb.querySelector('span').textContent : ''};
 });
 console.log('  state:',JSON.stringify(after));
 t(after.open===true,'A real delete button sets it off too, not only the event');

 t(errs.length===0,'No JavaScript errors ('+errs.join(' | ')+')');
 console.log('\n=== Mascot lines: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();
