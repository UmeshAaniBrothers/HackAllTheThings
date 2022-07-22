# **samba version**

Samba version -  
  
ngrep -i -d tap0 's.?a.?m.?b.?a.*[[:digit:]]'  
smbclient -L ip -U "" -N  
ngrep -i -d tap0 's.?a.?m.?b.?a.*[[:digit:]]' && smbclient -L ip -U "" -N  
  
  
[https://github.com/rewardone/OSCPRepo/blob/master/scripts/recon_enum/smbver.sh](https://github.com/rewardone/OSCPRepo/blob/master/scripts/recon_enum/smbver.sh)