# php-smtp

### setup

Initiaze database by using mysql command
```
$>mysql -uusername -p db_name < app/config/db/database.sql
```

create .env file at app root directory
```
$>vim .env
DB_DRIVER="mysql"
DB_HOST="127.0.0.1"
DB_DATABASE="db_name"
DB_USER="username"
DB_PASSWORD="userpassword"
DB_CHARSET="utf8"
DB_COLLATION="utf8_general_ci"
DB_PREFIX=""
DSN='mysqli://${DB_USER}:${DB_PASSWORD}@${DB_HOST}/${DB_DATABASE}'
```

### Start smtp server

```
$>php apps/script/smtp.server.php
SMTP Server(127.0.0.1:50025) start...
```

### Connet to Smtp Server by telnet

```
$>telnet 127.0.0.1 50025
220 localhost ESMTP
MAIL FROM: <user@example.com>
250 ok
RCPT TO: <user2@example.com>
250 ok
DATA
354 go ahead
From: user@example.com
To: user2@example.com
Subject: test mail

This is test mail
.
250 ok 1554537152
QUIT
```

