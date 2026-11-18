// One-shot Phase 5E backup. Main MySQL access is SELECT-only through the approved login file.
// No project .env, Admin credential, schema change, seed, importer, restore or permission grant.
const fs=require('node:fs'),fsp=fs.promises,path=require('node:path'),crypto=require('node:crypto');
const readline=require('node:readline');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const backup='C:\\Users\\ASUS\\AppData\\Local\\AUREL\\backups\\experience-phase5e-20261025-057fe2d6';
const source=path.join(root,'storage','app','public');
const loginFile='C:\\Users\\ASUS\\AppData\\Local\\AUREL\\mysql-readonly\\catalog-cd0b6853be264366a29e2e32f624b2c6\\catalog-ro.mylogin.cnf';
const bin='C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin';
const uuid='7880df8a-92cd-11f0-85c1-a841f442ddbd',account='aurel_catalog_ro@127.0.0.1';
const sqlFile=path.join(backup,'database','hotel_booking-20261025.sql');
const reportFile=path.join(backup,'evidence','backup-verification.json');
const sha=text=>crypto.createHash('sha256').update(text).digest('hex');
const qid=name=>'`'+name.replaceAll('`','``')+'`';
function env(){const result={...process.env,MYSQL_TEST_LOGIN_FILE:loginFile};delete result.MYSQL_PWD;return result;}
const connectionArgs=['--no-defaults','--login-path=aurel_catalog_ro','--protocol=TCP','--host=127.0.0.1','--port=3306',
    '--user=aurel_catalog_ro','--default-character-set=utf8mb4'];
