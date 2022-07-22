# **jenkin**

Jenking groovy code  
Testing to see if we have code execution…  
  
def sout = new StringBuffer(), serr = new StringBuffer()  
def proc = 'powershell.exe $PSVERSIONTABLE'.execute()  
proc.consumeProcessOutput(sout, serr)  
proc.waitForOrKill(x.x.x.x  
println "out> $sout err> $serr"