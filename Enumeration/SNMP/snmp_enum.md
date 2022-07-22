# **snmp_enum**

## == SNMP ==  
nmap -sU -p 161 --script=*snmp* x.x.x.x  
xprobe2 -v -p udp:161:open x.x.x.x  
  
  
msf > use auxiliary/scanner/snmp/snmp_login  
msf > use auxiliary/scanner/snmp/snmp_enum  
  
snmp-check x.x.x.x  
snmpget -v 1 -c public IP  
snmpwalk -v 1 -c public IP  
snmpbulkwalk -v2c -c public -Cn0 -Crx.x.x.x  
onesixtyone -c /usr/share/wordlists/dirb/small.txt x.x.x.x  
  
for i in $(cat /usr/share/wordlists/metasploit/unix_users.txt);do snmpwalk -v 1 -c $i x.x.x.x