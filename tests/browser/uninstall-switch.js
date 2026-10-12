const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
const ADV='http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit-settings&tab=advanced';
(async()=>{
 const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1400,height:1000});
 const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
 await p.goto(ADV,{waitUntil:'networkidle2'}); await wait(400);
 const warn=await p.evaluate(()=>(document.body.innerText.match(/Warning:|Fatal/g)||[]).length);
 t(warn===0,'No PHP warnings on the Advanced tab');
 t(!!(await p.$('input[name="loopkit_remove_data_on_uninstall"]')),'The switch is there');
 t(!(await p.$eval('input[name="loopkit_remove_data_on_uninstall"]',e=>e.checked)),'Off by default');
 const help=await p.evaluate(()=>{const el=[...document.querySelectorAll('.lk-field-help')]
   .find(x=>/undo/i.test(x.textContent)); return el?el.textContent.trim():'';});
 t(/no undo/i.test(help),'It says plainly there is no undo');
 t(/pages and posts themselves are kept/i.test(help),'And what is kept');
 await p.screenshot({path:'/tmp/uninstall-sw.png'});
 // turn it on and confirm it sticks
 await p.click('input[name="loopkit_remove_data_on_uninstall"]');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),
   p.click('form button[type=submit], form input[type=submit]')]);
 await wait(400);
 t(await p.$eval('input[name="loopkit_remove_data_on_uninstall"]',e=>e.checked),'It saves');
 await p.click('input[name="loopkit_remove_data_on_uninstall"]');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),
   p.click('form button[type=submit], form input[type=submit]')]);
 await wait(400);
 t(!(await p.$eval('input[name="loopkit_remove_data_on_uninstall"]',e=>e.checked)),'And unsaves');
 t(errs.length===0,'No JavaScript errors');
 console.log('\n=== Uninstall switch: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();
