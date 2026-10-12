const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  await p.goto('http://localhost/builder/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  await new Promise(r=>setTimeout(r,3000));
  
  // Check if we're on the dashboard now
  const title = await p.title();
  console.log('page title:', title);
  
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
  
  // Check body for loopkit data
  const bodyHtml = await p.evaluate(()=>document.body.innerHTML);
  const hasLoopkitData = bodyHtml.includes('_loopkit_data');
  console.log('has _loopkit_data in body:', hasLoopkitData);
  
  await b.close();
})();