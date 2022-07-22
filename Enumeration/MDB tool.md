# **MDB tool**

  
>mdb-sql backup.mdb  
>list tables  
>go  
  
or  
mtb-tables backup.mdb  
for i in $(mdb-tables backup.mdb);do echo $i; done  
for i in $(mdb-tables backup.mdb);do export-mdb backup.mdb $i > tables/$i; done  
wc -l *| sort -n {blank file with line 1 ignore them}  
  
readpst <pst file>  
less <mbox file>  
  
telnet ---> with creds from mbox