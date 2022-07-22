# **gobuster**

gobuster -u [http://](http://10.10.10.56/)x.x.x.x  
gobuster -u [http://](http://10.10.10.56/cgi-bin/)x.x.x.x  
gobuster -u x.x.x.x -w /usr/share/seclists/Discovery/Web_Content/common.txt -t 20  
gobuster -u x.x.x.x -w /usr/share/seclists/Discovery/Web_Content/quickhits.txt -t 20  
gobuster -u x.x.x.x -w /usr/share/seclists/Discovery/Web_Content/common.txt -t 20 -x .txt,.php  
gobuster -s "200,204,301,302,307,403,500" -w /usr/share/seclists/Discovery/Web_Content/common.txt -u [http://](http:)  
gobuster -s "200,204,301,302,307,403,500" -u [http://XXXX](http://xxxx/) -w  
gobuster -u [http://](http://10.10.10.6/)x.x.x.x  
Gobuster comprehensive directory busting  
gobuster -s 200,204,301,302,307,403 -u x.x.x.x  
Gobuster quick directory busting  
gobuster -u x.x.x.x