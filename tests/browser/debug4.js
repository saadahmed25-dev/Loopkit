const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:900});
  await p.goto('http://localhost/builder/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  await p.goto('http://localhost/builder/wp-admin/post.php?post=673&action=loopkit',{waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,5000));
  
  const hasIframe = await p.$('#lk-canvas-iframe');
  console.log('iframe found:', !!hasIframe);
  const frames = p.frames();
  console.log('total frames:', frames.length);
  frames.forEach((f,i)=>console.log('frame'+i+': name=\"'+f.name()+'\", url=\"'+f.url().substring(0,80)+'\"'));
  
  await b.close();
})();