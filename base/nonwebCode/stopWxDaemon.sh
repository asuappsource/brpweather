# This probably isn't very safe.
pid=`pgrep -u brpweather php`
kill -USR1 $pid
