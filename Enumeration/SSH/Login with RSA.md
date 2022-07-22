chmod 600 <ssh key>  
chmod 400 id_rsa  
ssh -i id_rsa hype@x.x.x.x  
ssh <user>@<ip> -i <private key> ****or***  
ssh -o StrictHostKeychecking=no -i <private key> <user>@<ip>