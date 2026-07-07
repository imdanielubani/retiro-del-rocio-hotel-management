' Launches schedule-run.bat with no visible console window (0 = hidden).
Set sh = CreateObject("WScript.Shell")
sh.Run Chr(34) & "C:\xampp\htdocs\retiro-del-rocio\scripts\schedule-run.bat" & Chr(34), 0, False
Set sh = Nothing
