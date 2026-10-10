@echo off
rem Builds upload\zephryx-crm.zip and upload\public_html.zip for cPanel.
rem See docs\DEPLOY.md for what to do with them.
cd /d "%~dp0"
php deploy\build.php
echo.
pause
