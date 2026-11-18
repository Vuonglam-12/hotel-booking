/* Loopback-only SMTP acceptance sink; recipients are synthetic. Never relays email. */
const net=require('node:net'),fs=require('node:fs'),path=require('node:path');
const runtime=path.resolve(process.argv[2]); if(!/roomtype-v2-tests[\\/][a-f0-9]{12}$/.test(runtime))throw Error('Owned runtime required');
const server=net.createServer(socket=>{
    let buffer='',data=false,lines=[],recipient='';socket.setTimeout(15000,()=>socket.destroy());socket.write('220 isolated.example.test ESMTP capture\r\n');
    socket.on('data',chunk=>{buffer+=chunk.toString();let i;while((i=buffer.indexOf('\r\n'))>=0){const line=buffer.slice(0,i);buffer=buffer.slice(i+2);
        if(data){if(line==='.') {const message=lines.join('\r\n');const file=path.join(runtime,'smtp-captured.eml');fs.writeFileSync(file,message);fs.writeFileSync(path.join(runtime,'smtp-capture-result.json'),JSON.stringify({recipient,accepted:true,synthetic_only:true,bytes:Buffer.byteLength(message)}));data=false;lines=[];socket.write('250 2.0.0 accepted\r\n');}else lines.push(line.replace(/^\.\./,'.'));continue;}
        if(/^EHLO|^HELO/i.test(line))socket.write('250-isolated.example.test\r\n250 8BITMIME\r\n');
        else if(/^MAIL FROM:/i.test(line))socket.write('250 OK\r\n');
        else if(/^RCPT TO:/i.test(line)){recipient=line.slice(8).trim().replace(/[<>]/g,'');socket.write(recipient.endsWith('@example.test')?'250 OK\r\n':'550 synthetic recipients only\r\n');}
        else if(line==='DATA'){data=true;socket.write('354 end with dot\r\n');}
        else if(line==='QUIT'){socket.end('221 bye\r\n');}
        else if(line==='RSET'||line==='NOOP')socket.write('250 OK\r\n');else socket.write('502 unsupported\r\n');
    }});
});
server.listen(0,'127.0.0.1',()=>process.stdout.write('PORT='+server.address().port+'\n'));
setTimeout(()=>{server.close();process.exit(0);},45000).unref();
