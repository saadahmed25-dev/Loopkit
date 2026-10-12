const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
(async () => {
  const b = await puppeteer.launch({executablePath: process.env.CHROME || chromePath(), args:['--no-sandbox','--disable-dev-shm-usage']});
  const p = await b.newPage(); await p.setViewport({width:1600,height:900});
  const errs=[]; p.on('pageerror', e=>errs.push(String(e)));
  await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}), p.click('#wp-submit')]);
  await p.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_items&collection_id='+process.argv[2],{waitUntil:'networkidle2'});

  const r = await p.evaluate(() => {
    const cells=[...document.querySelectorAll('.lk-cms-img-cell')];
    return cells.map(c => ({
      media: c.getAttribute('data-media'),
      icon: (c.querySelector('.lk-cms-img-btn')||{}).dataset?.icon,
      name: (c.querySelector('.lk-cms-media-name')||{}).textContent||'',
      hasImg: !!c.querySelector('img')
    }));
  });
  console.log('  cells:', JSON.stringify(r));
  t(r.length===4,'All four media cells render');
  t(r.some(x=>x.media==='image'),'image cell filters the library to images');
  t(r.some(x=>x.media==='image/svg+xml'),'SVG cell filters to image/svg+xml');
  t(r.some(x=>x.media==='video'),'video cell filters to video');
  t(r.some(x=>x.media==='audio'),'audio cell filters to audio');
  t(r.find(x=>x.media==='video').name==='promo-video.mp4','video shows the file name, not a broken image');
  t(r.find(x=>x.media==='audio').name==='theme-song.mp3','audio shows the file name');
  t(r.find(x=>x.media==='image/svg+xml').hasImg===true,'SVG shows the file itself');

  // the panel, opened by clicking — paintMedia must be reachable from there
  await p.click('.lk-items-table tbody tr:first-child .lk-cms-edit-item');
  await new Promise(r2=>setTimeout(r2,400));
  const e = await p.evaluate(() => {
    const ed=document.querySelector('.lk-cms-editor');
    return [...ed.querySelectorAll('.lk-cms-editor-img')].map(w=>({
      media: w.getAttribute('data-media'),
      choose: (w.querySelector('.lk-cms-editor-media')||{}).textContent||'',
      name: (w.querySelector('.lk-cms-media-name')||{}).textContent||''
    }));
  });
  console.log('  panel:', JSON.stringify(e));
  t(e.length===4,'The panel has all four controls');
  t(e.some(x=>x.choose.indexOf('SVG')>-1),'Its buttons say what they pick (Choose SVG)');
  t(e.some(x=>x.choose.indexOf('video')>-1),'Choose video');
  t(e.some(x=>x.choose.indexOf('audio')>-1),'Choose audio');
  // clear an audio control: this calls setEditorImage -> paintMedia across scopes
  await p.evaluate(() => {
    const w=[...document.querySelectorAll('.lk-cms-editor-img')].find(x=>x.getAttribute('data-media')==='audio');
    w.querySelector('.lk-cms-editor-media-clear').click();
  });
  await new Promise(r2=>setTimeout(r2,250));
  const cleared = await p.evaluate(() => {
    const w=[...document.querySelectorAll('.lk-cms-editor-img')].find(x=>x.getAttribute('data-media')==='audio');
    return { val: w.querySelector('.lk-cms-editor-img-input').value, has: w.classList.contains('has-value') };
  });
  t(cleared.val==='' && !cleared.has, 'Clearing works — paintMedia is reachable from setEditorImage');
  t(errs.length===0, 'No JavaScript errors on the page ('+errs.join(' | ')+')');
  console.log('\n=== Media types: '+pass+' passed, '+fail+' failed ===');
  await b.close(); process.exit(fail?1:0);
})();
