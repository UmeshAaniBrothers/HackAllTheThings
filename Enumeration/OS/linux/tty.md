# **tty**

python -c 'import pty;pty.spawn("/bin/bash")'  
  
python -c 'import pty; pty.spawn("/bin/bash")' -> stty raw -echo -> fg -> export TERM=xterm  
  
![images\518-1.png](https://oscpnotes.infosecsanyam.in/images/518-1.png)  
  
echo os.system('/bin/bash')  
/bin/sh -i  
  
  
# Enter while in reverse shell  
$ python -c 'import pty; pty.spawn("/bin/bash")'  
  
Ctrl-Z  
  
# In Kali  
$ stty raw -echo  
$ fg  
  
# In reverse shell  
$ reset  
$ export SHELL=bash  
$ export TERM=xterm-256color  
$ stty rows <num> columns <cols>