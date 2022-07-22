# **web**

Web scan  
#Gobuster  
gobuster -u [http://](http://10.10.10.160/)ip -w /usr/share/wordlists/dirbuster/directory-list-lowercase-2.3-small.txt -x php -o scans/gobuster-root-80-php  
gobuster -u x.x.x.x  
gobuster -s 200,204,301,302,307,403 -u x.x.x.x  
gobuster dir -u x.x.x.x  
gobuster dir -u https://mysite.com/path/to/folder -c 'session=123456' -t 50 -w common-files.txt -x .php,.html  
gobuster -u http://x.x.x.x  
gobsuter dir -w wordlist -u [http://ip](http://ip/) -t x.x.x.x  
gobuster dir -w /usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt -l -t 30 -e -k -x .asp,.aspx,.txt -u [http://](http://10.10.10.93/)x.x.x.x  
  
# Gobuster  
gobuster -u <targetip> -w /usr/share/seclists/Discovery/Web_Content/common.txt -s '200,204,301,302,307,403,500' -e  
----------------------------------------------  
# nikto  
nıkto -h <targetip>  
----------------------------------------------  
# curl  
curl -v -X OPTIONS [http://<targetip>/test/](http://%3Ctargetip%3E/test/)  
curl --upload-file <file name> -v --url <url> -0 --http1.0  
  
#dirsearch  
`dirsearch -u http://`x.x.x.x  
`dirsearch -u http://`x.x.x.x  
`dirsearch -u http://`x.x.x.x  
d`irsearch -u http://`x.x.x.x  
`dirsearch -u http://`x.x.x.x  
python3 dirsearch.py -f -e html,php,tar.gz,txt,xml,zip -u http://x.x.x.x  
  
  
#Dirb  
dirb http://localhost:5000 ./docs/common.txt  
dirb [http://webscantest.com](http://webscantest.com/) /usr/share/dirb/wordlists/vulns/apache.txt  
dirb [http://](http://192.168.1.106/dvwa/)x.x.x.x  
dirb [http://](http://192.168.1.106/dvwa)x.x.x.x  
dirb [http://](http://192.168.1.106/dvwa)x.x.x.x  
dirb [http://](http://192.168.1.106/dvwa)x.x.x.x  
dirb [http://](http://192.168.1.221:8000/)x.x.x.x  
dirb [http://](http://192.168.0.7/cgi-bin/)x.x.x.x  
dirb http://target.com -a "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.x.x.x.x  
  
  
  
#WFUZZ  
wfuzz -c -w /usr/share/seclists/Discovery/Web_Content/common.txt --hc 404 $ip/FUZZ  
wfuzz -c -w /usr/share/seclists/Discovery/Web_Content/common.txt -R 3 --sc 200 $ip/FUZZ  
wfuzz -c -z file,/root/.ZAP/fuzzers/dirbuster/directory-list-2.3-big.txt --sc 200 [http://pegasus.dev:8088/FUZZ.php](http://pegasus.dev:8088/FUZZ.php)  
wfuzz --hw=1 --hh=3076 -w seclist_common_wordlist.txt [http://ip/FUZZ](http://ip/FUZZ)  
  
  
  
  
  
#CMS  
cmsmap [http://IP_ADDR](http://ip_addr/) -f (D,J…)  
  
droopescan scan drupal -u [http://IP_ADDR](http://ip_addr/)  
  
wpscan --url [http://IP_ADDR](http://ip_addr/) --enumerate u,p,t  
  
wpscan --url //dc-2 --enumerate p --enumerate t --enumerate u  
  
# wordpress  
wpscan --url [http://....](http://..../) --log  
wpscan --url [http://...](http://.../) --enumerate u --log  
wpscan --url [http://<targetip>](http://%3Ctargetip%3E/) --wordlist wordlist.txt --username example_username  
[http://....../wp-admin](http://....../wp-admin)  
[http://...../wp-content/uploads/2017/](http://...../wp-content/uploads/2017/10/file.png)x.x.x.x  
  
  
joomscan -u [http://](http://192.168.1.102:8081/)x.x.x.x  
  
#port knock  
for x in 7000 8000 9000; do nmap -Pn –host_timeout 201 –max-retries 0 -p $x server_ip_address; done  
  
#wordlist  
/usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt  
/usr/share/wordlists/rockyou.txt  
/usr/share/dirb/wordlists/big.txt  
/usr/share/seclists/Web-Shells