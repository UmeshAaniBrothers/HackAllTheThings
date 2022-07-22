# **wfuzz**

wfuzz -w /usr/share/seclists/Discovery/Web_Content/common.txt --hc 400,404,500 [http://x.x.x.x/FUZZ](http://x.x.x.x/FUZZ)  
wfuzz -w /usr/share/seclists/Discovery/Web_Content/quickhits.txt --hc 400,404,500 [http://x.x.x.x/FUZZ](http://x.x.x.x/FUZZ)  
wfuzz -c -z range,1-65535 --hl=2 [http://](http://10.10.10.55:60000/url.php?path=1)x.x.x.x  
wfuzz -c -w /usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt --hh 158607 [http://bart.htb/FUZZ](http://bart.htb/FUZZ)