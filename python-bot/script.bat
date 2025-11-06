@ECHO OFF
ECHO ------------Opening Chrome in debug mode-------------
REM Try to find Chrome in common locations
SET CHROME_PATH="C:\Program Files\Google\Chrome\Application\chrome.exe"
IF NOT EXIST %CHROME_PATH% SET CHROME_PATH="C:\Program Files\Google\Chrome\Application\chrome.exe"
IF NOT EXIST %CHROME_PATH% (
    ECHO Chrome not found in default locations. Please update CHROME_PATH in script.bat
    PAUSE
    EXIT /B 1
)

ECHO Starting Chrome with remote debugging...
START "" %CHROME_PATH% --remote-debugging-port=8989 --user-data-dir="C:\Users\ashut\Desktop\whatsapptest\whatsapplogs" "https://web.whatsapp.com/"

ECHO Waiting for Chrome to start...
TIMEOUT /T 5 /NOBREAK

ECHO Chrome should now be open with WhatsApp Web.
ECHO Keep this window open and run main.py in another terminal.
PAUSE
