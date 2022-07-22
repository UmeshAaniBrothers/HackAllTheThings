# **FinalScanPart1**

#!/usr/bin/bash  
  
mkdir mynmap;cp /root/oscp_notes/scanning/mynmapscan/* mynmap;cd mynmap;  
nmap -sn -g 53 -PI -oG live.txt $1  --exclude $2 &&  cat live.txt | grep "Host:"| cut -d " " -f2 > live_hosts.txt && ./list_tcp.sh && ./list_udp.sh &&  ./tcp.sh &&  ./udp.sh   
  
  
nmap -sn -g 53 -PI x.x.x.x  
  
autorecon.py -cs=1 -ct=1 --single-target x.x.x.x  
  
  
  
  
HTB:  
  
cat > live_hosts.txt   
mkdir mynmap;cp /root/oscp_notes/scanning/mynmapscan/* mynmap;cd mynmap;cp ../live_hosts.txt  .;  
./list_tcp.sh && ./list_udp.sh &&  ./tcp.sh &&  ./udp.sh   
  
autorecon.py -cs=1 -ct=1 -t mynmap/live_hosts.txt   
  
./tcp_port.sh  && ./nmap_tcp.sh && ./udp_port.sh &&./nmap_udp.sh