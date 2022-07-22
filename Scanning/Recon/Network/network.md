Network Scan  
  
# my preference  
nmap -sV -sC -v -oA output <targetip>  
nmap -p- -v <targetip>  
  
#full tcp scan  
nmap -sC -sV -p- -vv -oA full x.x.x.x  
nmap -sT -p- --min-rate x.x.x.x  
  
  
nmap -sV -sC -O -T4 -n -Pn -oA fastscan <IP>  
nmap -sV -sC -O -T4 -n -Pn -p- -oA fullfastscan <IP>  
nmap -p- --min-rate x.x.x.x  
  
nmap -vvv -A --reason --script="+(safe or default) and not broadcast" -p <port> <host  
nmap -p80 $ip –script http-put –script-args http-put.url=’/test/sicpwn.php’,httpput.le=’/var/www/html/sicpwn.php  
  
#AutoRecon  
ar -ct 4 -cs x.x.x.x  
ar -ct 4 -cs x.x.x.x  
ar -ct 4 -cs x.x.x.x  
python3 autorecon.py -ct 4 -cs x.x.x.x  
  
  
#Nmap Automator  
na ip All  
./nmapAutomator.sh x.x.x.x  
  
#onetwopunch  
/onetwopunch.sh -t targets -p all -n "-sV -O --version-intensity=9"  
  
#reconnoitre  
reconnoitre -t x.x.x.x  
reconnoitre -t ip --services --quick -o /root  
reconnoitre -t x.x.x.x  
  
  
#nikto  
nikto -host x.x.x.x[  
](https://infosecsanyam261.gitbook.io/tryharder/untitled)nikto -h ip  
  
  
For port knocking  
  
for x in 7000 8000 9000; do nmap -Pn --host_timeout 201 --max-retries 0 -p $x x.x.x.x  
  
  
#SNMP  
#SNMP-Check  
snmp-check x.x.x.x  
snmp-check $IP  
snmpcheck -t $IP -c public  
snmpcheck -t x.x.x.x  
  
#onesixtyone  
onesixtyone -c names -i hosts  
  
#SNMPWALK  
snmpwalk -c public -v1 $IP  
  
#SNMPENUM  
perl snmpenum.pl $IP public windows.txt  
  
#NMAP SCRIPTS  
nmap -p 88 --script krb5-enum-users --script-args krb5-enum-users.realm='domain.local',userdb=/usr/share/wordlists/SecLists/Usernames/top_shortlist.txt x.x.x.x  
nmap -vv -sV -sU -Pn -p 161,162 --script=snmp-netstat,snmp-processes $IP  
nmap -sU -p 161 --script /usr/share/nmap/scripts/snmp-win32-users.nse $IP