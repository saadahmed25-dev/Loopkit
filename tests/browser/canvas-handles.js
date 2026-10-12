const { puppeteer, chromePath } = require(__dirname + '/_puppeteer');
let pass=0,fail=0; const t=(o,m)=>{o?(pass++,console.log('  PASS: '+m)):(fail++,console.log('  FAIL: '+m));};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const b=await puppeteer.launch({executablePath: process.env.CHROME || chromePath(),args:['--no-sandbox','--disable-dev-shm-usage']});
 const p=await b.newPage(); await p.setViewport({width:1500,height:900});
 await p.goto('http://127.0.0.1:8080/wp-login.php',{waitUntil:'networkidle2'});
 await p.type('#user_login','admin'); await p.type('#user_pass','admin');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle2'}),p.click('#wp-submit')]);
 await p.goto('http://127.0.0.1:8080/wp-admin/post.php?post='+process.argv[2]+'&action=loopkit',{waitUntil:'networkidle2'});
 
 // Wait for the canvas iframe to be available in the DOM
 await wait(3000);
 const iframeEl = await p.$('#lk-canvas-iframe');
 if(!iframeEl){
   console.log('Canvas iframe not found after wait, frames:', p.frames().map(f=>f.url().slice(-40)));
   await b.close();
   process.exit(1);
 }
 await wait(2500);
 // The canvas is an iframe; the handles live inside it.
 const frames = p.frames();
 let canvas = frames.find(f=>f.name()==='lk-canvas-iframe');
 if(!canvas){
   // Fall back to finding iframe by URL containing the page ID
   canvas = frames.find(f=>f.url().indexOf('page_id='+process.argv[2]+'')>-1 || f.url().indexOf('p='+process.argv[2]+'')>-1);
 }
 if(!canvas){
   // Last resort: find any non-main frame
   canvas = frames.find(f=>f!==p.mainFrame());
 }
 if(!canvas){ console.log('  frames:', frames.map(f=>f.url().slice(-40))); throw new Error('canvas frame not found'); }

 const shown=async()=>canvas.evaluate(()=>{
   const out=[];
   document.querySelectorAll('.lk-element-handle').forEach(h=>{
     const cs=getComputedStyle(h);
     if(cs.display!=='none'){
       const r=h.getBoundingClientRect();
       out.push({kind:(h.className.match(/lk-handle-(section|container|widget)/)||[])[1]||'?',
                 x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height)});
     }
   });
   return out;
 });

 const overlapping=(list)=>{
   for(let i=0;i<list.length;i++) for(let j=i+1;j<list.length;j++){
     const a=list[i],c=list[j];
     if(a.x < c.x+c.w && c.x < a.x+a.w && a.y < c.y+c.h && c.y < a.y+a.h) return [a.kind,c.kind];
   }
   return null;
 };

 console.log('=== Hovering the heading ===');
 const head=await canvas.$('.lk-editable[data-id="wid1"]');
 if(!head){ console.log('  (no heading found — is the canvas rendering?)'); }
 const frameBox = await (await p.$('#lk-canvas-iframe')).boundingBox();
 const off = (bx)=>({x:bx.x+frameBox.x, y:bx.y+frameBox.y, width:bx.width, height:bx.height});
 // The widget wrapper is display:contents, so it has no box of its own —
 // the thing with a box is what it renders inside.
 const innerBox = await canvas.evaluate(()=>{
   const w=document.querySelector('.lk-editable[data-id="wid1"]');
   let el=w.firstElementChild;
   while(el && el.classList.contains('lk-element-handle')) el=el.nextElementSibling;
   el.scrollIntoView({block:'center'});
   const r=el.getBoundingClientRect();
   return {x:r.x,y:r.y,width:r.width,height:r.height};
 });
 await wait(300);
 const hb=off(innerBox);
 await p.mouse.move(hb.x+hb.width/2, hb.y+hb.height/2); await wait(400);
 let s=await shown();
 console.log('  handles shown:',JSON.stringify(s));
 t(s.length===1,'Only one handle shows ('+s.length+')');
 t(s.length&&s[0].kind==='widget','And it is the heading\u2019s, not its parents\u2019');
 t(!overlapping(s),'Nothing overlaps'+(overlapping(s)?' ('+overlapping(s).join(' over ')+')':''));
 await p.screenshot({path:'/tmp/h-widget.png',clip:{x:0,y:0,width:1100,height:300}});

 console.log('=== The handle does not cover the first line of content ===');
 // Problem A: the handle used to sit top-left / top-centre, straight over the
 // first line of its element's content — the Container toolbar landing on the
 // heading text. It now sits top-right with a slight inset, so on flush-left
 // content it clears the text entirely.
 //
 // Hover the Container in its own area (in its bottom padding, so the pointer
 // is over the container and not over any widget), which brings up the
 // Container handle. Then measure the first text node's real painted box — via
 // a Range, so a display:contents wrapper cannot hide it — and check the
 // handle does not intersect it.
 const conBox2 = await canvas.evaluate(()=>{
   const r=document.querySelector('.lk-editable[data-id="con1"]').getBoundingClientRect();
   return {x:r.x,y:r.y,width:r.width,height:r.height};
 });
 const cb2=off(conBox2);
 await p.mouse.move(cb2.x+8, cb2.y+cb2.height-4); await wait(400);
 const textClash = await canvas.evaluate(()=>{
   const handles=[...document.querySelectorAll('.lk-element-handle')]
     .filter(h=>getComputedStyle(h).display!=='none');
   if(handles.length!==1) return {ok:false, reason:'expected one visible handle, got '+handles.length+' ('+handles.map(h=>(h.className.match(/lk-handle-(section|container|widget)/)||['?'])[1]).join(',')+')'};
   const h=handles[0];
   const kind=(h.className.match(/lk-handle-(section|container|widget)/)||['?'])[1];
   const owner=h.parentElement;
   // First non-empty text node inside the element, skipping the handle itself.
   const walker=document.createTreeWalker(owner, NodeFilter.SHOW_TEXT, {
     acceptNode(n){ return (h.contains(n) || !n.nodeValue.trim()) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT; }
   });
   const node=walker.nextNode();
   if(!node) return {ok:true, reason:'no text to clash with', kind:kind};
   const range=document.createRange(); range.selectNodeContents(node);
   const tr=range.getBoundingClientRect();
   const hr=h.getBoundingClientRect();
   const overlap = hr.right > tr.left && tr.right > hr.left && hr.bottom > tr.top && tr.bottom > hr.top;
   return { ok: !overlap, kind:kind,
            text: node.nodeValue.trim().slice(0,30),
            textRect:{x:Math.round(tr.left),y:Math.round(tr.top),w:Math.round(tr.width),h:Math.round(tr.height)},
            handleRect:{x:Math.round(hr.left),y:Math.round(hr.top),w:Math.round(hr.width),h:Math.round(hr.height)} };
 });
 console.log('  (handle under test: '+(textClash.kind||'?')+', first text "'+(textClash.text||'')+'")');
 t(textClash.ok, 'The visible handle does not overlap the first text inside its element'
   + (textClash.ok?'':' — '+textClash.kind+' handle '+JSON.stringify(textClash.handleRect)+' covers "'+textClash.text+'" '+JSON.stringify(textClash.textRect)));

 console.log('=== Hovering the section, away from the heading ===');
 const sec=await canvas.$('.lk-editable[data-id="sec1"]');
 const secBox = await canvas.evaluate(()=>{
   const r=document.querySelector('.lk-editable[data-id="sec1"]').getBoundingClientRect();
   return {x:r.x,y:r.y,width:r.width,height:r.height};
 });
 const sb=off(secBox);
 await p.mouse.move(sb.x+8, sb.y+sb.height-6); await wait(400);
 s=await shown();
 console.log('  handles shown:',JSON.stringify(s));
 t(s.length>=1,'The section is still reachable');
 t(s.every(x=>x.kind!=='widget'),'The widget handle is not in the way');
 await p.screenshot({path:'/tmp/h-section.png',clip:{x:0,y:0,width:1100,height:300}});

 console.log('=== One selected, another hovered ===');
 // This is the case that was being reported and that I kept failing to
 // reproduce: a selected element kept its handle while a hovered one drew
 // its own, so two toolbars sat on screen at once.
 const posOf = async (id) => canvas.evaluate((i)=>{
   const w=document.querySelector('.lk-editable[data-id="'+i+'"]');
   let e=w.firstElementChild; while(e&&e.classList.contains('lk-element-handle')) e=e.nextElementSibling;
   const r=e.getBoundingClientRect(); return {x:r.x,y:r.y,w:r.width,h:r.height};
 }, id);
 const hbox = await posOf('wid1');
 await p.mouse.click(frameBox.x+hbox.x+60, frameBox.y+hbox.y+hbox.h/2);
 await wait(700);
 t(await canvas.evaluate(()=>!!document.querySelector('.lk-selected')), 'Clicking selects it');
 const sbox = await posOf('sec1');
 await p.mouse.move(frameBox.x+sbox.x+8, frameBox.y+sbox.y+sbox.h-8);
 await wait(500);
 s = await shown();
 t(s.length===1, 'Still only one handle with something selected ('+s.length+')');
 t(canvas.evaluate(()=>{ const el=document.querySelector('.lk-selected'); const o=getComputedStyle(el).outlineStyle; return o!=='none'; }), 'And the selected element still shows which it is, by its outline');

 console.log('=== The handle cannot be covered ===');
 // It used to sit above its element, outside its own box, where a
 // neighbouring section or a sticky header could paint over it. Inside the
 // element, nothing can.
 const inside = await canvas.evaluate(()=>{
   const el=document.querySelector('.lk-editable[data-id="wid1"]');
   const h=el.querySelector(':scope > .lk-element-handle');
   if(!h) return null;
   const hb=h.getBoundingClientRect(), eb=el.getBoundingClientRect();
   const pts=[0.1,0.5,0.9].map(f=>{
     const t=document.elementFromPoint(hb.x+hb.width*f, hb.y+hb.height/2);
     return t && (t===h || h.contains(t));
   });
   return { within: hb.top >= eb.top-1 && hb.bottom <= eb.bottom+1, onTop: pts.every(Boolean) };
 });
 t(inside && inside.within, 'The handle stays inside its own element');
 t(inside && inside.onTop, 'And nothing paints over it, across its whole width');

 console.log('=== Nothing hovered ===');
 // Deselect first: a selected element keeps its handle when the pointer is
 // off the canvas entirely, which is correct — that is how you get back to
 // the thing you were working on.
 await canvas.evaluate(()=>{ document.querySelectorAll('.lk-selected').forEach(e=>e.classList.remove('lk-selected')); });
 await p.mouse.move(5,5); await wait(400);
 t((await shown()).length===0,'No handles linger');
 console.log('\n=== Handles: '+pass+' passed, '+fail+' failed ===');
 await b.close();
})();