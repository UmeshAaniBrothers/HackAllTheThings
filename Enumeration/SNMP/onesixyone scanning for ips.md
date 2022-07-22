# **onesixyone scanning for ips**

# echo public > community  
# echo private >> community  
# echo manager >> community  
# onesixtyone -c community -i live_ip.txt  
  
  
# onesixtyone -c community -i live_ip.txt > snmp_ip.txt  
# cat snmp_ip.txt | cut -d " " -f1