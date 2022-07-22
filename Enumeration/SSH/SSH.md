# **SSH**

  
  

## hydra -L wordlists/userlist -P wordlists/offsecpass -f -u

x.x.x.x  
  
ssh -i id_rsa hype@x.x.x.x  
  
  
_**

# Quick tip to remember is that you can chain sshuttle commands to reach a subnet within a subnet.

**_  
  

## sshuttle: where transparent proxy meets VPN meets ssh

  
  
**

### Obtaining sshuttle  
  
From PyPI:

**

###   
  
pip install sshuttle  
  

**

### Clone:

**

###   
  
git clone

[https://github.com/sshuttle/sshuttle.git](https://github.com/sshuttle/sshuttle.git)

###   
.  
/setup.py install



![](Screenshots/706-1.png)