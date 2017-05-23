$(function() {
    var FRAME_DELAY = 250; //the delay between frames in ms

    var animImage = $("img.webcam-anim");
    var animBuffer = $("img.webcam-anim-buffer");
    var animControls = $("#anim-controls");
    var preloadControls = $("#preload-controls");
    var preloadProgressBar = $("#preload-progress .bar");
    var frameCounter = $("#frame-counter");
    var frameCounterCount = frameCounter.children("#frame-counter-count");
    var frameTimeStamp = $("#frame-timestamp");

    var _interval = null;
    var _webcam = null;
    var frames = null;
    var fullImageURL;
    var stateStack = [];

    var seconds = (_lapse > 0 && _lapse <= 24) ? _lapse * 3600 : 3600;
    $("#time").val(seconds);

    // start the animation with whatever the intial lapse time is
    showAnimation(_id, $("#time").val());
    
    // grab the meta-data for this webcam
    //$.getJSON("/brpweather/apis/webcams?json").done(function(json) {
    $.getJSON("http://wxdata.appsourceweather.org/webcam.php?json").done(function(json) {
        $("#initial-loading").hide();
        
        var webcamSelect = $("#camera-select");
        var camInfo = $("#cam-info");
        
        $.each(json, function(i, webcam) {
            webcamSelect.append($("<option/>", {'value': webcam.id}).text(webcam.name));
            if (webcam.id == _id) {
                _webcam = webcam;
            }
        });

        webcamSelect
         .val(_id)
         .change(function() {
            window.location.href = "cam/" + $(this).val() + "/" + _lapse;
        });
       
        // have to jump through these hoops because firefox js engine doesn't parse dates correctly 
        var year = _webcam.lastModified.substr(0, 4);
        var month = _webcam.lastModified.substr(5, 2);
        var day = _webcam.lastModified.substr(8, 2);
        var hour = _webcam.lastModified.substr(11, 2);
        var minute = _webcam.lastModified.substr(14, 2);
        var second = _webcam.lastModified.substr(17, 2);
        var lastModified = new Date(year, month - 1, day, hour, minute, second);
        //TODO: configurable cutoff time (currently 2 hours)
        var lastModifiedCutoff = (new Date()).getTime() - (60*60*2*1000);
        if (lastModified.getTime() <= lastModifiedCutoff) {
            $("#stale-alert").removeClass("hide");
            //TODO: check to see if any of the time lapses contain images, and disable the ones that don't
        }
        var DSTsuffix = (lastModified.getTimezoneOffset() == 300) ? " EST" : " EDT";
        frameTimeStamp.text(lastModified.toLocaleDateString() + " at " + lastModified.toLocaleTimeString() + DSTsuffix);
        
        camInfo.find("#title").text(_webcam.name);
        //camInfo.find("#description").text(_webcam.caption);
        camInfo.find("#city").text(_webcam.city);
        camInfo.find("#state").text(_webcam.state);
        camInfo.find("#mile-marker").text(_webcam.milemarker);
    
        //fullImageURL = "http://brpweather.org/webcams/" + _webcam.dir + "/800px/image.jpeg";
        fullImageURL = "http://brpwebcams.org/images/" + _webcam.dir + "/800px/image.jpeg";
        animImage.attr('src', fullImageURL);
        $("#webcam-full").attr('src', fullImageURL);
    
        //var staticMapURL ="http://maps.googleapis.com/maps/api/staticmap?size=290x180&zoom=9&markers=icon:http://appsourcevideo.cs.appstate.edu/brpcam/img/cam_map_pin.png%7C" + _webcam.lat  + "," + _webcam.long + "&sensor=false";
        //$("img#static-map").attr('src', staticMapURL);
        
        //initGMap(_webcam.lat, _webcam.long);
    
        $("#ajax-loaded").show();
    });

    /*
    function initGMap(lat, lng) {
        var pos = new google.maps.LatLng(lat, lng);
        var opts = {
            center: pos,
            zoom: 10,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            mapTypeControl: false,
            streetViewControl: false,
            panControl: false,
            zoomControlOptions: {
                style: google.maps.ZoomControlStyle.SMALL
            }
        };
        var map = new google.maps.Map($("#dynamic-map")[0], opts);
        new google.maps.Marker({
            position: pos,
            map: map,
            title: _webcam.name,
            icon: "/img/cam_map_pin.png"
        });
        new google.maps.KmlLayer({
            clickable: false,
            map: map,
            preserveViewport: true,
            url: "http://brpwebcams.org/kml/MotorROAD.kmz"
        });
    }
    */
    // register preload cancel button handler
    $("#cancel").click(function() {

        if ($(".preload-temp").remove().length > 0) {
            if (_interval != null) pause();
            restoreState();
            
            activateControls();
            setButtonToPlay();
            //play();

            preloadControls.hide();
            //animImage.show();
        }
    });
                
    // register pause button handler
    $("#pause").click(function() {
        if (_interval != null) {
            pause();
            setButtonToPlay();
        } else {
            play();
            setButtonToPause();
        }
    });

    //register step buttons handler
    $("#step-forward").click(function() {
        if (_interval != null) return;
        stepForward();
    });
    
    $("#step-backward").click(function() {
        if (_interval != null) return;
        stepBackward();
    });
    
    // register loop duration dropdown handler
    $("#time").change(function() {
        activateCancelButton();
        if (_interval != null) {
            pause();
        }
        //setButtonToPause();
        saveCurrentState();
        showAnimation(_id, $(this).blur().val());
    });

    
    $("#lightbox").on('show', function() {
        if (_interval != null) {
            pause();
            setButtonToPlay();
        }
    });

    function showAnimation(id, seconds) {
        $.getJSON("//wxdata.appsourceweather.org/imageArr.php?id=" + id + "&range=" + seconds).done(function(json) {
            preloadControls.show();
            animImage.attr('src', fullImageURL);

            // urls come in with /var/www at the beginning, need to trim that off
            for (var i=0; i<json.length; i++) {
                json[i] = json[i].split("../brpcam")[1];
            }

            frames = {
                images: json,
                index: 0
            }

            $("#frame-counter-total").text(" of " + frames.images.length);

            preloadImages(json);
        });
    }

    function preloadImages(images) {
        disableAllControls();
        preloadControls.show();
        hiddenDiv = $("<div/>", {'class': "preload-temp hide"}).appendTo($("body"));
        $.each(images, function(i, image) {
            $("<img/>").attr('src', image).appendTo(hiddenDiv);
        });
        hiddenDiv.imagesLoaded({
            callback: function(images, proper, broken) {
                disableCancelButton();
                if (_interval != null) pause();
                activateControls();
                activateStepControls();
                setButtonToPlay();

                preloadControls.hide();
                
                this.remove();
            },
            progress: function(isBroken, images, proper, broken) {
                preloadProgressBar
                    .css({
                        width: Math.round(((proper.length + broken.length) * 100) / images.length) + '%'
                    });
                if (console && isBroken) console.log("broken image!", this);
            }
        });
    }
   
    function stepForward() {
        if (frames.index == frames.images.length-1 && _interval != null) {
            pause();
            setButtonToPlay();
            return;
        }
            
        frames.index = (frames.index + 1) % frames.images.length;
        var url = frames.images[frames.index]
        
        animBuffer.attr('src', url);
        frameCounterCount.text(frames.index + 1);
         
        var date = new Date(url.substr(url.lastIndexOf("/") + 7, 10) * 1000);
        var DSTsuffix = (date.getTimezoneOffset() == 300) ? " EST" : " EDT";
        frameTimeStamp.text(date.toLocaleDateString() + " at " + date.toLocaleTimeString() + DSTsuffix);
        
        animImage.attr('src', url);
    }

    function stepBackward() {
        if (frames.index == 0)
            frames.index = frames.images.length - 1;
        else
            frames.index = (frames.index - 1) % frames.images.length;
        
        var url = frames.images[frames.index]
        
        animBuffer.attr('src', url);
        frameCounterCount.text(frames.index + 1);
         
        var date = new Date(url.substr(url.lastIndexOf("/") + 7, 10) * 1000);
        var DSTsuffix = (date.getTimezoneOffset() == 300) ? " EST" : " EDT";
        frameTimeStamp.text(date.toLocaleDateString() + " at " + date.toLocaleTimeString() + DSTsuffix);
        
        animImage.attr('src', url);
    }

    function play() {
        disableStepControls();
        clearInterval(_interval);
        stepForward();
        _interval = setInterval(stepForward, FRAME_DELAY);
    }
                
    function pause() {
        activateStepControls();
        clearInterval(_interval);
        _interval = null;
    }

    function activateControls() {
        animControls.find("#pause, #time").removeAttr("disabled");
    }

    function activateStepControls() {
        animControls.find(".step-control").removeAttr("disabled");
    }    

    function disableAllControls() {
        animControls.find("#pause, #time, .step-control").attr('disabled', "disabled");
    }
    
    function disableStepControls() {
        animControls.find(".step-control").attr('disabled', "disabled");
    }

    function activateCancelButton() {
        preloadControls.find("#cancel").removeAttr("disabled");
    }

    function disableCancelButton() {
        preloadControls.find("#cancel").attr('disabled', "disabled");
    }

    function setButtonToPause() {
        $("#pause")
            .removeClass("btn-success")
            .addClass("btn-warning")
            .children("i")
                .removeClass("icon-play")
                .addClass("icon-pause");
    }

    function setButtonToPlay() {
        $("#pause")
            .removeClass("btn-warning")
            .addClass("btn-success")
            .children("i")
                .removeClass("icon-pause")
                .addClass("icon-play");
    }

    function saveCurrentState() {
        var state = {
            frames: frames,
            counterHTML: frameCounter.html()
        };
        stateStack.push(state);
    }

    function restoreState() {
        if (stateStack.length < 1) return;
        var state = stateStack.pop();
        
        frames = state.frames;
        frameCounter.html(state.counterHTML);
    }

});
