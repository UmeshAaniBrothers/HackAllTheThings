# **basic enumeration**

  
Linux  
cat /etc/issue; cat /etc/*-release; cat /etc/lsb-release; cat /etc/redhat-release;  
  
  
  
  

## Applications & Services

  
  
  
  
Sensitive info search  
  
w  
last  
cat /etc/passwd | cut -d: -f1 # List of users  
grep -v -E "^#" /etc/passwd | awk -F: '$3 == 0 { print $1}' # List of super users  
awk -F: '($3 == "0") {print}' /etc/passwd # List of super users  
cat /etc/sudoers  
sudo -l