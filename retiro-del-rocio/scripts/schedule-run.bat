@echo off
REM Runs Laravel's scheduler once. Invoked every minute by Windows Task Scheduler.
cd /d C:\Users\Imdanielubani\Desktop\retiro-del-rocio-hotel-managment
C:\php85\php.exe artisan schedule:run >nul 2>&1
