/**
 * The editor panel, driven the way a person drives it: by clicking.
 *
 * The panel is one element reused for every row, so anything the server
 * renders only when ?item_id= is in the URL never appears for someone
 * clicking Edit. Asserting against that URL passed while the real path
 * was broken, so this suite clicks.
 *
 * Usage: CHROME=/path/to/chrome node verify-editor-browser.js <collection_id>
 */
const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass = 0, fail = 0;
const t = (ok, msg) => { if (ok) { pass++; console.log('  PASS: ' + msg); } else { fail++; console.log('  FAIL: ' + msg); } };

(async () => {
  const b = await puppeteer.launch({executablePath: process.env.CHROME || chromePath(), args:['--no-sandbox','--disable-dev-shm-usage']});
  const p = await b.newPage(); await p.setViewport({width:1500,height:900});
  await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
  await p.type('#user_login','admin'); await p.type('#user_pass','admin');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}), p.click('#wp-submit')]);
  await p.goto('http://127.0.0.1:8080/wp-admin/admin.php?page=loopkit_cms_items&collection_id='+process.argv[2],{waitUntil:'networkidle2'});

  const snap = () => p.evaluate(() => {
    const vis = el => { if(!el) return false; const s=getComputedStyle(el), r=el.getBoundingClientRect();
      return s.visibility==='visible' && s.display!=='none' && r.width>0; };
    const ed=document.querySelector('.lk-cms-editor');
    const prev=ed.querySelector('.lk-step-prev'), next=ed.querySelector('.lk-step-next');
    return { open: ed.classList.contains('is-open'),
      itemId:(ed.querySelector('[name="item_id"]')||{}).value||'',
      stepper: vis(ed.querySelector('.lk-editor-step')),
      pos:(ed.querySelector('.lk-editor-step-pos')||{}).textContent||'',
      prevOff: prev?prev.classList.contains('is-off'):null,
      nextOff: next?next.classList.contains('is-off'):null,
      actions: vis(ed.querySelector('.lk-editor-foot-right')),
      dup:(ed.querySelector('.lk-cms-dup')||{}).value||'',
      del:(ed.querySelector('.delete-item')||{}).value||'',
      title:(ed.querySelector('[name="data[title]"]')||{}).value||'' };
  });
  const wait = () => new Promise(r=>setTimeout(r,250));

  console.log('=== A brand new row has neither ===');
  let z = await p.evaluate(() => {
    const d = el => el ? getComputedStyle(el).display : 'missing';
    const ed = document.querySelector('.lk-cms-editor');
    return { step: d(ed.querySelector('.lk-editor-step')), foot: d(ed.querySelector('.lk-editor-foot-right')) };
  });
  t(z.step === 'none', 'Add row shows no stepper — there is no row to step from');
  t(z.foot === 'none', 'Add row shows no Delete — there is nothing to delete yet');

  console.log('=== Opening the panel by clicking Edit ===');
  await p.click('.lk-items-table tbody tr:nth-child(2) .lk-cms-edit-item'); await wait();
  let s = await snap();
  t(s.open, 'Panel opens');
  t(s.stepper, 'The stepper is there — not only on a ?item_id= load');
  t(s.actions, 'Duplicate and Delete are there too');
  t(s.pos === '2 of 3', 'It says which row you are on (2 of 3)');
  t(s.title === 'Row two', "The row's values are loaded");
  t(s.dup === 'duplicate:' + s.itemId && s.del === 'delete:' + s.itemId, 'The actions point at this row');

  console.log('=== Stepping with the arrows ===');
  await p.click('.lk-cms-editor .lk-step-next'); await wait();
  const s2 = await snap();
  t(s2.pos === '3 of 3' && s2.itemId !== s.itemId, 'Next moves to the row below');
  t(s2.title === 'Row one', 'And loads its values');
  t(s2.nextOff === true, 'At the end the forward arrow goes off');
  t(s2.dup === 'duplicate:' + s2.itemId, 'The actions follow to the new row');
  await p.click('.lk-cms-editor .lk-step-next'); await wait();
  t((await snap()).itemId === s2.itemId, 'The off arrow does nothing');
  await p.click('.lk-cms-editor .lk-step-prev');
  await p.click('.lk-cms-editor .lk-step-prev'); await wait();
  const s3 = await snap();
  t(s3.pos === '1 of 3' && s3.prevOff === true, 'Back at the top the back arrow goes off');
  t(s3.title === 'Row three', 'With that row loaded');

  // Nothing below may change the data: this suite ran twice in a row and the
  // second run failed because its own last step had added a row.
  console.log('\n=== Editor (browser): ' + pass + ' passed, ' + fail + ' failed ===');
  await b.close();
  process.exit(fail ? 1 : 0);
})();
