const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  
  // Go directly to the admin area
  await p.goto('http://localhost/builder/wp-admin',{waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,3000));
  
  // Check title
  const title = await p.title();
  console.log('wp-admin title:', title);
  
  // Check cookies
  const cookies = await p.cookies();
  console.log('cookies:', cookies.length);
  cookies.forEach(c=>console.log('  -', c.name, '=', c.value.substring(0,30)));
  
  // Check for loopkit
  const hasLoopkit = await p.evaluate(()=>document.querySelector('script[src*="loopkit"]') !== null);
  console.log('has loopkit script:', hasLoopkit);
  
  // Check for canvas iframe
  const hasIframe = await p.$('#lk-canvas-iframe');
  console.log('iframe found:', !!hasIframe);
  
  await b.close();
})();