function run(command,args,options={}){return new Promise((resolve,reject)=>{
    const child=spawn(command,args,{cwd:root,env:env(),windowsHide:true,stdio:['ignore','pipe','pipe'],...options});
    let stdout='',stderr='';child.stdout.on('data',c=>stdout+=c);child.stderr.on('data',c=>stderr+=c);
    child.on('error',()=>reject(new Error('Cannot start '+path.basename(command))));
    child.on('close',code=>resolve({code,stdout,stderr}));
});}
class Reader{
    constructor(){
        this.child=spawn(path.join(bin,'mysql.exe'),[...connectionArgs,'--connect-timeout=5','--database=hotel_booking','--local-infile=0',
            '--batch','--raw','--skip-column-names','--unbuffered'],{cwd:root,env:env(),windowsHide:true,stdio:['pipe','pipe','pipe']});
        this.stderr='';this.child.stderr.on('data',c=>this.stderr+=c);
        this.closed=new Promise(resolve=>this.child.on('close',code=>{this.exitCode=code;resolve(code);this.fail('RO client closed before its query finished.');}));
        this.child.on('error',()=>this.fail('RO client could not start.'));
        this.child.stdin.on('error',()=>this.fail('RO client input closed unexpectedly.'));
        readline.createInterface({input:this.child.stdout}).on('line',line=>{
            if(!this.pending)return;
            if(line===this.pending.marker){const p=this.pending;this.pending=null;clearTimeout(p.timer);p.resolve(p.lines);}
            else this.pending.lines.push(line);
        });
    }
    fail(message){if(this.pending){const p=this.pending;this.pending=null;clearTimeout(p.timer);p.reject(new Error(message));}}
    query(sql){
        const statement=sql.trim().replace(/;$/,'');
        if(!/^(SELECT|SHOW)\b/i.test(statement)||statement.includes(';')||this.pending)throw new Error('RO query boundary rejected.');
        return new Promise((resolve,reject)=>{
            const marker='phase5e_'+crypto.randomBytes(12).toString('hex');
            const timer=setTimeout(()=>{this.fail('RO query timed out.');this.child.kill();},15000);
            this.pending={marker,lines:[],resolve,reject,timer};
            this.child.stdin.write(statement+";\nSELECT '"+marker+"';\n");
        });
    }
    async json(sql){return (await this.query(sql)).map(line=>JSON.parse(line));}
    async stop(){this.child.stdin.end();const code=await this.closed;if(code!==0)throw new Error('RO client exited unsuccessfully.');}
}
async function identity(reader){
    const [id]=await reader.json("SELECT JSON_OBJECT('database',DATABASE(),'account',CURRENT_USER(),'uuid',@@server_uuid,'version',VERSION(),'port',@@port,'role',CAST(CURRENT_ROLE() AS CHAR),'mandatory_roles',@@GLOBAL.mandatory_roles,'partial_revokes',@@GLOBAL.partial_revokes,'sql_mode',@@SESSION.sql_mode,'time_zone',@@SESSION.time_zone,'system_time_zone',@@system_time_zone,'observed_at',CAST(NOW() AS CHAR),'observed_utc',CAST(UTC_TIMESTAMP() AS CHAR))");
    const grants=(await reader.query('SHOW GRANTS')).sort();
    const expected=['GRANT USAGE ON *.* TO `aurel_catalog_ro`@`127.0.0.1`','GRANT SELECT ON `hotel\\_booking`.* TO `aurel_catalog_ro`@`127.0.0.1`'].sort();
    const [roles]=await reader.json("SELECT JSON_OBJECT('applicable',(SELECT COUNT(*) FROM information_schema.APPLICABLE_ROLES),'enabled',(SELECT COUNT(*) FROM information_schema.ENABLED_ROLES))");
    if(id.database!=='hotel_booking'||id.account!==account||id.uuid!==uuid||id.version!=='8.0.30'||Number(id.port)!==3306
        ||id.role!=='NONE'||id.mandatory_roles!==''||Number(id.partial_revokes)!==0||roles.applicable||roles.enabled
        ||JSON.stringify(grants)!==JSON.stringify(expected))throw new Error('Source identity or read-only grant mismatch; no fallback.');
    return {...id,grants,roles};
}
async function schema(reader){
    const tables=await reader.json("SELECT JSON_OBJECT('name',TABLE_NAME,'kind',TABLE_TYPE,'engine',ENGINE,'collation',TABLE_COLLATION) FROM information_schema.TABLES WHERE TABLE_SCHEMA='hotel_booking' ORDER BY TABLE_NAME");
    if(!tables.length||tables.some(t=>t.kind!=='BASE TABLE'||t.engine!=='InnoDB'))throw new Error('Source has a view or a non-InnoDB table; stop before the simple dump.');
    const columns=await reader.json("SELECT JSON_OBJECT('table',TABLE_NAME,'column',COLUMN_NAME,'ordinal',ORDINAL_POSITION,'type',COLUMN_TYPE,'nullable',IS_NULLABLE,'default',COLUMN_DEFAULT,'extra',EXTRA,'collation',COLLATION_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='hotel_booking' ORDER BY TABLE_NAME,ORDINAL_POSITION");
    const indexes=await reader.json("SELECT JSON_OBJECT('table',TABLE_NAME,'index',INDEX_NAME,'non_unique',NON_UNIQUE,'position',SEQ_IN_INDEX,'column',COLUMN_NAME,'prefix',SUB_PART,'type',INDEX_TYPE,'visible',IS_VISIBLE,'expression',EXPRESSION) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='hotel_booking' ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX");
    const foreignKeys=await reader.json("SELECT JSON_OBJECT('table',TABLE_NAME,'constraint',CONSTRAINT_NAME,'column',COLUMN_NAME,'ordinal',ORDINAL_POSITION,'referenced_schema',REFERENCED_TABLE_SCHEMA,'referenced_table',REFERENCED_TABLE_NAME,'referenced_column',REFERENCED_COLUMN_NAME) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='hotel_booking' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION");
    const definitions={tables,columns,indexes,foreign_keys:foreignKeys};return {...definitions,sha256:sha(JSON.stringify(definitions))};
}
async function catalog(reader,definitions){
    const result={};
    for(const table of ['location','attraction','hotel','hotel_image','migrations','attraction_image','guide_category','guide_post']){
        if(!definitions.tables.some(t=>t.name===table))continue;
        const names=definitions.columns.filter(c=>c.table===table).map(c=>c.column);
        if(!names.includes('id')||names.some(n=>!/^\w+$/.test(n)))throw new Error('Unexpected catalog identifier.');
        const fields=names.map(n=>"'"+n+"',"+qid(n)).join(',');
        const rows=await reader.query('SELECT JSON_OBJECT('+fields+') FROM '+qid(table)+' ORDER BY `id`');
        result[table]={rows:rows.length,sha256:sha(rows.join('\n')+'\n')};
    }
    return result;
}
async function hashFile(file){const h=crypto.createHash('sha256');for await(const chunk of fs.createReadStream(file))h.update(chunk);return h.digest('hex');}
async function inventory(directory,sub=''){
    const rows=[];
    const entries=(await fsp.readdir(path.join(directory,sub),{withFileTypes:true})).sort((a,b)=>a.name.localeCompare(b.name));
    for(const entry of entries){
        const relative=path.join(sub,entry.name),file=path.join(directory,relative),stat=await fsp.lstat(file);
        if(stat.isSymbolicLink())throw new Error('Reparse point found; no link traversal is allowed.');
        if(stat.isDirectory())rows.push(...await inventory(directory,relative));
        else if(stat.isFile())rows.push({path:relative.replaceAll('\\','/'),bytes:stat.size,mtime_ms:stat.mtimeMs,sha256:await hashFile(file)});
        else throw new Error('Unexpected source asset type.');
    }
    return rows;
}
function content(rows){return rows.map(({path,bytes,sha256})=>({path,bytes,sha256}));}
async function copyAssets(rows,from,to){
    await fsp.mkdir(to,{recursive:true});
    for(const row of rows){
        const target=path.resolve(to,row.path);if(!target.startsWith(path.resolve(to)+path.sep))throw new Error('Destination path escapes private backup.');
        await fsp.mkdir(path.dirname(target),{recursive:true});
        await fsp.copyFile(path.join(from,row.path),target,fs.constants.COPYFILE_EXCL);
        const stat=await fsp.stat(path.join(from,row.path));await fsp.utimes(target,stat.atime,stat.mtime);
    }
}
async function inspectSql(file,expectedNames){
    const decoder=new TextDecoder('utf-8',{fatal:true});let buffer='',lines=0,header=false,footer=false;
    const creates={},inserts={};
    function line(text){
        lines++;
        if(text.includes('\0'))throw new Error('SQL encoding contains a NUL byte.');
        if(/^-- MySQL dump/.test(text))header=true;
        if(/^-- Dump completed on /.test(text))footer=true;
        const insert=/^INSERT INTO `((?:``|[^`])+)`/.exec(text);
        if(insert){const name=insert[1].replaceAll('``','`');inserts[name]=(inserts[name]||0)+1;return;}
        const create=/^CREATE TABLE `((?:``|[^`])+)`/.exec(text);
        if(create){const name=create[1].replaceAll('``','`');creates[name]=(creates[name]||0)+1;}
        if(!/^\s*--/.test(text)&&(/^\s*(?:CREATE\s+DATABASE|USE\b|DROP\s+TABLE|LOCK\s+TABLES)/i.test(text)
            ||/\bSET\s+(?:@@)?GLOBAL\b/i.test(text)||/\bDEFINER\s*=/i.test(text)
            ||/`hotel_booking`\s*\.|\bhotel_booking\s*\./i.test(text)))throw new Error('SQL has an unexpected target/object statement; manual review required.');
    }
    for await(const chunk of fs.createReadStream(file)){
        buffer+=decoder.decode(chunk,{stream:true});let end;
        while((end=buffer.indexOf('\n'))!==-1){line(buffer.slice(0,end).replace(/\r$/,''));buffer=buffer.slice(end+1);}
    }
    buffer+=decoder.decode();if(buffer)line(buffer.replace(/\r$/,''));
    if(!header||!footer||JSON.stringify(Object.keys(creates).sort())!==JSON.stringify([...expectedNames].sort())
        ||Object.values(creates).some(n=>n!==1)||Object.keys(inserts).some(n=>!expectedNames.includes(n)))throw new Error('SQL header/footer or table inventory is incomplete.');
    return {strict_utf8:true,header_present:header,footer_present:footer,lines,create_table_count:Object.keys(creates).length,insert_rows_by_table:inserts,restore_to_empty_target_only:true};
}
async function writeJson(name,data){await fsp.writeFile(path.join(backup,'evidence',name),JSON.stringify(data,null,2)+'\n',{flag:'wx'});}

