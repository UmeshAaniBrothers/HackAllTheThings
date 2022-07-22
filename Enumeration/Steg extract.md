# **Steg extract**

sudo steghide embed -cf [FIRST FILE WHICH WILL EMBED THE HIDDEN FILES] -ef [FILES YOU WANT TO EMBED]  
[ENTER]  
Provide a password, but if you want no password, just hit [ENTER]  
-------------------------  
  
Extract  
  
Extracting information  
Now if you want to extract the hidden files, you simply need to provide the following command:  
  
sudo steghide extract -sf [FILENAME]  
  
The “sudo steghide info [FILENAME]” + [ENTER] command will show you the information which is hidden in the file.