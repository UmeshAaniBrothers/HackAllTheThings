# **Enum Image**

![images\769-1.png](https://oscpnotes.infosecsanyam.in/images/769-1.png)  
  
Used to verify user in the machine  
#finger root@x.x.x.x  
#finger user@x.x.x.x  
#finger <username>@<ip>  
  
Username enumeration of the finger service. The finger protocol is used to get information about users on a remote system. In our case, we used it to enumerate usernames that we later used to SSH into the server. The remediation for this vulnerability would be to disable this service.  
  
Weak authentication credentials. After getting a username from the finger service, we ran a brute force attack on SSH to obtain a user’s credentials. The user should have used a sufficiently long password that is not easily crackable.