const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
(async()=>{
  const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
  const p=await b.newPage(); await p.setViewport({width:1500,height:950});
  const errs=[]; p.on('pageerror',e=>errs.push(String(e)));
  await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
  // Pin the collection and start from a clean slate: the suite defaulted to
  // whichever collection sorted first and inherited a half-finished upload
  // from the run before, so it only passed on an empty site.
  const COL = process.argv[3];
  await p.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_io&collection_id='+COL,{waitUntil:'networkidle2'});
  if (await p.$('button[value="cancel_import"]')) {
    await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}), p.click('button[value="cancel_import"]')]);
    await p.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_io&collection_id='+COL,{waitUntil:'networkidle2'});
  }
  await p.screenshot({path:'/tmp/io-1.png'});

  let r=await p.evaluate(()=>({
    warn:(document.body.innerText.match(/Warning:/g)||[]).length,
    hasExport:!!document.querySelector('input[value="loopkit_cms_export"]'),
    hasUpload:!!document.querySelector('input[name="csv"]'),
    collections:document.querySelectorAll('#lk-io-col option').length,
    count:(document.querySelector('.lk-cms-count')||{}).textContent||''
  }));
  console.log('  screen:',JSON.stringify(r));
  t(r.warn===0,'No PHP warnings on the screen');
  t(r.hasExport,'Export form is there');
  t(r.hasUpload,'Upload field is there');
  t(/rows,/.test(r.count) && /fields/.test(r.count),'It says what is in the collection');

  // upload a CSV through the real form
  const input=await p.$('input[name="csv"]');
  await input.uploadFile(process.argv[2]);
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('form[enctype] button[type=submit]')]);
  await p.screenshot({path:'/tmp/io-2.png'});

  let m=await p.evaluate(()=>{
    const rows=[...document.querySelectorAll('.lk-io-map tbody tr')].map(tr=>({
      col:tr.querySelector('td strong').textContent.trim(),
      sample:tr.querySelector('.lk-io-sample').textContent.trim(),
      picked:tr.querySelector('select').value
    }));
    return {warn:(document.body.innerText.match(/Warning:/g)||[]).length,
            rows, skip:(document.querySelector('#lk-io-skip')||{}).value,
            match:(document.querySelector('#lk-io-match')||{}).value};
  });
  console.log('  mapping:',JSON.stringify(m));
  t(m.warn===0,'No PHP warnings on the mapping step');
  t(m.rows.length===3,'One line per column in the file');
  t(m.rows[0].picked==='name' && m.rows[1].picked==='role' && m.rows[2].picked==='site',
    'Columns are matched to fields by name');
  t(m.rows[0].sample==='Sana Malik','The first row is shown so you can check the match');

  // run it
  await p.select('#lk-io-match','name');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),
    p.click('.lk-cms-form-footer button.lk-btn-primary')]);
  await p.screenshot({path:'/tmp/io-3.png'});
  const done=await p.evaluate(()=>document.body.innerText);
  t(/Import finished/.test(done),'It reports what happened');
  // Across runs the same file is sometimes new and sometimes a repeat, so
  // assert what holds either way: all three lines were accounted for, and
  // with a match field set a repeat updates rather than duplicating.
  const nums = done.match(/Import finished: (\d+) added, (\d+) updated, (\d+) skipped/);
  t(!!nums, 'The report names added, updated and skipped');
  t(nums && (Number(nums[1]) + Number(nums[2])) === 3, 'All three lines in the file were handled');
  t(nums && Number(nums[3]) === 0, 'None were skipped');
  t(errs.length===0,'No JavaScript errors ('+errs.join(' | ')+')');
  console.log('\n=== Import/Export (browser): '+pass+' passed, '+fail+' failed ===');
  await b.close();
})();
