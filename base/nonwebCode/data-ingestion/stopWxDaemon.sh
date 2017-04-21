#!/bin/bash

# This probably isn't very safe.
pid=`cat /home/brpweather/data-ingestion/wxProcess.pid`
kill -USR1 $pid
