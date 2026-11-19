const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('fs');
const dir=process.env.QA_ARTIFACT_DIR+'/ui';
fs.mkdirSync(dir,{recursive:true});
const base=process.env.QA_BASE_URL;
const results=[]; const runtimeErrors=[];
function assert(value,message){if(!value)throw new Error(message);}
async function check(name,fn){try{const details=await fn();results.push({name,status:'passed',details});console.log('PASS',name,details?JSON.stringify(details):'');}catch(e){results.push({name,status:'failed',error:e.message});console.log('FAIL',name,e.message);}}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||(fs.existsSync('/usr/bin/chromium')?'/usr/bin/chromium':undefined),headless:true,args:['--no-sandbox']});
 const contexts={};
 async function logged(role){
  const context=await browser.newContext({viewport:{width:1440,height:1000},timezoneId:'UTC'});
  const page=await context.newPage();
  page.on('pageerror',e=>runtimeErrors.push({role,error:e.message,url:page.url()}));
  await page.goto(base+'/login');await page.locator('#email').fill(role+'1@equip.test');await page.locator('#password').fill('password');
  await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/login')),page.getByRole('button',{name:'Log in',exact:true}).click()]);
  contexts[role]={context,page};return page;
 }
 const guestContext=await browser.newContext({viewport:{width:375,height:812}}); const guest=await guestContext.newPage();
 await check('guest login mobile layout',async()=>{const r=await guest.goto(base+'/login');assert(r.status()===200,'login response '+r.status());assert(await guest.locator('#main-content').count()===1,'missing main landmark');const size=await guest.evaluate(()=>({vw:innerWidth,width:document.documentElement.scrollWidth}));assert(size.width<=size.vw+1,'page horizontal overflow '+JSON.stringify(size));await guest.screenshot({path:dir+'/login-mobile.png',fullPage:true});return size;});
 for(const role of ['student','faculty','manager','head','auditor']){
  await check(role+' login',async()=>{const page=await logged(role);return {landing:new URL(page.url()).pathname};});
 }
 const pages={student:['/dashboard','/equipment','/reservations','/documents/checklist','/profile'],faculty:['/dashboard','/equipment','/reservations'],manager:['/dashboard','/equipment','/queue','/maintenance'],head:['/department','/department/users','/department/labs','/maintenance']};
 for(const [role,paths] of Object.entries(pages)){
  const page=contexts[role]?.page;if(!page)continue;
  for(const path of paths){
   await check(role+' desktop '+path,async()=>{await page.setViewportSize({width:1440,height:1000});const r=await page.goto(base+path);assert(r.status()===200,'HTTP '+r.status());assert(await page.locator('nav[aria-label="Main navigation"]').isVisible(),'desktop sidebar hidden');const size=await page.evaluate(()=>({vw:innerWidth,width:document.documentElement.scrollWidth}));assert(size.width<=size.vw+1,'horizontal overflow '+JSON.stringify(size));return size;});
   await check(role+' 375px '+path, async () => {
    await page.setViewportSize({width:375,height:812});
    await page.goto(base+path);
    const menu = page.getByRole('button', {name:'Open navigation', exact:true});
    const nav = page.locator('nav[aria-label="Main navigation"]');
    const target = await menu.boundingBox();
    assert(target && target.width >= 44 && target.height >= 44, 'menu target must be at least 44 × 44 px');
    assert(!await nav.isVisible(), 'closed menu is visible');
    await menu.click();
    await nav.waitFor({state:'visible'});
    await page.waitForFunction(() => document.activeElement?.getAttribute('aria-label') === 'Close navigation');
    assert(await page.locator('main').evaluate(el => !!el.closest('[inert]')), 'background remains interactive');
    await page.keyboard.press('Escape');
    await nav.waitFor({state:'hidden'});
    await page.waitForFunction(() => document.activeElement?.getAttribute('aria-label') === 'Open navigation');
    const size = await page.evaluate(() => ({vw:innerWidth, width:document.documentElement.scrollWidth}));
    assert(size.width <= size.vw+1, 'page horizontal overflow '+JSON.stringify(size));
    await page.screenshot({path:dir+'/'+role+path.replaceAll('/','-')+'-mobile.png',fullPage:true});
    return size;
   });
  }
 }
 if(contexts.auditor){for(const path of ['/dashboard','/profile','/documents/checklist','/equipment'])await check('auditor forbidden '+path,async()=>{const r=await contexts.auditor.page.goto(base+path);assert(r.status()===403,'expected403 actual'+r.status());return {status:r.status()};});}
 const student=contexts.student?.page;
 if(student){
  await student.setViewportSize({width:1440,height:1000});
  await check('catalog filters expand and preserve focus on auto-submit', async () => {
   await student.goto(base+'/equipment?clear=1');
   const filters = student.locator('details[data-filter-disclosure]');
   const status = student.getByLabel('Equipment status');
   const clear = student.getByRole('link', {name:'Clear filter', exact:true});
   assert(!await status.isVisible(), 'optional filters are visible by default');
   assert(await clear.count() === 0, 'Clear filter shown without active filters');
   await filters.locator('summary').focus();
   await student.keyboard.press('Enter');
   await status.focus();
   await Promise.all([
    student.waitForURL(url => url.searchParams.get('status') === 'available'),
    status.selectOption('available'),
   ]);
   await student.waitForFunction(() => document.activeElement?.name === 'status');
   assert(await status.inputValue() === 'available', 'selected value not persisted');
   assert(await clear.count() === 1, 'Clear filter absent');
   assert(await student.locator('form[data-instant-filters]').getAttribute('method') === 'GET', 'not GET form');
   await Promise.all([
    student.waitForURL(url => url.searchParams.get('status') === ''),
    status.selectOption(''),
   ]);
   await student.waitForFunction(() => document.activeElement?.name === 'status');
   assert(await status.isVisible(), 'filter panel closed while resetting the focused filter');
  });
  await check('debounced search retains focus and caret',async()=>{await student.goto(base+'/equipment');const input=student.locator('form[data-instant-filters] [data-filter-search]');await input.fill('QA Browser');await input.evaluate(el=>el.setSelectionRange(4,4));await student.waitForURL(u=>u.searchParams.get('q')==='QA Browser');await student.waitForLoadState('networkidle');const focus=await student.locator('form[data-instant-filters] [data-filter-search]').evaluate(el=>({focused:document.activeElement===el,start:el.selectionStart,value:el.value}));assert(focus.focused,'focus lost after debounce');assert(focus.start===4,'caret not restored '+JSON.stringify(focus));return focus;});
  await check('Clear filter clears query and conditional link',async()=>{await Promise.all([student.waitForURL(u=>u.pathname==='/equipment'&&!u.search),student.getByRole('link',{name:'Clear filter',exact:true}).click()]);assert(await student.getByRole('link',{name:'Clear filter',exact:true}).count()===0,'clear link still visible');});
  await check('reservation default end and manual end preservation',async()=>{const r=await student.goto(base+'/equipment/1/reserve');assert(r.status()===200,'reserve HTTP'+r.status());const start=student.locator('[name=starts_at]'),end=student.locator('[name=ends_at]');assert(await start.getAttribute('min'),'past-date minimum missing');const time=new Date(Date.now()+10*86400000);time.setUTCHours(10,0,0,0);const str=d=>d.toISOString().slice(0,16);await start.fill(str(time));assert(await end.inputValue()===str(new Date(time.getTime()+3600000)),'end not start+1h');await end.fill(str(new Date(time.getTime()+7200000)));time.setUTCHours(9);await start.fill(str(time));assert(await end.inputValue()===str(new Date(time.getTime()+10800000)),'manual end overwritten');return {minimum:await start.getAttribute('min'),start:await start.inputValue(),end:await end.inputValue()};});
  await check('reservation browser conflict hint',async()=>{let found=false;for(let id=1;id<=100;id++){await student.goto(base+`/equipment/${id}/reserve`);const data=await student.locator('[data-booked-windows]').textContent();const windows=JSON.parse(data||'[]');if(!windows.length)continue;found=true;await student.locator('[name=starts_at]').fill(windows[0].start.slice(0,16));await student.locator('[name=ends_at]').fill(windows[0].end.slice(0,16));assert(await student.locator('#reservation-conflict').isVisible(),'conflict warning not visible');assert((await student.locator('#reservation-conflict').textContent()).includes('overlaps'),'missing conflict text');return {equipment:id,window:windows[0]};}assert(found,'No booked fixture found');});
  await check('flash accessible and dismiss button works',async()=>{await student.goto(base+'/profile');const form=student.locator('form').filter({has:student.locator('input[name=name]')});await Promise.all([student.waitForNavigation(),form.getByRole('button',{name:'Save',exact:true}).click()]);const flash=student.locator('[data-flash]');assert(await flash.isVisible(),'flash missing after profile save');assert(await flash.getAttribute('role')==='status','flash missing status role');assert(await flash.getAttribute('aria-live')==='polite','aria-live missing');await student.getByRole('button',{name:'Dismiss notification'}).click();assert(!(await flash.isVisible()),'flash did not dismiss');});
 }
 for(const [name,page,path] of [['login',guest,'/login'],['catalog',student,'/equipment'],['queue',contexts.manager?.page,'/queue'],['department',contexts.head?.page,'/department']]){
  if(!page)continue;await check('axe '+name,async()=>{await page.setViewportSize({width:1440,height:1000});await page.goto(base+path);const scan=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();fs.writeFileSync(dir+'/axe-'+name+'.json',JSON.stringify(scan,null,2));const violations=scan.violations.map(v=>({id:v.id,impact:v.impact,count:v.nodes.length,targets:v.nodes.flatMap(n=>n.target)}));assert(!violations.length,'Accessibility violations '+JSON.stringify(violations));return {violations:0};});
 }
 await check('no browser JavaScript exceptions',async()=>{assert(!runtimeErrors.length,JSON.stringify(runtimeErrors));return {errors:0};});
 fs.writeFileSync(dir+'/results.json',JSON.stringify({results,runtimeErrors},null,2));
 const summary={passed:results.filter(r=>r.status==='passed').length,failed:results.filter(r=>r.status==='failed').length};console.log('SUMMARY',JSON.stringify(summary));await browser.close();process.exitCode=summary.failed?1:0;
})().catch(e=>{console.error(e);process.exitCode=1;});
