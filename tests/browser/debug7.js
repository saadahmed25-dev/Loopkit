const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  
  // Directly go to the LoopKit editor page
  await p.goto('http://localhost/builder/wp-admin/post.php?post=673&action=loopkit',{waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,5000));
  
  // Check page title
  const title = await p.title();
  console.log('page title:', title);
  
  // Check for loopkit scripts
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
  
  // Check for LK global
  const hasLK = await p.evaluate(()=>typeof LK !== 'undefined');
  console.log('LK global exists:', hasLK);
  if(hasLK) console.log('LK.canvasUrl:', LK?.canvasUrl);
  
  await b.close();
})();