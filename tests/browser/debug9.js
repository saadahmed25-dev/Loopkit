const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  
  // Go to the admin area
  await p.goto('http://localhost/builder/wp-login.php',{waitUntil:'networkidle2'});
  
  // Fill and submit login form
  await p.type('#user_login','admin');
  await p.type('#user_pass','admin');
  // Click submit and wait for navigation
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  await new Promise(r=>setTimeout(r,5000));
  
  // Check what URL we're at now
  const url = p.url();
  console.log('current url:', url);
  const title = await p.title();
  console.log('page title:', title);
  
  // Check cookies
  const cookies = await p.cookies();
  console.log('cookies after login:', cookies.length);
  cookies.forEach(c=>console.log('  -', c.name, '=', c.value ? c.value.substring(0,30) : '(empty)'));
  
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