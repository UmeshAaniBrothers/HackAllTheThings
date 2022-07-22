# **wfuzz username**

  
wfuzz -c -z file,/root/Desktop/tools/SecLists/Usernames/Names/names.txt --hs "Try again" -d "username=FUZZ&password=anything" [http://](http://10.10.10.73/login.php)x.x.x.x