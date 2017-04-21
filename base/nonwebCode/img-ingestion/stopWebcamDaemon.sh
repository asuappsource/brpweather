#!/bin/bash

# This probably isn't very safe.
pid=`cat /home/brpweather/img-ingestion/webcamProcess.pid`
kill -USR1 $pid
