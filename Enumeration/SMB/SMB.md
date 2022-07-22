# **SMB**

  
  
# mount SMB share to a afolder  
mount -t auto --source //x.x.x.x  
  
  
Note**** note the comments which get listed while listing smb share like smbclient //<ip>/share exist in /etc/  
Follow the file mapping based on http files & smb files listing becuase putting file in a certain folder might get accessible from LFI in webbrowser :)  
  
  
***NOte if get access with creds to a certain domain then tryupload there  
  

## == SMB NETBIOS==  
enum4linux x.x.x.x  
nmap -v -p 139,445 -oG smb.txt x.x.x.x  
nbtscan -r x.x.x.x  
nmblookup -A target  
smbclient //x.x.x.x  
rpcclient -U "" target // connect as blank user /nobody  
smbmap -u "" -p "" -d MYGROUP -H <target ip>  
  
  
== NetBIOS NullSession enumeration ==  
# This feature exists to allow unauthenticated machines to obtain browse lists from other  
# Microsoft servers. Enum4linux is a wrapper built on top of smbclient,rpcclient, net and nmblookup  
enum4linux -a x.x.x.x  
  
## upload file  
smbclient //x.x.x.x  

  

## Windows null session:  
C:\>net use \\TARGET\IPC$ “” /u:””  
Use acccheck for getting user pass using smb  
#acccheck -v -t x.x.x.x  
#acccheck -t x.x.x.x  
Once you got user creds we will use the creds to see the shares using smbmap  
#smbmap -u <user_name> -p <password> -d <domain> -H <IP>  
#smbmap -u user -p pass -d workgroup -H x.x.x.x  
#smbmap -L -u user -p pass -d workgroup -H x.x.x.x  
If you have only read privilege read the shares  
#smbmap -r -u user -p pass -d workgroup -H

x.x.x.x