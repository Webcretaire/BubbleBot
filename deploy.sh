#!/bin/bash

REMOTE_SERVER="$1"

if [ -z "$REMOTE_SERVER" ]
then
      echo "You need to specify the server to deploy to"
      exit 1
fi

OWN_PATH="`dirname \"$0\"`"
LOCAL_PROJECT_PATH="$OWN_PATH"
REMOTE_PROJECT_PATH="/var/www/my_webapp__3"
RSYNC_OPTIONS="-avzhP --delete"
RSYNC_CMD="rsync $RSYNC_OPTIONS"

echo
echo "================="
echo "= Sending files ="
echo "================="
echo

$RSYNC_CMD $LOCAL_PROJECT_PATH/src/ $REMOTE_SERVER:$REMOTE_PROJECT_PATH/src
$RSYNC_CMD $LOCAL_PROJECT_PATH/composer.json $REMOTE_SERVER:$REMOTE_PROJECT_PATH
$RSYNC_CMD $LOCAL_PROJECT_PATH/composer.lock $REMOTE_SERVER:$REMOTE_PROJECT_PATH
$RSYNC_CMD $LOCAL_PROJECT_PATH/main.php $REMOTE_SERVER:$REMOTE_PROJECT_PATH
$RSYNC_CMD $LOCAL_PROJECT_PATH/supervisor.conf.dist $REMOTE_SERVER:$REMOTE_PROJECT_PATH

echo
echo "===================="
echo "= Composer install ="
echo "===================="
echo

ssh $REMOTE_SERVER "cd $REMOTE_PROJECT_PATH && php8.1 /usr/local/bin/composer install"

echo
echo "======================"
echo "= Reload supervisord ="
echo "======================"
echo

ssh $REMOTE_SERVER "supervisorctl stop discordbotwebcretaire:"
ssh $REMOTE_SERVER "cd $REMOTE_PROJECT_PATH && rm logs/*.log* && sed -e \"s,___project__dir___,$REMOTE_PROJECT_PATH,g\" supervisor.conf.dist > supervisor.conf"
#                   ^^^^^^^^^^^^^^^^^^^^^^^    ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
#                   Go in correct directory    Generate supervisor conf with correct path
ssh $REMOTE_SERVER "supervisorctl update all && supervisorctl start discordbotwebcretaire:"

echo
echo "All done :)"
