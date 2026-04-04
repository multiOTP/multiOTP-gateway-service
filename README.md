# multiOTP gateway service

multiOTP gateway service is an open source gateway to send push notification from multiOTP server to multiOTP token App, provided as is by SysCo systèmes de communication sa.

(c) 2025-2026 SysCo systemes de communication sa  
https://www.multiotp.net/

Current build: 5.10.2.3 (2026-04-04)

[![Donate via PayPal](https://img.shields.io/badge/donate-paypal-87ceeb.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=USD&business=paypal@sysco.ch&item_name=Donation%20for%20multiOTP%20project)
*Please consider supporting this project by making a donation via [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=USD&business=paypal@sysco.ch&item_name=Donation%20for%20multiOTP%20project)*

In order to use push notification with the open source version of multiOTP, you will have to run your own multiOTP gateway service, and you will have also to compile your own multiOTP token App for Android and iOS, as you will have to use your own Google or Apple keys in order to send notifications.

# .env config file
You will have to adapt the .env file for at least the folloving values:

## Application
```
APP_NAME=your-gateway-service
APP_ENV=development
APP_KEY=base64:MDAwMDAwMDA=
APP_DEBUG=true
APP_URL=https://your.push.server
```

## Database
```
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=push
DB_USERNAME_READ=push_read
DB_PASSWORD_READ=push_read_password
DB_USERNAME_WRITE=push_write
DB_PASSWORD_WRITE=push_write_password
```

## Firebase
```
FIREBASE_SERVICE_ACCOUNT_FILE_PATH=your-firebase-key-file.json
FIREBASE_GOOGLE_APIS_MESSAGE_SEND_URL=https://fcm.googleapis.com/v1/projects/your-project/messages:send
NOTIFICATION_BODY=your-notification-body
```

## iOS service
```
IOS_SERVICE_ACCOUNT_FILE_PATH=your-ios-authkey-file.p8
IOS_KEY_ID=YOUR-IOS-KEY-ID
IOS_TEAM_ID=YOUR-IOS-TEAM-ID
IOS_BUNDLE_ID=your.ios.bundle.id
```

# Websocket PHP configuration file
Change the URL of your push server (https://your.push.server) here : `/websocket/src/config.php`

# Authentication files

## Firebase key file
Put your Firebase key (json) here : `/push/src/storage/app/private/`

## iOS service account file
Add your Apple key (p8) here : `/push/src/storage/app/private/`

# Vendors packages installation
* Go into /push/src/ and run `npm install`
* Go into /websocket/src/ and run `npm install`

# Database initialization (and database schema update after source update)
* Go into /push/src/ and run `php artisan migrate`

# Database initialization of the cleaning event (in root)
After database creation, create event in order to delete exports older then 10 minutes

```
SET GLOBAL event_scheduler = ON;
```

```
CREATE EVENT delete_old_exports ON SCHEDULE EVERY 5 MINUTE DO DELETE FROM exports WHERE created_at < NOW() - INTERVAL 10 MINUTE;
```

# Install the websocket service
Create the file `/etc/systemd/system/ws.server.service` with the following content:

```
[Unit]
Description=WS server PHP Script Service
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/php /path/to/the/ws/src/server.php
Restart=always
RestartSec=5

User=ws_push
Group=ws_push

WorkingDirectory=/path/to/the/ws/src/
Environment="ENV_VAR=production"
StandardOutput=journal
StandardError=journal
ExecStop=/bin/kill $MAINPID

[Install]
WantedBy=multi-user.target
```

And install the service:

```
systemctl daemon-reload
systemctl enable ws.server.service
systemctl start ws.server.service
