const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
const SET='http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit-settings&tab=general';
const CMS='http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_collections';
(async()=>{
 const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1400,height:950});
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
 const there=async()=>{ await p.goto(CMS,{waitUntil:'networkidle2'}); await wait(350);
   return !!(await p.$('.lkm-dock')); };
 const setTo=async(on)=>{
   await p.goto(SET,{waitUntil:'networkidle2'}); await wait(300);
   const cur=await p.$eval('input[name="loopkit_mascot"]',e=>e.checked);
   if(cur!==on) await p.click('input[name="loopkit_mascot"]');
   await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),
     p.click('form button[type=submit], form input[type=submit]')]);
   await wait(300);
 };

 console.log('=== The switch exists ===');
 await p.goto(SET,{waitUntil:'networkidle2'}); await wait(300);
 t(!!(await p.$('input[name="loopkit_mascot"]')),'There is a mascot toggle in Settings → General');
 t(await p.$eval('input[name="loopkit_mascot"]',e=>e.checked),'On by default');
 await p.screenshot({path:'/tmp/toggle.png'});

 console.log('=== Turning it off and on from Settings ===');
 await setTo(false);
 t(!(await there()),'Off in settings, it is gone');
 await setTo(true);
 t(await there(),'On in settings, it is back');

 console.log('=== The real trap: dismissed in the browser, then switched on ===');
 await p.evaluate(()=>localStorage.setItem('loopkitMascot','off:'+Math.floor(Date.now()/1000)));
 t(!(await there()),'Dismissed from the mascot itself, it stays away');
 await wait(1100); // the stamp must be newer than the dismissal
 await setTo(false); await setTo(true);
 t(await there(),'Switching it on in Settings overrules that dismissal');
 const cleared=await p.evaluate(()=>localStorage.getItem('loopkitMascot'));
 t(cleared===null,'And the stale dismissal is cleared, not just ignored');

 console.log('=== It appears on the save itself, not a page later ===');
 // The original bug: the setting was written during the page render, after
 // the enqueue decision had already been made from the old value. Saving
 // "on" looked like it did nothing until you navigated somewhere else.
 await p.evaluate(()=>localStorage.setItem('loopkitMascot','off')); // old plain format
 await setTo(false);
 await p.goto(SET,{waitUntil:'networkidle2'}); await wait(300);
 await p.click('input[name="loopkit_mascot"]');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),
   p.click('form button[type=submit], form input[type=submit]')]);
 await wait(400);
 const onSave = await p.evaluate(()=>({
   dock:!!document.querySelector('.lkm-dock'),
   script:!!document.querySelector('script[src*="loopkit-mascot"]'),
   ls:localStorage.getItem('loopkitMascot'),
   onAt: window.loopkitMascot ? window.loopkitMascot.onAt : null
 }));
 console.log('  on the save page:',JSON.stringify(onSave));
 t(onSave.script,'The script loads on the very request that saved it');
 t(onSave.dock,'And the mascot is on that page, not the next one');
 t(onSave.ls===null,'A dismissal in the old plain format is cleared too');
 t(parseInt(onSave.onAt,10)>0,'The switch-on is stamped');

 console.log('\n=== Mascot toggle: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();
