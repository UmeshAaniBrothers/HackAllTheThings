# **Check smb version**

  
#!/bin/sh  
#Author: rewardone  
#Description:  
# Requires root or enough permissions to use tcpdump  
# Will listen for the first 8 packets of a null login  
# and grab the SMB Version  
#Notes:  
# Will sometimes not capture or will print multiple  
# lines. May need to run a second time for success.  
if [ -z $1 ]; then echo "Usage: ./smbver.sh RHOST {RPORT}" && exit; else rhost=$1; fi  
if [ ! -z $2 ]; then rport=$2; else rport=139; fi  
tcpdump -s0 -n -i tap0 src $rhost and port $rport -A -c x.x.x.x  
echo "exit" | smbclient -L $rhost 1>/dev/null 2>/dev/null  
echo "" && sleep .1  
  
----------------------------------------------------------or---------------------------------------------------  
smbclient -L <target ip>  
ngrep -i -d tap0 's.?a.?m.?b.?a.*[[:digit:]]'