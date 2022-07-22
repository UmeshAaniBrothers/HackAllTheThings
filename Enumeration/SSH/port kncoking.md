# **port kncoking**

crunch 3 3 -p ' 0 ' ' 7 ' ' 2350 ' ' 43 ' > knock.txt  
IFS=$'\n';for i in $(cat knock.txt);echo knock $i -d 500;done