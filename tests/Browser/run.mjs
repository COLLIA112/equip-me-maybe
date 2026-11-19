// Repeatable browser checks use a fresh disposable database, never the configured app database.
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const artifacts=fs.mkdtempSync(path.join(os.tmpdir(),'equip-maybe-browser-'));
const port=process.env.QA_PORT||'8012';const base='http://127.0.0.1:'+port;
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',APP_KEY:'base64:'+randomBytes(32).toString('base64'),APP_URL:base,DB_CONNECTION:'sqlite',DB_DATABASE:path.join(artifacts,'browser.sqlite'),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',QUEUE_CONNECTION:'sync',MAIL_MAILER:'log',BCRYPT_ROUNDS:'4',QA_BASE_URL:base,QA_PROJECT_DIR:root,QA_ARTIFACT_DIR:artifacts};
fs.writeFileSync(env.DB_DATABASE,'');
function run(command,args,extra={}){const r=spawnSync(command,args,{cwd:root,env,encoding:'utf8',...extra});if(r.status!==0)throw Error(`${command} ${args.join(' ')}\n${r.stdout||''}${r.stderr||''}${r.error||''}`);return r.stdout;}
let server;let failed=false;
try{
 console.log(run('php',['artisan','migrate','--seed','--force']));
 fs.writeFileSync(path.join(artifacts,'fixtures.json'),run('php',['tests/Browser/fixtures.php']));
 const serverLog=fs.openSync(path.join(artifacts,'server.log'),'a');
 server=spawn('php',['-S','127.0.0.1:'+port,path.join(root,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(root,'public'),env,stdio:['ignore',serverLog,serverLog]});
 for(let i=0;i<100;i++){try{const r=await fetch(base+'/login');if(r.status===200)break;}catch{}if(i===99)throw Error('Browser server did not become ready: '+path.join(artifacts,'server.log'));await new Promise(r=>setTimeout(r,100));}
 for(const script of ['lifecycle.mjs','ui.cjs','extras.mjs']){const r=spawnSync(process.execPath,[path.join(root,'tests/Browser',script)],{cwd:root,env,encoding:'utf8',maxBuffer:10*1024*1024});fs.writeFileSync(path.join(artifacts,script+'.log'),(r.stdout||'')+(r.stderr||''));console.log(r.stdout||'');if(r.status!==0){console.error(r.stderr||'');failed=true;}}
}catch(e){console.error(e.message);failed=true;}
finally{server?.kill('SIGTERM');console.log('Browser artifacts: '+artifacts);process.exitCode=failed?1:0;}
