@echo off
echo ============================================
echo Finding Your XAMPP Template Files
echo ============================================
echo.

echo Searching for index.php and submit.php in C:\xampp\htdocs...
echo.

if not exist "C:\xampp\htdocs" (
    echo ERROR: C:\xampp\htdocs not found!
    echo.
    echo Is XAMPP installed in a different location?
    echo Please check your XAMPP installation path.
    pause
    exit /b
)

echo Found C:\xampp\htdocs
echo.
echo Looking for your template files...
echo.

dir /s /b "C:\xampp\htdocs\index.php" 2>nul > temp_results.txt
dir /s /b "C:\xampp\htdocs\submit.php" 2>nul >> temp_results.txt

if not exist temp_results.txt (
    echo No files found.
    pause
    exit /b
)

for /f %%i in ("temp_results.txt") do set size=%%~zi
if %size%==0 (
    echo No index.php or submit.php files found in C:\xampp\htdocs
    echo.
    echo Please check where you saved your template files.
    del temp_results.txt
    pause
    exit /b
)

echo ============================================
echo FILES FOUND:
echo ============================================
echo.
type temp_results.txt
echo.
echo ============================================
echo.
echo These are the files you need to upload to cPanel!
echo.
echo Next steps:
echo 1. Note the folder path shown above
echo 2. Open DEPLOY-NOW.txt and follow the upload instructions
echo 3. Use File Manager in cPanel to upload these files
echo.

del temp_results.txt
pause
