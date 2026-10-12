const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  await p.goto('http://localhost/builder/wp-login.php',{waitUntil:'networkidle2'});
  
  // Check the login form
  const formAction = await p.evaluate(()=>document.querySelector('#loginform').action);
  console.log('form action:', formAction);
  
  // Check for nonce
  const nonce = await p.evaluate(()=>document.querySelector('input[name="log"]')?.getAttribute('value') || 'no login field');
  console.log('login field exists');
  
  // Submit the form
  await p.type('#user_login','admin');
  await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  await new Promise(r=>setTimeout(r,3000));
  
  // Check page title after login
  const title = await p.title();
  console.log('page title after login:', title);
  
  // Check for loopkit
  const hasLoopkit = await p.evaluate(()=>document.querySelector('script[src*="loopkit"]') !== null);
  console.log('has loopkit script:', hasLoopkit);
  
  // Check for canvas iframe
  const hasIframe = await p.$('#lk-canvas-iframe');
  console.log('iframe found:', !!hasIframe);
  
  // Check all frames
  const frames = p.frames();
  console.log('total frames:', frames.length);
  frames.forEach((f,i)=>console.log('frame'+i+': name=\"'+f.name()+'\", url=\"'+f.url().substring(0,80)+'\"'));
  
  await b.close();
})();