QUESTIONNAIRE TEMPLATE DEPLOYMENT INSTRUCTIONS
================================================

The questionnaire template files (index.php and submit.php) need to be deployed to:
https://letsdoitsmartly.com/portal/template/

OPTION 1: Manual Upload via cPanel File Manager
-------------------------------------------------
1. Login to cPanel at: https://bh-in-32.webhostbox.net:2083
   Username: letsdoitadmin
   Password: (your password)

2. Navigate to File Manager
3. Go to: /home4/letsdoitadmin/public_html/portal/template/
4. Upload the files:
   - index.php (Static Website Client Intake Questionnaire form)
   - submit.php (Form submission handler and summary display)

OPTION 2: Using FTP
-------------------
Host: ftp.letsdoitsmartly.com or bh-in-32.webhostbox.net
Username: letsdoitadmin
Port: 21
Directory: /public_html/portal/template/

OPTION 3: Using the deployment script
--------------------------------------
Place index.php and submit.php in this directory, then run:
node deploy-questionnaire.js index.php submit.php

VERIFICATION
------------
After upload, visit:
https://letsdoitsmartly.com/portal/template/

The questionnaire form should load and allow you to:
1. Fill out client intake information
2. Submit the form
3. View a formatted summary

FILES NEEDED
------------
Both files were provided in your message as documents. You can:
1. Save them from your editor/location where you have them
2. Copy them to this directory
3. Run the deployment script

The template directory has been created on the server and is ready to receive the files.
