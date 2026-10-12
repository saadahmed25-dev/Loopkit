const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  await p.goto('http://127.0.0.1:8080/wp-admin/post.php?post=673&action=loopkit',{waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,5000));
  
  // Check page title
  console.log('page title:', await p.title());
  
  // Check for loopkit scripts
  const hasLoopkit = await p.evaluate(()=>document.querySelector('script[src*="loopkit"]') !== null);
  console.log('has loopkit script:', hasLoopkit);
  
  // Check for LK global
  const hasLK = await p.evaluate(()=>typeof LK !== 'undefined');
  console.log('LK global exists:', hasLK);
  
  // Check body for loopkit data
  const bodyHtml = await p.evaluate(()=>document.body.innerHTML);
  const hasLoopkitData = bodyHtml.includes('_loopkit_data');
  console.log('has _loopkit_data in body:', hasLoopkitData);
  
  // Check all frames
  const frames = p.frames();
  console.log('total frames:', frames.length);
  frames.forEach((f,i)=>console.log('frame'+i+': name=\"'+f.name()+'\", url=\"'+f.url().substring(0,80)+'\"'));
  
  // Check for #lk-canvas-iframe in DOM
  const iframeEl = await p.$('#lk-canvas-iframe');
  console.log('iframe element found:', !!iframeEl);
  if(iframeEl) console.log('iframe boundingBox:', await iframeEl.boundingBox());
  
  // Check page source for canvas URL
  const pageSource = await p.content();
  const hasCanvasUrl = pageSource.includes('loopkit_canvas');
  console.log('has loopkit_canvas in source:', hasCanvasUrl);
  
  // Look for any canvas-related elements
  const canvasDivs = await p.$$('[class*="canvas"]');
  console.log('canvas-related divs:', canvasDivs.length);
  
  await b.close();
})();