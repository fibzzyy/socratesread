@echo off
setlocal
set "XAMPP_DIR=C:\xampp"
set "TARGET=%XAMPP_DIR%\htdocs\socratesread"
set "REPO=https://github.com/fibzzyy/socratesread.git"
if not exist "%XAMPP_DIR%\htdocs" (
  echo XAMPP htdocs was not found at "%XAMPP_DIR%\htdocs".
  echo Edit XAMPP_DIR in this file if XAMPP is installed elsewhere.
  pause
  exit /b 1
)
if exist "%TARGET%" (
  echo Target already exists: "%TARGET%"
  echo Nothing was overwritten. Move or rename it yourself before cloning again.
  pause
  exit /b 1
)
where git >nul 2>nul || (echo Git is required but was not found on PATH.& pause & exit /b 1)
git clone "%REPO%" "%TARGET%" || (echo Clone failed.& pause & exit /b 1)
echo.
echo Project cloned to "%TARGET%".
echo Enable extension=mbstring in %%XAMPP_DIR%%\php\php.ini and restart Apache if needed.
echo Then open a terminal in the project folder and run: composer setup
pause