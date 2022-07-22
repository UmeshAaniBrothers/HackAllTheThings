# **Step2-nmap Full port scan**

#!/usr/bin/bash  
  
   
while read ip;  
do  
echo "nmap -n -Pn -sU -g 53 --stats-every 3m --max-retries 1  -T3 --top-ports x.x.x.x  
done < live_hosts.txt