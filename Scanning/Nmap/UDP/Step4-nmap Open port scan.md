# **Step4-nmap Open port scan**

#!/usr/bin/bash  
> final_udp_nmap.txt  
cmd=""  
while read ip;  
do  
cmd=$(cat $ip-udp.txt | grep "/udp"| grep -v "closed" | cut -d "/" -f1 | xargs | sed  's/ /,/g')  
if [$cmd -eq ""]  
then  
echo "$ip have no open port"  
else  
echo "nmap -n -Pn -g 53 -sV -sU -A -p $cmd --version-intensity 7 $ip -oN $ip/$ip-udp.txt"  >> final_udp_nmap.txt  
  
fi  
  
done < live_hosts.txt