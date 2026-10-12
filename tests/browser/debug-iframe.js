const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  await p.goto('http://127.0.0.1:8080/wp-admin/post.php?post=673&action=loopkit',{waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,5000));
  // Check for canvas iframe
  const iframeEl = await p.$('#lk-canvas-iframe');
  console.log('iframe element found:', !!iframeEl);
  if(iframeEl) console.log('iframe boundingBox:', await iframeEl.boundingBox());
  // Check all frames
  const frames = p.frames();
  console.log('total frames:', frames.length);
  frames.forEach((f,i)=>console.log('frame'+i+': name=\"'+f.name()+'\", url=\"'+f.url().substring(0,80)+'\"'));
  // Check if #lk-canvas-iframe exists in body
  const hasIframe = await p.evaluate(()=>document.querySelector('#lk-canvas-iframe') !== null);
  console.log('body has lk-canvas-iframe:', hasIframe);
  // Check page title
  console.log('page title:', await p.title());
  // Check for LK global
  const hasLK = await p.evaluate(()=>typeof LK !== 'undefined');
  console.log('LK global exists:', hasLK);
  if(hasLK) console.log('LK.canvasUrl:', LK?.canvasUrl);
  await b.close();
})();