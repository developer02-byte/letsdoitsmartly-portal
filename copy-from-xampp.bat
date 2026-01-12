@echo off
echo ============================================
echo Copy Template Files from XAMPP
echo ============================================
echo.

REM Set your XAMPP template folder path here
set XAMPP_PATH=C:\xampp\htdocs\template

echo Please edit this file and set XAMPP_PATH to your template folder location
echo Current XAMPP_PATH: %XAMPP_PATH%
echo.

if not exist "%XAMPP_PATH%" (
    echo ERROR: XAMPP path not found!
    echo.
    echo Please edit copy-from-xampp.bat and set the correct path
    echo.
    echo Common locations:
    echo   C:\xampp\htdocs\template
    echo   C:\xampp\htdocs\questionnaire
    echo   C:\xampp\htdocs\[your-project-name]
    echo.
    pause
    exit /b
)

echo Found XAMPP folder: %XAMPP_PATH%
echo.

if not exist "%XAMPP_PATH%\index.php" (
    echo ERROR: index.php not found in XAMPP folder
    pause
    exit /b
)

if not exist "%XAMPP_PATH%\submit.php" (
    echo ERROR: submit.php not found in XAMPP folder
    pause
    exit /b
)

echo Copying files...
copy "%XAMPP_PATH%\index.php" "%~dp0index.php"
copy "%XAMPP_PATH%\submit.php" "%~dp0submit.php"

echo.
echo ============================================
echo Files copied successfully!
echo ============================================
echo.
echo Next steps:
echo 1. Check if files need any configuration changes for cPanel
echo 2. Run: node quick-deploy.js
echo    OR upload manually via cPanel File Manager
echo.
pause
