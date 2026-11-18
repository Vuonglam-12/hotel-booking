// Owns every process, database, asset, cache and profile created by this focused run.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),crypto=require('node:crypto');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const exec=(command,args,options={})=>new Promise((resolve,reject)=>{
    const child=spawn(command,args,{cwd:root,windowsHide:true,stdio:['ignore','pipe','pipe'],...options});let output='';
    child.stdout.on('data',chunk=>{output+=chunk;if(options.print)process.stdout.write(chunk);});child.stderr.on('data',chunk=>{output+=chunk;if(options.print)process.stderr.write(chunk);});
    child.on('error',reject);child.on('exit',code=>code===0?resolve(output):reject(new Error(command+' exited '+code+'\n'+output.slice(-6000))));
});
(async()=>{
    if(['--experience-crud','--guides-crud','--guides-ui','--guides-editor','--data-safety'].filter(mode=>process.argv.includes(mode)).length>1)throw new Error('Choose one focused test mode.');
    const mysqld=process.argv[2];if(!mysqld||!path.isAbsolute(mysqld)||!fs.existsSync(mysqld))throw new Error('Pass an explicit absolute mysqld executable.');
    const nonce=crypto.randomBytes(6).toString('hex'),runtime=path.join(fs.realpathSync(os.tmpdir()),'aurel-catalog-mysql-'+nonce);
    fs.mkdirSync(runtime);for(const dir of ['data','views','mysql-tmp','uploads'])fs.mkdirSync(path.join(runtime,dir));
    const reservation=net.createServer();await new Promise(resolve=>reservation.listen(0,'127.0.0.1',resolve));const port=reservation.address().port;await new Promise(resolve=>reservation.close(resolve));
    if(port<10000||port===3306)throw new Error('Unsafe fixture port');
    const stage={synthetic_only:true,nonce,runtime,datadir:path.join(runtime,'data'),host:'127.0.0.1',port,database:'aurel_catalog_test_'+nonce,
        username:'aurel_'+nonce,password:crypto.randomBytes(24).toString('hex'),owner_token:crypto.randomBytes(32).toString('hex')};
    const descriptor=path.join(runtime,'stage.json');fs.writeFileSync(descriptor,JSON.stringify(stage,null,2));fs.writeFileSync(path.join(runtime,'owner.txt'),stage.owner_token);
    const bootstrapPassword=crypto.randomBytes(24).toString('hex'),rootPassword=crypto.randomBytes(24).toString('hex');
    fs.writeFileSync(path.join(runtime,'bootstrap-secret.json'),JSON.stringify({password:bootstrapPassword}));
    fs.writeFileSync(path.join(runtime,'root-init.sql'),"CREATE USER 'runner_bootstrap'@'127.0.0.1' IDENTIFIED BY '"+bootstrapPassword+"';\nGRANT ALL PRIVILEGES ON *.* TO 'runner_bootstrap'@'127.0.0.1' WITH GRANT OPTION;\nALTER USER 'root'@'localhost' IDENTIFIED BY '"+rootPassword+"';\n");
    const env={...process.env,AUREL_CATALOG_MYSQL_STAGE:descriptor};const basedir=path.dirname(path.dirname(mysqld));
    console.log('Owned MySQL runtime: '+runtime+' | port '+port+' | database '+stage.database);
    const common=['--no-defaults','--basedir='+basedir,'--datadir='+stage.datadir,'--log-error='+path.join(runtime,'mysql.log')];
    await exec(mysqld,[...common,'--initialize-insecure']);console.log('Fresh data directory initialized; no project MySQL configuration loaded.');
    const serverArgs=[...common,'--bind-address=127.0.0.1','--port='+port,'--mysqlx=OFF','--skip-log-bin','--skip-name-resolve','--local-infile=0',
        '--event-scheduler=OFF','--secure-file-priv=NULL','--innodb-buffer-pool-size=64M','--performance-schema=OFF','--tmpdir='+path.join(runtime,'mysql-tmp'),
        '--pid-file='+path.join(runtime,'mysql.pid'),'--init-file='+path.join(runtime,'root-init.sql')];
    const server=spawn(mysqld,serverArgs,{cwd:runtime,windowsHide:true,stdio:['ignore','pipe','pipe']});
    let exited=false;const closed=new Promise(resolve=>server.on('exit',(code,signal)=>{exited=true;resolve({code,signal});}));server.on('error',error=>console.error(error.message));
    fs.writeFileSync(path.join(runtime,'process.json'),JSON.stringify({pid:server.pid,executable:mysqld,args:serverArgs},null,2));
    let provisioned=false,status='FAIL',failure='';
    try{
        for(let i=0;i<100;i++){
            if(exited)throw new Error('Owned MySQL exited during startup: '+fs.readFileSync(path.join(runtime,'mysql.log'),'utf8').slice(-4000));
            const ready=await new Promise(resolve=>{const socket=net.createConnection({host:'127.0.0.1',port});socket.once('connect',()=>{socket.destroy();resolve(true);});socket.once('error',()=>resolve(false));});
            if(ready)break;if(i===99)throw new Error('Owned MySQL startup timeout');await delay(200);
        }
        await exec('php',['tests/Support/catalog_mysql_control.php','provision'],{env,print:true});provisioned=true;
        const experienceCrud=process.argv.includes('--experience-crud');
        const guidesUi=process.argv.includes('--guides-ui');
        const guidesEditor=process.argv.includes('--guides-editor');
        const guidesCrud=process.argv.includes('--guides-crud')||guidesUi||guidesEditor;
        await exec('php',['tests/Support/catalog_mysql_control.php',experienceCrud?'experience-migrations':guidesCrud?'guide-migrations':'migrations'],{env,print:true});
        const dataSafety=process.argv.includes('--data-safety');
        const phpunitArgs=['vendor/bin/phpunit','-c',dataSafety?'phpunit.catalog-safety-mysql.xml':guidesCrud?'phpunit.guides-mysql.xml':'phpunit.catalog-mysql.xml'];
        if(experienceCrud)phpunitArgs.push('--filter','test_experience_crud_');
        if(guidesUi)phpunitArgs.push('--filter','test_guide_archive_|test_guide_publication_time_');
        if(guidesEditor)phpunitArgs.push('--filter','test_publication_validation_and_html_sanitization');
        const output=await exec('php',phpunitArgs,{env,print:true});fs.writeFileSync(path.join(runtime,'phpunit.txt'),output);
        if(experienceCrud)await exec('node',['tests/Frontend/experiences.browser.cjs'],{env,print:true});
        if(guidesCrud)await exec('node',['tests/Frontend/guides.browser.cjs',...(guidesUi?['--archive-datetime']:guidesEditor?['--editor-toolbar']:[])],{env,print:true});
        if(!dataSafety&&!experienceCrud&&!guidesCrud){
            await exec('php',['tests/Support/catalog_mysql_control.php','browser-fixture'],{env,print:true});
            await exec('node',['tests/Frontend/catalog-mysql.browser.cjs'],{env,print:true});
        }
        status='PASS';
    }catch(error){failure=error.stack;throw error;}
    finally{
        if(!exited&&provisioned)await exec('php',['tests/Support/catalog_mysql_control.php','shutdown'],{env,print:true}).catch(error=>{failure+='\n'+error.message;});
        if(!exited)await Promise.race([closed,delay(12000)]);
        if(!exited){server.kill();await closed;}
        const result=await closed;
        fs.writeFileSync(path.join(runtime,'run-report.json'),JSON.stringify({status,failure,synthetic_only:true,database:stage.database,port,runtime,
            server_pid:server.pid,server_stopped:exited,exit:result,project_env_loaded:false,production_connection_available:false},null,2));
        console.log('MySQL integration artifacts: '+runtime+' | stopped='+exited+' | '+status);
    }
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
