# **WFuzz**

  

## wfuzz -w wordlist.txt --filter "c=200 and l>0"

[http://IP_ADDR/somepath.php?url=FUZZ](http://ip_addr/somepath.php?url=FUZZ)  
  

## wfuzz -w wordlist.txt --filter "c=200 and l>0"

[http://](http://192.168.153.157/image.php?secrettier360=FUZZ)x.x.x.x  
  

## wfuzz -w wordlist.txt --filter "c=200 and l>0"

[http://ip/nibbleblog/index.php?controller=FUZZ](http://10.10.10.75/nibbleblog/index.php?controller=FUZZ)  
  
brute Web with Wfuzz:  
  
wfuzz -c -w </path/os/pass/list> -d "username"=FUZZ&password=FUZZ" --hs "No account found/error" [http://<ip>/login.php](http://%3Cip%3E/login.php)  
  
-c = color  
-hs = hide  
  
  
  

## wfuzz -w wordlist.txt --filter "c=200 and l>0"

x.x.x.x