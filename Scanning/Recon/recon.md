netdiscover -r x.x.x.x  
  
Network Scan  
  
# my preference  
nmap -sV -sC -v -oA output <targetip>  
nmap -p- -v <targetip>  
  
  
nmap -sV -sC -O -T4 -n -Pn -oA fastscan <IP>  
nmap -sV -sC -O -T4 -n -Pn -p- -oA fullfastscan <IP>  
nmap -p- --min-rate x.x.x.x  
  
  
python3 autorecon.py -ct 4 -cs x.x.x.x  
  
  
nikto -host x.x.x.x[  
](https://infosecsanyam261.gitbook.io/tryharder/untitled)  
  
Web scan  
gobuster -u [http://](http://10.10.10.160/)ip -w /usr/share/wordlists/dirbuster/directory-list-lowercase-2.3-small.txt -x php -o scans/gobuster-root-80-php  
  
  
  
  
CMS  
cmsmap [http://IP_ADDR](http://ip_addr/) -f (D,J…)  
  
droopescan scan drupal -u [http://IP_ADDR](http://ip_addr/)  
  
wpscan --url [http://IP_ADDR](http://ip_addr/) --enumerate u,p,t