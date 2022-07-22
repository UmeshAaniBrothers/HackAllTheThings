# **Step3-Run nmap Full port scan**

#!/usr/bin/bash  
  
while read cmd;  
do  
gnome-terminal --tab -e "$cmd"  
done  < cmd_tcp.txt