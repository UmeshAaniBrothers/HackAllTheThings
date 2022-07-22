# **SSH Tunneling / Pivoting**

  
  
**

## sshuttle  


**

###   
sshuttle -vvr user@x.x.x.x  
  
  

**

## Local port forwarding

**

###   
  
ssh <gateway> -L <local port to listen>:<remote host>:<remote port>  
  
  

**

## Remote port forwarding

**

###   
  
ssh <gateway> -R <remote port to bind>:<local host>:<local port>  
  
  

**

## Dynamic port forwarding

**

###   
  
ssh -D <local proxy port> -p <remote port> <target>  
  
  

**

## Plink local port forwarding

**

###   
  
plink -l root -pw pass -R 3389:<localhost>:3389 <remote host>