(async()=>{
    if(!process.argv.includes('--execute')){console.log('Prepared backup plan. Explicit --execute is required; no connection or backup file was created.');return;}
    if(root!=='C:\\laragon\\www\\Hotel-Booking'||fs.realpathSync(source)!==source||fs.realpathSync(backup)!==backup
        ||!(await fsp.stat(loginFile)).isFile()||fs.existsSync(sqlFile)||fs.existsSync(reportFile))throw new Error('Path/credential/overwrite guard rejected.');
    const acl=await run('powershell.exe',['-NoProfile','-NonInteractive','-Command',
        "$a=Get-Acl -LiteralPath '"+backup+"'; [PSCustomObject]@{protected=$a.AreAccessRulesProtected;rules=@($a.Access|ForEach-Object{[PSCustomObject]@{sid=$_.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value;rights=[string]$_.FileSystemRights;inherited=$_.IsInherited;type=[string]$_.AccessControlType}});free=(New-Object System.IO.DriveInfo('C:\\')).AvailableFreeSpace}|ConvertTo-Json -Depth 4"]);
    if(acl.code!==0)throw new Error('Cannot verify private ACL.');const protection=JSON.parse(acl.stdout);
    const allowed=['S-1-5-18','S-1-5-21-2873856134-3016355433-1345141024-1001'];
    if(!protection.protected||protection.rules.length!==2||protection.rules.some(r=>!allowed.includes(r.sid)||r.inherited||r.type!=='Allow'||r.rights!=='FullControl')||protection.free<1073741824)throw new Error('Backup ACL/free space guard rejected.');
    for(const child of ['database','storage-public','evidence'])if((await fsp.readdir(path.join(backup,child))).length)throw new Error('Expected an unused prepared backup directory.');
    const report={date:'2026-10-25',status:'RUNNING',backup_directory:backup,
        owner_metadata_attestation:{source:'Project owner, phpMyAdmin with appropriate privileges, latest conversation',triggers:0,routines:0,events:0,requeried:false},
        safety:{project_dotenv_read:false,admin_credentials_used:false,source_data_or_schema_written:false,grant_run:false,migration_seed_importer_run:false,restore_run:false}};
    const reader=new Reader();
    try{
        report.source_before=await identity(reader);console.log('PASS source target/account/SELECT-only guard.');
        const beforeSchema=await schema(reader),beforeCatalog=await catalog(reader,beforeSchema);
        report.source_schema_sha256=beforeSchema.sha256;report.catalog_baseline=beforeCatalog;
        await writeJson('source-schema.json',beforeSchema);
        const beforeAssets=await inventory(source);console.log('Storage baseline: '+beforeAssets.length+' files, '+beforeAssets.reduce((s,r)=>s+r.bytes,0)+' bytes.');
        const codeFiles=['app/Http/Controllers/Admin/AttractionController.php','app/Http/Controllers/ExperienceController.php','app/Http/Requests/SaveAttractionRequest.php',
            'app/Models/Attraction.php','app/Models/AttractionImage.php','app/Models/Location.php','app/Models/Hotel.php',
            'app/Services/Admin/AttractionService.php','app/Services/ExperienceCatalogService.php','app/Services/AdminAccommodationImageService.php',
            'app/Support/ExperienceSchema.php','app/Support/ExperienceCategory.php','app/Support/LocationIdentity.php','app/Support/AccommodationImage.php',
            'database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php','resources/views/admin/experiences.blade.php',
            'resources/views/admin/partials/experiences-script.blade.php','resources/views/experiences/index.blade.php','resources/views/experiences/index-script.blade.php',
            'resources/views/experiences/show.blade.php','resources/views/experiences/styles.blade.php','resources/views/experiences/unavailable.blade.php',
            'routes/web.php','routes/api.php','tools/backup-experience-phase5e.cjs'];
        const code=[];for(const relative of codeFiles){const stat=await fsp.stat(path.join(root,relative));code.push({path:relative,bytes:stat.size,mtime_ms:stat.mtimeMs,sha256:await hashFile(path.join(root,relative))});}
        await copyAssets(code,root,path.join(backup,'code-snapshot'));
        const args=[...connectionArgs,'--single-transaction','--quick','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF',
            '--hex-blob','--column-statistics=0','--skip-triggers','--skip-routines','--skip-events',
            '--skip-add-locks','--skip-add-drop-table','--skip-disable-keys','--skip-extended-insert','--result-file='+sqlFile,'hotel_booking'];
        const reservation=await fsp.open(sqlFile,'wx');await reservation.close();
        report.dump_started_at_utc=new Date().toISOString();
        const dump=await run(path.join(bin,'mysqldump.exe'),args);
        report.dump_finished_at_utc=new Date().toISOString();report.dump_exit_code=dump.code;
        report.dump_stderr_sha256=sha(dump.stderr);report.dump_stderr_present=Boolean(dump.stderr.trim());
        if(dump.code!==0||dump.stderr.trim())throw new Error('mysqldump did not finish cleanly; partial files are retained privately.');
        console.log('PASS mysqldump exit 0; no stderr.');
        await copyAssets(beforeAssets,source,path.join(backup,'storage-public'));
        const copied=await inventory(path.join(backup,'storage-public')),afterAssets=await inventory(source);
        if(JSON.stringify(content(copied))!==JSON.stringify(content(beforeAssets))||JSON.stringify(beforeAssets)!==JSON.stringify(afterAssets))throw new Error('Storage changed or the copy differs; backup is not verified.');
        const copiedCode=await inventory(path.join(backup,'code-snapshot'));
        if(JSON.stringify(content(copiedCode).sort((a,b)=>a.path.localeCompare(b.path)))!==JSON.stringify(content(code).sort((a,b)=>a.path.localeCompare(b.path))))throw new Error('Code snapshot differs.');
        for(const row of code)if(await hashFile(path.join(root,row.path))!==row.sha256)throw new Error('Code changed during backup.');
        const afterSchema=await schema(reader),afterCatalog=await catalog(reader,afterSchema);
        report.source_after=await identity(reader);
        if(beforeSchema.sha256!==afterSchema.sha256||JSON.stringify(beforeCatalog)!==JSON.stringify(afterCatalog))throw new Error('Source schema/catalog changed during backup; review the snapshot window.');
        const inspected=await inspectSql(sqlFile,beforeSchema.tables.map(t=>t.name));
        for(const [table,data] of Object.entries(beforeCatalog))if((inspected.insert_rows_by_table[table]||0)!==data.rows)throw new Error('Dump catalog row counts differ from the stable baseline.');
        const stat=await fsp.stat(sqlFile);if(!stat.size)throw new Error('Empty SQL dump.');
        report.database_dump={path:sqlFile,bytes:stat.size,sha256:await hashFile(sqlFile),validation:inspected};
        report.storage={source,backup:path.join(backup,'storage-public'),files:copied.length,bytes:copied.reduce((s,r)=>s+r.bytes,0),
            manifest_sha256:sha(JSON.stringify(content(copied))),all_copied_hashes_match:true,source_unchanged_before_after:true};
        report.code={files:code.length,backup:path.join(backup,'code-snapshot'),all_copied_hashes_match:true};
        report.stability={schema_unchanged:true,catalog_unchanged:true,images_unchanged:true,operational_maintenance_enforced:false};
        await writeJson('storage-manifest.json',{files:copied,source_before_after_unchanged:true});
        await writeJson('code-manifest.json',{files:code});
        await writeJson('dump-options.json',{executable:path.join(bin,'mysqldump.exe'),arguments:args,password_in_arguments:false,sql_payload_logged:false});
        report.status='PASS_BACKUP_FILE_VERIFICATION_RESTORE_PENDING';
        console.log('PASS SQL UTF-8/header/footer, '+inspected.create_table_count+' tables and catalog row counts.');
        console.log('PASS '+copied.length+' storage files: source/copy SHA-256 match; source stable.');
    }catch(error){report.status='FAIL_BACKUP_VERIFICATION';report.failure=error.message;throw error;}
    finally{
        try{await reader.stop();report.source_client_closed=true;}catch{report.source_client_closed=false;report.status='FAIL_BACKUP_VERIFICATION';}
        await fsp.writeFile(reportFile,JSON.stringify(report,null,2)+'\n',{flag:'wx'});
        console.log('Private backup evidence: '+reportFile);
    }
    if(report.status!=='PASS_BACKUP_FILE_VERIFICATION_RESTORE_PENDING')throw new Error('Backup verification failed.');
    console.log('PASS backup files verified; database restore has NOT been executed.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
