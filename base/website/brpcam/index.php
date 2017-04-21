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

  <body>

    <div class="container-narrow">

      <div class="masthead row-fluid">
        <div class="span8">
          <h2 id="heading" class="">Blue Ridge Parkway Webcams</h2>
        </div>
        <div class="span4">
          <div class="well well-small">
            <a href="http://nps.gov/blri" target="_blank"><img style="max-width:100px;" src="/img/blri_logo.gif" /></a>
            <a href="http://brpfoundation.org" target="_blank"><img style="max-width:100px;" src="/img/blpf_logo.svg" /></a>
          </div>
        </div>
      </div>

      <hr>
      
      <div id="initial-loading" class="progress progress-striped active">
          <div class="bar" style="width: 100%;"></div>
      </div>

      <div class="hide" id="webcams-wrapper">
      </div>

      <div class="footer">
        <p>&copy; Appsource 2017</p>
      </div>

    </div> <!-- /container -->
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
    <script>
        var webcams = {};
        $(function() {

            //$.getJSON("brpweather/apis/webcams?json").done(function(json) {
            $.getJSON("http://wxdata.appsourceweather.org/webcam.php?json").done(function(json) {
                var webcamsWrapper = $("#webcams-wrapper");

                var rowElement;
                $.each(json, function(i, webcam) {
                    if (i%3 == 0) {
                        rowElement = $("<div />", {'class': "row-fluid webcams"}).appendTo(webcamsWrapper);
                        $("<hr>", {'class': "separatorHR"}).appendTo(webcamsWrapper);
                    }
                    
                    $("<a />", {'class': "span4 cam-thumb", 'href': "cam/" + webcam.id})
                        .append($("<h5 />").text(webcam.name))
                        .append($("<img />", {'src': webcam.thumbURL, 'class': "img-polaroid"}))
                        .appendTo(rowElement);
                    webcams[webcam.id] = webcam;
                });
                
                $("#initial-loading").hide();
                $("#webcams-wrapper").show(); 
            });
        });
    </script>
  </body>
</html>
