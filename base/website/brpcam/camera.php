<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Blue Ridge Parkway Webcams</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <base href="/" />

    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/bootstrap-responsive.min.css" rel="stylesheet">
    <link href="/css/main.css" rel="stylesheet">

    <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

  </head>

  <body<?php echo (isset($_GET['debug']) && $_GET['debug'] == 'yep') ? ' class="debug"' : ''; ?>>

    <div class="container-narrow">

      <div class="masthead row-fluid">
          <a href="index.php"><h2 class="span8" id="heading">BRP Webcams</h2></a>
          <div class="well well-small pull-right">
            <a href="http://nps.gov/blri" target="_blank"><img src="/img/blri_logo.png" /></a>
            <a href="http://brpfoundation.org" target="_blank"><img src="/img/blpf_logo.png" /></a>
          </div>
      </div>

      <hr>

        <div id="initial-loading" class="progress progress-striped active">
            <div class="bar" style="width: 100%;"></div>
        </div>
        
        <div id="ajax-loaded" class="hide">
            <div id="stale-alert" class="hide alert alert-error">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>Uh-Oh</strong> This camera hasn't reported a new image in a while!
            </div>
            <div id="cam-info" class="row-fluid">
                <div class="span7">
                    <h3 id="title"></h3>
                    <p id="description"></p>
                    <address>
                        <span id="city"></span>, <span id="state"></span><br>
                        <strong>Mile Marker: </strong><span id="mile-marker"></span>
                    </address>
                    <form method="GET" action="camera.php" id="switch-camera">
                        <label for="webcam"><strong>Other Cameras:</strong></label>
                        <select name="webcam" id="camera-select">
                        </select>
                    </form>
                </div>
                <div class="span5">
                    <!-- <img id="static-map" class="pull-right img-rounded" />-->
                    <div id="dynamic-map" class="pull-right"></div>
                </div>
            </div>
            
            <!-- div/frame for the video controls -->
            <form id="anim-controls" class="form-inline">
                <div class="controls controls-row">
                    <!-- video player buttons -->
                    <div class="btn-group">
                        <button type="button" id="step-backward" class="btn step-control">
                            <i class="icon-step-backward"></i>
                        </button>
                        <button type="button" id="pause" class="btn btn-success">
                            <i class="icon-play"></i>
                        </button>
                        <button type="button" id="step-forward" class="btn step-control">
                            <i class="icon-step-forward"></i>
                        </button>
                    </div>
                    <label for="time">Time Lapse Length:</label>
                    <select id="time" class="input-medium">
                        <option value="3600">1 hour</option>
                        <option value="21600">6 hours</option>
                        <option value="43200">12 hours</option>
                        <option value="86400">24 hours</option>
                    </select>
                    
                    <!-- div for progress bar -->
                    <div class="row-fluid hide" id="preload-controls">
                        <div class="progress span10" id="preload-progress">
                            <div class="bar"></div>
                        </div>
                        <button type="button" id="cancel" class="pull-right btn btn-small btn-danger" disabled>Cancel</button>
                    </div>

                    <!-- fullscreen button -->
                    <div class="pull-right">
                        <!--<button type="button" id="loop" class="btn btn-info active" data-toggle="button" title="Loop">
                            <i class="icon-repeat"></i>
                        </button>-->
                        <button type="button" id="fullscreen" class="btn" data-toggle="modal" data-target="#lightbox">
                            <i class="icon-fullscreen"></i>
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- div for date and frame counter  -->
            <div class="row-fluid">
                <span class="pull-right span2" id="frame-counter">
                    <span id="frame-counter-count">1</span>
                    <span id="frame-counter-total"> of 30</span>
                </span>
                <span id="frame-timestamp"></span>
            </div>

            <!-- div for the "player"  -->
            <div id="buffer-frame">
                <img class="webcam-anim img-polaroid" />
                <img class="webcam-anim-buffer img-polaroid" />
            </div>
            
        </div>


      <hr>

      <div class="footer">
        <p>&copy; Appsource 2013</p>
      </div>

    </div> <!-- /container -->
    
    <div id="lightbox" class="modal hide fade">
        <div class="modal-body">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <img id="webcam-full">
        </div>
    </div>
    
    <script>
        <?php //TODO: in false case for webcam id, return error value and handle it in JS ?>
        var _id = <?php echo (isset($_GET['webcam']) && is_numeric($_GET['webcam'])) ? $_GET['webcam'] : 3; ?>;
        var _lapse = <?php echo (isset($_GET['hours']) && is_numeric($_GET['hours'])) ? $_GET['hours'] : 1; ?>;
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCdPupGvZme3V9RsCbHRa-nq7NyvkqLcc0&sensor=false"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
    <script src="/js/jquery.imagesloaded.min.js"></script>
    <script src="/js/cam.js"></script>
  
  </body>
</html>
