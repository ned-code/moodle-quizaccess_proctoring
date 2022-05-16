define(['jquery', 'core/ajax', 'core/notification', 'core/modal_events', 'core/str', 'core/log'],
    function($, Ajax, Notification, ModalEvents, Str, log) {
        function d(...args){
            // noinspection JSUnresolvedVariable
            (log.debug || console.debug)(...args);
        }
        function warning_alert(text_key, okCallback=null, error=false){
            Str.get_strings([
                {'key' : error ? 'error' : 'warning'},
                {'key' : text_key, component : 'quizaccess_proctoring'},
                {'key' : 'ok'},
            ]).done(function(s) {
                Notification.alert(s[0], s[1], s[2])
                    .then(function(modal){
                        if (okCallback) modal.getRoot().on(ModalEvents.hidden, () => okCallback());
                        return modal;
                    });
            }
            ).fail(Notification.exception);
        }
        // d('proctoring.js loaded');
        $(function() {
            $('#id_start_quiz').prop("disabled", true);
            $('#id_proctoring').on('change', function() {
                if (this.checked && (isCameraAllowed || isCameraAllowed === null)) {
                    $('#id_start_quiz').prop("disabled", false);
                } else {
                    $('#id_start_quiz').prop("disabled", true);
                }
            });
        });

        function find_elements(){
            video = document.getElementById('video');
            canvas = document.getElementById('canvas');
            photo = document.getElementById('photo');
        }

        let firstcalldelay = 3000; // 3 seconds after the page load
        let takepicturedelay = 30000; // 30 seconds
        let isCameraAllowed = null;
        if (navigator.mediaDevices){
            navigator.mediaDevices.enumerateDevices()
                .then(function(devices) {
                    devices.forEach(function(device) {
                        if (device.kind === 'videoinput')
                            isCameraAllowed = isCameraAllowed || device.label !== '';
                    });
                })
        }

        let video, canvas, photo;
        find_elements();

        let isMobile = false; //initiate as false
        // device detection
        if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)
            || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0,4))) {
            isMobile = true;
        }
        let width = isMobile ? 100 : 320;
        let height = 0; // This will be computed based on the input stream
        let streaming = false;
        let data = null;

        let SA = {
            hideButtons: function(){
                let $quiz_button = $('#mod_quiz-next-nav');
                if (!$quiz_button.hasClass('hidden')){
                    $quiz_button.prop("disabled", true).addClass('hidden')
                        .after('<div class="text text-red red">You need to enable web camera before submitting this quiz!</div>');
                }
            },
            clearphoto: function(){
                if (isCameraAllowed || isCameraAllowed === null){
                    var context = canvas.getContext('2d');
                    context.fillStyle = "#AAA";
                    context.fillRect(0, 0, canvas.width, canvas.height);

                    data = canvas.toDataURL('image/png');
                    if (photo) photo.setAttribute('src', data);
                } else {
                    SA.hideButtons();
                }
            },
            takepicture: function(){
                var context = canvas.getContext('2d');
                if (width && height) {
                    canvas.width = width;
                    canvas.height = height;
                    context.drawImage(video, 0, 0, width, height);
                    data = canvas.toDataURL('image/png');
                    photo.setAttribute('src', data);
                    props.webcampicture = data;

                    var wsfunction = 'quizaccess_proctoring_send_camshot';
                    var params = {
                        'courseid': props.courseid,
                        'screenshotid': props.id,
                        'quizid': props.quizid,
                        'webcampicture': data,
                        'imagetype': 1
                    };

                    var request = {
                        methodname: wsfunction,
                        args: params
                    };

                    Ajax.call([request])[0].done(function(data) {
                        if (data.warnings.length < 1) {
                            // NO; pictureCounter++;
                        } else {
                            if (video) {
                                warning_alert('error:takingimage', null, true);
                            }
                        }
                    }).fail(Notification.exception);
                } else {
                    SA.clearphoto();
                }
            },
            setup: function(props) {
                // $("body").attr("oncopy","return false;");
                // $("body").attr("oncut","return false;");
                // $("body").attr("onpaste","return false;");
                // $("body").attr("oncontextmenu","return false;");

                // Camshotdelay taken from admin_settings
                takepicturedelay = props.camshotdelay;
                // Skip for summary page
                if (document.getElementById("page-mod-quiz-summary") !== null &&
                    document.getElementById("page-mod-quiz-summary").innerHTML.length) {
                    return false;
                }
                if (document.getElementById("page-mod-quiz-review") !== null &&
                    document.getElementById("page-mod-quiz-review").innerHTML.length) {
                    return false;
                }

                $('#mod_quiz_navblock').append('<div class="card-body p-3"><h3 class="no text-left">Webcam</h3> <br/>'
                    + '<video id="video">Video stream not available.</video><canvas id="canvas" style="display:none;"></canvas>'
                    + '<div class="output" style="display:none;">'
                    + '<img id="photo" alt="The picture will appear in this box."/></div></div>');

                if (navigator.mediaDevices){
                    navigator.mediaDevices.getUserMedia({video: true, audio: false})
                        .then(function(stream) {
                            video.srcObject = stream;
                            video.play();
                            isCameraAllowed = true;
                        })
                        .catch(function() {
                            isCameraAllowed = false;
                            SA.hideButtons();
                        });
                } else {
                    isCameraAllowed = false;
                    SA.hideButtons();
                }

                if (!video) find_elements();

                if (video) {
                    video.addEventListener('canplay', function() {
                        if (!streaming) {
                            height = video.videoHeight / (video.videoWidth / width);
                            // Firefox currently has a bug where the height can't be read from
                            // The video, so we will make assumptions if this happens.
                            if (isNaN(height)) {
                                height = width / (4 / 3);
                            }
                            video.setAttribute('width', width);
                            video.setAttribute('height', height);
                            canvas.setAttribute('width', width);
                            canvas.setAttribute('height', height);
                            streaming = true;
                        }
                    }, false);

                    // Allow to click picture
                    video.addEventListener('click', function(ev) {
                        SA.takepicture();
                        ev.preventDefault();
                    }, false);
                    setTimeout(SA.takepicture, firstcalldelay);
                    setInterval(SA.takepicture, takepicturedelay);
                } else {
                    SA.hideButtons();
                }
                var cascadeClose;
                $(window).ready(function() {
                    cascadeClose = setInterval(CloseOnParentClose, 1000);
                });
                const quizurl = props.quizurl;
                function CloseOnParentClose() {
                    //// OLD CODE
                    // if (typeof window.opener != 'undefined' && window.opener !== null) {
                    //     if (window.opener.closed) {
                    //         window.close();
                    //     }
                    // } else {
                    //     window.close();
                    // }
                    //
                    // var parentWindowURL = window.opener.location.href;
                    // // console.log("parenturl", parentWindowURL);
                    // // console.log("quizurl", quizurl);
                    //
                    // if(!parentWindowURL.includes(quizurl)){
                    //     window.close();
                    // }
                    // if (parentWindowURL !== quizurl) {
                    //     window.close();
                    // }
                    //
                    // var share_state = window.opener.share_state;
                    // var window_surface = window.opener.window_surface;
                    // // Console.log('parent ss', share_state);
                    // // console.log('parent ws', window_surface);
                    //
                    // if (share_state.value !== "true") {
                    //     // Window.close();
                    //     // console.log('close window now');
                    //     window.close();
                    // }
                    //
                    // if (window_surface.value !== 'monitor') {
                    //     // Console.log('close window now');
                    //     window.close();
                    // }
                    /////

                    if (!cascadeClose) return;

                    let finish = function(){
                        clearInterval(cascadeClose);
                        cascadeClose = null;
                        d('finish');
                        warning_alert('warning:keepparentwindowopen', window.close);
                        //window.close();
                    };

                    //d('window status checking:');
                    if (window.opener != null && !window.opener.closed){
                        //d('window open')
                    } else {
                        //d('window closed');
                        finish();
                        return;
                    }

                    var parentWindowURL = window.opener.location.href;
                    // d("parenturl", parentWindowURL);
                    // d("quizurl", quizurl);

                    if (!parentWindowURL.includes(quizurl)){
                        finish(1);
                        return;
                    }

                    if (parentWindowURL !== quizurl){
                        finish();
                        return;
                    }
                }



                // $("#responseform").submit(function() {
                //     var nextpageel = document.getElementsByName('nextpage');
                //     var nextpagevalue = 0;
                //     if (nextpageel.length > 0) {
                //         nextpagevalue = nextpageel[0].value;
                //     }
                //     if (nextpagevalue === "-1") {
                //         window.opener.screenoff.value = "1";
                //     }
                // });

                return true;
            },
            videoStartup: function(){
                if (!video) find_elements();

                if (video) {
                    let get_camera_fail = function(){
                        isCameraAllowed = false;
                        warning_alert('warning:cameraallowwarning');
                        SA.hideButtons();
                    };

                    if (navigator.mediaDevices){
                        navigator.mediaDevices.getUserMedia({video: true, audio: false})
                            .then(function(stream) {
                                video.srcObject = stream;
                                video.play();
                                isCameraAllowed = true;
                                $("#allow_camera_btn").prop('disabled', 'disabled');
                            })
                            .catch(function() {
                                get_camera_fail();
                            });
                    } else {
                        get_camera_fail();
                    }


                    video.addEventListener('canplay', function() {
                        if (!streaming) {
                            height = video.videoHeight / (video.videoWidth / width);
                            // Firefox currently has a bug where the height can't be read from
                            // The video, so we will make assumptions if this happens.
                            if (isNaN(height)) {
                                height = width / (4 / 3);
                            }
                            video.setAttribute('width', width);
                            video.setAttribute('height', height);
                            canvas.setAttribute('width', width);
                            canvas.setAttribute('height', height);
                            streaming = true;
                        }
                    }, false);

                    // Allow to click picture
                    video.addEventListener('click', function(ev) {
                        SA.takepicture();
                        ev.preventDefault();
                    }, false);
                } else {
                    SA.hideButtons();
                }
                SA.clearphoto();
            },
            setupAttempt: function(props) {
                // $("body").attr("oncopy","return false;");
                // $("body").attr("oncut","return false;");
                // $("body").attr("onpaste","return false;");
                // $("body").attr("oncontextmenu","return false;");
                // console.log(props);
                console.log(props.examurl);
                var submitbtn = document.getElementById('id_submitbutton');

                $("#id_submitbutton").css("display", "none");
                var quizwindow;
                var startbtn = $('<button disabled class="btn btn-primary" id="id_start_quiz">Start Quiz</button>').click(function () {
                    // var url = props.examurl+'?attempt='+props.attemptid+'&cmid='+props.cmid;

                    var sesskey = document.getElementsByName("sesskey")[0].value;
                    var url = props.examurl+'?cmid='+props.cmid+'&sesskey='+sesskey;
                    console.log('url',url);
                    event.preventDefault();
                    quizwindow = window.open(url, '_blank');
                });

                // var quizlink = "<a href='http://www.google.com' target='_blank' class='btn btn-primary'>Start Quiz</a>";
                $( "#id_submitbutton" ).after(startbtn);

                $("#allow_camera_btn").click(function(event){
                    event.preventDefault();
                    SA.videoStartup();
                });

                var enablesharescreen = props.enablescreenshare;
                if (enablesharescreen){
                    window.share_state = document.getElementById('share_state');
                    window.window_surface = document.getElementById('window_surface');
                    window.screenoff = document.getElementById('screen_off_flag');

                    const videoElem = document.getElementById("video-screen");
                    const logElem = document.getElementById("log-screen");
                    var displayMediaOptions = {
                        video: {
                            cursor: "always"
                        },
                        audio: false
                    };

                    $("#share_screen_btn").click(function(event){
                        event.preventDefault();
                        // Console.log('screen sharing clicked');
                        startCapture();
                        $("#form_activate").css("visibility", "visible");
                        // Options for getDisplayMedia()
                    });

                    async function startCapture() {
                        logElem.innerHTML = "";
                        try {
                            // Console.log("vid found success");
                            videoElem.srcObject = await navigator.mediaDevices.getDisplayMedia(displayMediaOptions);
                            dumpOptionsInfo();
                            updateWindowStatus();
                        } catch (err) {
                            // Console.log("Error: " + err.toString());
                            let errString = err.toString();
                            if (errString == "NotAllowedError: Permission denied") {
                                warning_alert('warning:sharescreen');
                                return false;
                            }
                        }
                    }

                    function dumpOptionsInfo() {
                        // Const videoTrack = videoElem.srcObject.getVideoTracks()[0];

                        // Console.info("Track settings:");
                        // console.info(JSON.stringify(videoTrack.getSettings(), null, 2));
                        // console.info("Track constraints:");
                        // console.info(JSON.stringify(videoTrack.getConstraints(), null, 2));
                    }

                    $(window).on("beforeunload", function() {
                        if (quizwindow) quizwindow.close();
                    })

                    window.addEventListener('locationchange', function(){
                        console.log('location changed!');
                        if (quizwindow) quizwindow.close();
                    })

                    var updateWindowStatus = function() {
                        if (videoElem.srcObject !== null) {
                            // Console.log(videoElem);
                            const videoTrack = videoElem.srcObject.getVideoTracks()[0];
                            var currentStream = videoElem.srcObject;
                            var active = currentStream.active;
                            var settings = videoTrack.getSettings();
                            var displaySurface = settings.displaySurface;
                            document.getElementById('window_surface').value = displaySurface;
                            document.getElementById('display_surface').innerHTML = displaySurface;
                            document.getElementById('share_screen_status').innerHTML = active;
                            document.getElementById('share_state').value = active;
                            var screenoff = document.getElementById('screen_off_flag').value;

                            console.log(document.getElementById('window_surface'));
                            if(displaySurface !== 'monitor'){
                                // window close
                                if (quizwindow) quizwindow.close();
                                console.log('quiz window closed');
                            }

                            if(!active){
                                if (quizwindow) quizwindow.close();
                            }

                            // if (screenoff == "1") {
                            //     videoTrack.stop();
                            //     quizwindow.close();
                            //     console.log('quiz window closed');
                            //     clearInterval(windowState);
                            //     // location.reload();
                            // }
                        }
                    };

                    var takeScreenshot = function() {
                        var screenoff = document.getElementById('screen_off_flag').value;
                        if (videoElem.srcObject !== null) {
                            // Console.log(videoElem);
                            const videoTrack = videoElem.srcObject.getVideoTracks()[0];
                            var currentStream = videoElem.srcObject;
                            var active = currentStream.active;
                            // Console.log(active);

                            var settings = videoTrack.getSettings();
                            var displaySurface = settings.displaySurface;

                            if (screenoff == "0") {
                                if (!active) {
                                    warning_alert('warning:sorry:restartattempt');
                                    document.getElementById('display_surface').innerHTML = displaySurface;
                                    document.getElementById('share_screen_status').innerHTML = 'Disabled';
                                    clearInterval(screenShotInterval);
                                    // window.close();
                                    if (quizwindow) quizwindow.close();
                                    return false;
                                }
                                console.log(displaySurface);

                                if (displaySurface !== "monitor") {
                                    // console.log(displaySurface);
                                    warning_alert('warning:sorry:sharescreen');
                                    document.getElementById('display_surface').innerHTML = displaySurface;
                                    document.getElementById('share_screen_status').innerHTML = 'Disabled';
                                    clearInterval(screenShotInterval);
                                    // window.close();
                                    if (quizwindow) quizwindow.close();
                                    return false;
                                }

                            }
                            // Console.log(displaySurface);
                            // console.log(quizurl);

                            // Capture Screen
                            var video_screen = document.getElementById('video-screen');
                            var canvas_screen = document.getElementById('canvas-screen');
                            var screen_context = canvas_screen.getContext('2d');
                            // Var photo_screen = document.getElementById('photo_screen');
                            canvas_screen.width = screen.width;
                            canvas_screen.height = screen.height;
                            screen_context.drawImage(video_screen, 0, 0, screen.width, screen.height);
                            var screen_data = canvas_screen.toDataURL('image/png');
                            // Photo_screen.setAttribute('src', screen_data);
                            // console.log(screen_data);

                            // API Call
                            var wsfunction = 'quizaccess_proctoring_send_camshot';
                            var params = {
                                'courseid': props.courseid,
                                'screenshotid': props.id,
                                'quizid': props.cmid,
                                'webcampicture': screen_data,
                                'imagetype': 2
                            };

                            var request = {
                                methodname: wsfunction,
                                args: params
                            };

                            // Console.log('params', params);
                            if (screenoff == "0") {
                                Ajax.call([request])[0].done(function(data) {
                                    if (data.warnings.length < 1) {
                                        // NO; pictureCounter++;
                                    } else {
                                        if (video_screen) {
                                            warning_alert('error:takingimage', null, true);
                                            clearInterval(screenShotInterval);
                                        }
                                    }
                                }).fail(Notification.exception);
                            }
                        }
                    };

                    var screenShotInterval = setInterval(takeScreenshot, props.screenshotinterval);
                    var windowState = setInterval(updateWindowStatus, 1000);
                }

                $("#fcvalidate").click(function() {
                    event.preventDefault();
                    // Console.log('validate face clicked');
                    var context = canvas.getContext('2d');
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    var data = canvas.toDataURL('image/png');
                    photo.setAttribute('src', data);

                    var courseid = document.getElementById('courseidval').value;
                    var cmid = document.getElementById('cmidval').value;
                    var profileimage = document.getElementById('profileimage').value;

                    var wsfunction = 'quizaccess_proctoring_validate_face';
                    var params = {
                        'courseid': courseid,
                        'cmid': cmid,
                        'profileimage': profileimage,
                        'webcampicture': data,
                    };

                    var request = {
                        methodname: wsfunction,
                        args: params
                    };
                    document.getElementById('loading_spinner').style.display = 'block';
                    Ajax.call([request])[0].done(function(data) {
                        if (data.warnings.length < 1) {
                            document.getElementById('loading_spinner').style.display = 'none';
                            // NO; pictureCounter++;
                            // console.log('api response', data);
                            var status = data.status;
                            if (status == 'success') {
                                $("#video").css("border", "10px solid green");
                                $("#face_validation_result").html('<span style="color: green">True</span>');
                                // Document.getElementById("validate_form").style.display = "none";
                                document.getElementById("fcvalidate").style.display = "none";
                                // console.log(enablesharescreen);
                                if(enablesharescreen == 1){
                                    document.getElementById("share_screen_btn").style.display = "block";
                                }
                                else{
                                    $("#form_activate").css("visibility", "visible");
                                }
                            } else {
                                $("#video").css("border", "10px solid red");
                                $("#face_validation_result").html('<span style="color: red">False</span>');
                            }
                        } else {
                            document.getElementById('loading_spinner').style.display = 'none';
                            if (video) {
                                warning_alert('error:takingimage', null, true);
                            }
                        }
                    }).fail(Notification.exception);

                });

                return true;
            },
        };

        return SA;
    });
