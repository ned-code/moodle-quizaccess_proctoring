define(['jquery', 'core/ajax', 'core/notification', 'core/modal_events', 'core/str', 'core/loglevel', 'core/config'],
    function($, Ajax, Notification, ModalEvents, Str, Log, CFG) {
        const PLUGIN = 'quizaccess_proctoring';
        const KEY_ALLOW = 'access_granted';
        const KEY_QUIZ_START = 'quiz_start';

        const KEY_WORK_DELAY = 24*3600*1000; // 1 day
        const INTERVAL_DELAY = 1000;

        const SETTING_H = 'height';
        const SETTING_W = 'width';
        const SETTING_ID = 'id';
        const SETTING_CMID = 'cmid';
        const SETTING_COURSEID = 'courseid';
        const SETTING_SCR_ENABLE = 'enablescreenshare';
        const SETTING_FACE_ENABLE = 'faceidcheck';
        const SETTING_CAMSHOT_DELAY = 'camshotdelay';
        const SETTING_WEB_ALLOWED = 'webcam_allowed';
        const SETTING_SCR_ALLOWED = 'screen_allowed';
        const SETTING_FACE_ALLOWED = 'face_allowed';
        const SETTING_BOX_ALLOWED = 'checkbox_validation';
        const SETTING_FINAL_DENY = 'final_deny';
        const SETTING_QUIZ_URL = 'quizurl';

        const ID_START_QUIZ = 'id_start_quiz';
        const ID_WEBCAM_BUTTON = 'allow_camera_btn';
        const ID_SCREEN_BUTTON = 'share_screen_btn';
        const ID_FACE_BUTTON = 'fcvalidate';
        const ID_FACE_RESULT = 'face_validation_result';

        const SELECTOR_QUIZ_START_BUTTON_DIV = 'div.singlebutton.quizstartbuttondiv';
        const DISPLAY_SURFACE = 'monitor';
        const IS_QUIZ_PAGE = 'is_quiz_page';

        //region Set Log
        let log = new Log.constructor(PLUGIN);
        log.originalFactory = log.methodFactory;
        log.methodFactory = (methodName, logLevel) => log.originalFactory(methodName, logLevel).bind(log, "["+PLUGIN+"]");
        log.setLevel(log.getLevel());
        let d = log.debug.bind(log);
        d('loaded');
        //endregion

        //region Set Storage
        let Storage = {
            _storage: window.localStorage,
            /**
             * Add prefix to key
             *
             * @param {string|array|any} key - The cache key to check, if it's array - join it by '_', otherwise uses .toString() method
             *
             * @return {string} - key with plugin prefix
             */
            _key: function(key){
                if (key instanceof Array) key = key.join('_');

                return PLUGIN + '_' + key.toString();
            },
            /**
             * Checked that fullKeyName and key are the same
             *
             * @param {string} fullKeyName - The cache full name key to check
             * @param {string|array|any} key - The cache key to check, if it's array - join it by '_', otherwise uses .toString() method
             *
             * @return {boolean}
             */
            keyCheck: function(fullKeyName, key){
                return fullKeyName.toString() === this._key(key);
            },
            /**
             * Get a value from local storage.
             * @method get
             *
             * @param {string|array|any} key - The cache key to check, if it's array - join it by '_', otherwise uses .toString() method
             *
             * @return {boolean|string|any} False if the value is not in the cache, or some other error -
             *      a string otherwise, or parsed JSON data
             */
            get: function(key){
                let value = this._storage.getItem(this._key(key));
                let res;
                if (typeof value === 'string') {
                    try {
                        res = JSON.parse(value);
                    } catch (e) {
                        res = value;
                    }
                } else {
                    res = value;
                }

                return res;
            },
            /**
             * Get a value from local storage.
             * @method get
             *
             * @param {string|array|any} key - The cache key to check, if it's array - join it by '_', otherwise uses .toString() method
             */
            remove: function(key){
                this._storage.removeItem(this._key(key));
            },
            /**
             * Set a value to local storage.
             * Remember: if json is false - value must be string.
             * @method set
             *
             * @param {string|array|any} key - The cache key to check, if it's array - join it by '_', otherwise uses .toString() method
             * @param {string|any} value - The value to set, saved as JSON
             *
             * @return {boolean} False if the value can't be saved, or some other error - true otherwise.
             */
            set: function(key, value){
                let res;
                try {
                    res = JSON.stringify(value);
                } catch (e) {
                    res = value;
                }

                // This can throw exceptions when the storage limit is reached.
                try {
                    this._storage.setItem(this._key(key), res);
                } catch (e) {
                    return false;
                }
                return true;
            },
            isSupported: function() {
                if (!this._storage) return false;

                try {
                    // MDL-51461 - Some browsers misreport availability of the storage, so check it is actually usable.
                    let testKey = '__storage_test__';
                    this.set(testKey, testKey);
                    this.remove(testKey);
                } catch (ex) {
                    return false;
                }

                return true;
            },
        };

        if (!Storage.isSupported()){
            warningAlert('error:localStorage', null, true);
        }
        //endregion

        //region Local Data
        let settings = {};

        let canvas, photo;
        let videoWebcam, videoScreen;
        let webcamShotInterval, screenShotInterval;
        let quizwindow;

        let isMobile = false; //initiate as false
        // device detection
        // noinspection RegExpRedundantEscape,RegExpSingleCharAlternation
        if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)
            || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0,4))) {
            isMobile = true;
        }
        settings[SETTING_W] = isMobile ? 100 : 320;
        settings[SETTING_H] = 0; // This will be computed based on the input stream
        let data = null;
        //endregion

        //region Functions
        function isU(val){
            return val === undefined;
        }
        // settings
        function importSettings(data){
            for (const key in data) settings[key] = data[key];
        }
        function clearStorage(){
            Storage.remove(KEY_ALLOW);
            Storage.remove(KEY_QUIZ_START);
        }
        function checkKeyAllow(){
            let data = Storage.get(KEY_ALLOW);
            if (!data) return false;

            return (data + KEY_WORK_DELAY) >= (new Date()).getTime();
        }
        function finishAttempt(alert_txt=false){
            d('finishAttempt', 'txt:', alert_txt);
            let final_fun = function(){};
            if (!settings[IS_QUIZ_PAGE]){
                clearStorage();
                closeWindow(quizwindow);
            } else {
                Storage.remove(KEY_QUIZ_START);
                $('#page').remove();
                final_fun = closeWindowMain;
            }

            if (alert_txt){
                if (typeof alert_txt !== 'string') alert_txt = 'warning:keepparentwindowopen';

                warningAlert(alert_txt, final_fun);
            } else {
                final_fun();
            }
        }
        function checkRules(){
            if (settings[IS_QUIZ_PAGE]) return undefined;
            if (settings[SETTING_FINAL_DENY]) return false;
            if (!settings[SETTING_WEB_ALLOWED]) return false;
            if (settings[SETTING_SCR_ENABLE] && !settings[SETTING_SCR_ALLOWED]) return false;
            if (settings[SETTING_FACE_ENABLE] && !settings[SETTING_FACE_ALLOWED]) return false;

            return !!settings[SETTING_BOX_ALLOWED];
        }
        function videoPlay(videoElem){
            let playPromise = videoElem.play();

            if (playPromise !== undefined){
                playPromise
                    .then(() => {})
                    .catch(() => {});
            }
        }
        function updateAllow(value, final=false){
            if (settings[IS_QUIZ_PAGE]) return undefined;
            if (settings[SETTING_FINAL_DENY]) return false;

            let prev = settings[KEY_ALLOW];
            let res = isU(value) ? checkRules() : value;
            settings[KEY_ALLOW] = res;

            let finalDeny = !res && final;
            if (finalDeny){
                turnWebcam(false);
                turnScreen(false);
                settings[SETTING_FINAL_DENY] = true;

                $('#'+ID_START_QUIZ).remove();
                let $quizstartbuttondiv = $(SELECTOR_QUIZ_START_BUTTON_DIV);
                if ($quizstartbuttondiv.length){
                    $quizstartbuttondiv
                        .find('form').prop('action', '').addClass('hidden')
                        .find(':input').prop('disabled', true);
                    // noinspection JSCheckFunctionSignatures
                    Str.get_string('cantattempt', PLUGIN).done(
                        s => $quizstartbuttondiv.append($('<div></div>').text(s).addClass('error'))
                    );
                }
            }

            if (res !== prev){
                Storage.set(KEY_ALLOW, res ? (new Date()).getTime() : false);

                if (!finalDeny){
                    $('#'+ID_START_QUIZ).prop("disabled", !res);
                }
            }

            return res;
        }
        function updateWebcamStatus(){
            if (settings[SETTING_FINAL_DENY]) return;

            let finish = function(){
                turnWebcam(false);
                return null;
            };

            if (!videoWebcam || !videoWebcam.srcObject) return finish();

            let currentStream = videoWebcam.srcObject;
            let active = currentStream.active;
            if (!active){
                return finish();
            }
        }
        function updateScreenStatus(){
            if (settings[SETTING_FINAL_DENY]) return;

            let finish = function(final_fail=false){
                turnScreen(false, null, final_fail);
                return null;
            };

            if (!videoScreen || !videoScreen.srcObject) return finish();

            let currentStream = videoScreen.srcObject;
            let active = currentStream.active;
            if (!active){
                return finish();
            }

            let displaySurface = getDisplaySurface(videoScreen);
            if (displaySurface !== DISPLAY_SURFACE){
                if (isU(displaySurface)){
                    warningAlert('error:sharescreen', null, true);
                    return finish(true);
                }

                warningAlert('warning:sorry:sharescreen');
                return finish();
            }
        }
        function turnWebcam(value=true, stream=null){
            if (settings[SETTING_FINAL_DENY]) return;

            value = value && videoWebcam;
            settings[SETTING_WEB_ALLOWED] = value;
            if (value){
                if (stream){
                    videoWebcam.srcObject = stream;
                }
                videoPlay(videoWebcam);
            } else {
                if (videoWebcam){
                    if (videoWebcam.srcObject){
                        videoWebcam.srcObject.getTracks().forEach(track => track.stop());
                    }
                    videoWebcam.srcObject = null;
                    videoWebcam.removeAttribute('height');
                    videoWebcam.streaming = false;
                }
            }

            updateAllow();
            $("#"+ID_WEBCAM_BUTTON).prop('disabled', value).toggleClass('disabled done', value);
        }
        function turnScreen(value=true, stream=null, final_fail=false){
            if (settings[SETTING_FINAL_DENY]) return;

            value = value && videoScreen && !final_fail;
            settings[SETTING_SCR_ALLOWED] = value;
            if (value){
                if (stream){
                    videoScreen.srcObject = stream;
                }
                videoPlay(videoScreen);
            } else {
                if (videoScreen){
                    if (videoScreen.srcObject){
                        videoScreen.srcObject.getTracks().forEach(track => track.stop());
                    }
                    videoScreen.srcObject = null;
                    videoScreen.removeAttribute('height');
                }

                closeWindow(quizwindow);
            }

            if (final_fail){
                updateAllow(false, true);
                $("#"+ID_SCREEN_BUTTON).prop('disabled', true).removeClass('done').addClass('disabled fail');
            } else {
                updateAllow();
                $("#"+ID_SCREEN_BUTTON).prop('disabled', value).toggleClass('disabled done', value);
            }

            if (value){
                // yes, it should be at the end
                updateScreenStatus();
            }
        }
        // get pictures
        function getDisplaySurface(videoElem){
            let displaySurface = null;
            if (!videoElem || !videoElem.srcObject || !videoElem.srcObject.getVideoTracks) return displaySurface;

            let videoTracks = videoElem.srcObject.getVideoTracks();
            for (let i = 0; i < videoTracks.length; i++){
                let track = videoTracks[i];
                if (!track.enabled || track.kind !== "video") continue;

                let tr_settings = track.getSettings();
                if (!tr_settings) continue;

                displaySurface = tr_settings.displaySurface;
                if (displaySurface) return displaySurface;
            }

            return displaySurface;
        }
        function takeScreenshot(){
            if (videoScreen.srcObject !== null) {
                // Console.log(videoElem);
                let currentStream = videoScreen.srcObject;
                let active = currentStream.active;
                // Console.log(active);

                let displaySurface = getDisplaySurface(videoScreen);
                if (!active) {
                    warningAlert('warning:sorry:restartattempt');
                    clearInterval(screenShotInterval);

                    if (quizwindow) quizwindow.close();
                    return false;
                }

                if (displaySurface !== DISPLAY_SURFACE) {
                    d(displaySurface);
                    warningAlert('warning:sorry:sharescreen');
                    clearInterval(screenShotInterval);

                    if (quizwindow) quizwindow.close();
                    return false;
                }

                // Capture Screen
                let canvas_screen = document.getElementById('canvas-screen');
                let screen_context = canvas_screen.getContext('2d');

                canvas_screen.width = screen.width;
                canvas_screen.height = screen.height;
                screen_context.drawImage(videoScreen, 0, 0, screen.width, screen.height);
                let screen_data = canvas_screen.toDataURL('image/png');

                // API Call
                let wsfunction = 'quizaccess_proctoring_send_camshot';
                let params = {
                    'courseid': settings[SETTING_COURSEID],
                    'screenshotid': settings[SETTING_ID],
                    'cmid': settings[SETTING_CMID],
                    'webcampicture': screen_data,
                    'imagetype': 2
                };

                let request = {
                    methodname: wsfunction,
                    args: params
                };

                Ajax.call([request])[0].done(function(data) {
                    if (data.warnings.length < 1) {
                        // NO; pictureCounter++;
                    } else {
                        if (videoScreen) {
                            warningAlert('error:takingimage', null, true);
                            clearInterval(screenShotInterval);
                        }
                    }
                }).fail(Notification.exception);
            }
        }
        function takePicture(){
            let context = canvas.getContext('2d');
            if (settings[SETTING_W] && settings[SETTING_H]) {
                canvas.width = settings[SETTING_W];
                canvas.height = settings[SETTING_H];
                context.drawImage(videoWebcam, 0, 0, settings[SETTING_W], settings[SETTING_H]);
                data = canvas.toDataURL('image/png');
                photo.setAttribute('src', data);

                let wsfunction = 'quizaccess_proctoring_send_camshot';
                let params = {
                    'courseid': settings[SETTING_COURSEID],
                    'screenshotid': settings[SETTING_ID],
                    'cmid': settings[SETTING_CMID],
                    'webcampicture': data,
                    'imagetype': 1
                };

                let request = {
                    methodname: wsfunction,
                    args: params
                };

                Ajax.call([request])[0].done(function(data) {
                    if (data.warnings.length < 1) {
                        // NO; pictureCounter++;
                    } else {
                        if (videoWebcam) {
                            warningAlert('error:takingimage', null, true);
                        }
                    }
                }).fail(Notification.exception);
            } else {
                clearPhoto();
            }
        }
        function clearPhoto(){
            if (!settings[SETTING_WEB_ALLOWED]) return;

            let context = canvas.getContext('2d');
            context.fillStyle = "#AAA";
            context.fillRect(0, 0, canvas.width, canvas.height);

            data = canvas.toDataURL('image/png');
            if (photo) photo.setAttribute('src', data);
        }
        function takeScreenshotInterval(){
            if (!Storage.get(KEY_QUIZ_START)) return;

            takeScreenshot();
        }
        function takePictureInterval(){
            if (!Storage.get(KEY_QUIZ_START)) return;

            takePicture();
        }

        // other
        function warningAlert(text_key, okCallback=null, is_error=false){
            Str.get_strings([
                {'key' : is_error ? 'error' : 'warning'},
                {'key' : text_key, component : PLUGIN},
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
        function closeWindow(win){
            if (!win) return;

            win.close();
            // if window not allowed to close, change its location
            if (win.location && win.location.assign) win.location.assign('/mod/quiz/view.php?id='+settings[SETTING_CMID]);
        }
        function closeWindowMain(win){
            win = win || window;
            closeWindow(win);
        }
        function CheckQuizPage() {
            const quizUrl = settings[SETTING_QUIZ_URL];
            let should_finish = function(){
                if (!checkKeyAllow()) return true;

                if (window.opener == null || window.opener.closed) return true;

                let parentWindowURL = window.opener.location.href;
                if (!parentWindowURL.includes(quizUrl)) return true;

                // noinspection RedundantIfStatementJS
                if (parentWindowURL !== quizUrl) return true;

                return false;
            }

            if (should_finish()) {
                d('call finishAttempt');
                finishAttempt(false, true);
            }
        }
        function find_elements(){
            videoWebcam = document.getElementById('video');
            canvas = document.getElementById('canvas');
            photo = document.getElementById('photo');
        }
        function callAfterPageLoad(fun){
            if (typeof fun !== 'function') return;

            if (document.readyState === 'complete'){
                fun();
            } else {
                $(window).on('load', fun);
            }
        }
        //endregion

        let API = {
            setup: function(props) {
                importSettings(props);
                settings[IS_QUIZ_PAGE] = true;

                // Skip for summary page
                if (document.getElementById("page-mod-quiz-summary") !== null &&
                    document.getElementById("page-mod-quiz-summary").innerHTML.length) {
                    return false;
                }
                if (document.getElementById("page-mod-quiz-review") !== null &&
                    document.getElementById("page-mod-quiz-review").innerHTML.length) {
                    return false;
                }

                Storage.set(KEY_QUIZ_START, true);

                $('#mod_quiz_navblock').append('<div class="card-body p-3"><h3 class="no text-left">Webcam</h3>'
                    + '<video id="video" class="navblock-video">Video stream not available.</video>'
                    + '<canvas id="canvas" class="hidden"></canvas>'
                    + '<div class="output hidden">'
                    + '<img id="photo" alt="The picture will appear in this box."/></div></div>');

                find_elements();
                API.videoStartup(() => finishAttempt('warning:sorry:restartattempt'));
                $(window)
                    .on("unload", finishAttempt)
                    .on('storage', (e) => {
                        d('onstorage', e);
                        if (e.key && Storage.keyCheck(e.key, KEY_ALLOW)) CheckQuizPage();
                    });

                callAfterPageLoad(function(){
                    CheckQuizPage();
                    $('body').addClass('shown');
                });

                return true;
            },
            videoStartup: function(fun_fail=null){
                if (settings[SETTING_FINAL_DENY]) return false;

                let res = null;
                if (videoWebcam) {
                    let get_camera_fail = function(...debug_data){
                        if (debug_data) d(...debug_data);

                        turnWebcam(false);
                        warningAlert('warning:cameraallowwarning');
                        res = false;
                        if (fun_fail && typeof fun_fail === 'function') fun_fail();
                    };

                    if (navigator.mediaDevices){
                        res = true;
                        navigator.mediaDevices.getUserMedia({video: true, audio: false})
                            .then(stream => turnWebcam(true, stream))
                            .catch(err => get_camera_fail('navigator.mediaDevices.getUserMedia error', err));
                    } else {
                        get_camera_fail('No navigator.mediaDevices');
                        return res;
                    }

                    if (!videoWebcam[PLUGIN+'_init']){
                        videoWebcam.addEventListener('canplay', function() {
                            if (!videoWebcam.streaming){
                                settings[SETTING_H] = videoWebcam.videoHeight / (videoWebcam.videoWidth / settings[SETTING_W]);
                                // Firefox currently has a bug where the height can't be read from
                                // The video, so we will make assumptions if this happens.
                                if (isNaN(settings[SETTING_H])) {
                                    settings[SETTING_H] = settings[SETTING_W] / (4 / 3);
                                }
                                videoWebcam.setAttribute('width', settings[SETTING_W]);
                                videoWebcam.setAttribute('height', settings[SETTING_H]);
                                canvas.setAttribute('width', settings[SETTING_W]);
                                canvas.setAttribute('height', settings[SETTING_H]);

                                videoWebcam.streaming = true;
                            }
                        }, false);

                        videoWebcam.addEventListener('suspend', () => updateWebcamStatus());
                        videoWebcam.addEventListener('pause', () => updateWebcamStatus());

                        videoWebcam[PLUGIN+'_init'] = true;
                    }
                } else {
                    res = false;
                }

                clearPhoto();
                return res;
            },
            screenStartup: function(){
                if (settings[SETTING_FINAL_DENY]) return false;

                let res = null
                let get_screen_fail = function(...debug_data){
                    if (debug_data) d(...debug_data);

                    turnScreen(false);
                    warningAlert('warning:sharescreen');
                    res = false;
                };

                if (!videoScreen) return res;

                if (navigator.mediaDevices){
                    res = true;
                    let displayMediaOptions = {
                        video: {
                            cursor: "always"
                        },
                        audio: false
                    };
                    let supportedConstraints = navigator.mediaDevices.getSupportedConstraints();
                    if (supportedConstraints.displaySurface) {
                        displayMediaOptions.video.displaySurface = DISPLAY_SURFACE;
                    }

                    navigator.mediaDevices.getDisplayMedia(displayMediaOptions)
                        .then(stream => turnScreen(true, stream))
                        .catch(err => get_screen_fail('navigator.mediaDevices.getDisplayMedia error', err));
                } else {
                    get_screen_fail('No navigator.mediaDevices');
                    return false;
                }

                if (!videoScreen[PLUGIN+'_init']){
                    videoScreen.addEventListener('suspend', () => updateScreenStatus());
                    videoScreen.addEventListener('pause', () => updateScreenStatus());

                    videoScreen[PLUGIN+'_init'] = true;
                }

                return res;
            },
            setupBeforeAttempt: function(props) {
                importSettings(props);
                clearStorage();
                updateAllow(false);

                let $submitbutton = $("#id_submitbutton");
                if (!$submitbutton.length){
                    d('Error: no $submitbutton');
                    updateAllow(false, true);
                    return;
                }
                $submitbutton.prop('disabled', 1);
                find_elements();

                let $startbtn = $('<button class="btn btn-primary"></button>').prop('id', ID_START_QUIZ).prop('disabled', true);
                $startbtn.click(function() {
                    event.preventDefault();
                    if (!updateAllow()) return;

                    let url = $submitbutton.closest('form').prop('action')+'?cmid='+settings[SETTING_CMID]+'&sesskey='+CFG.sesskey;
                    quizwindow = window.open(url, '_blank');
                });
                $startbtn.text($submitbutton.text() || $submitbutton.val());
                $submitbutton.after($startbtn);

                $('#id_proctoring_checkbox').on('change', function() {
                    settings[SETTING_BOX_ALLOWED] = !!this.checked;
                    updateAllow();
                });

                callAfterPageLoad(() => $(SELECTOR_QUIZ_START_BUTTON_DIV).addClass('shown'));

                $("#"+ID_WEBCAM_BUTTON).click(function(event){
                    event.preventDefault();
                    API.videoStartup();
                });
                $("#id_cancel").click(function(){
                    turnWebcam(false);
                    turnScreen(false);

                    settings[SETTING_FACE_ALLOWED] = false;
                    $("#"+ID_FACE_BUTTON).removeClass('hidden');
                    $("#"+ID_FACE_RESULT).html('');
                });

                webcamShotInterval = setInterval(takePictureInterval, settings[SETTING_CAMSHOT_DELAY] || INTERVAL_DELAY);
                $(window).on("unload", finishAttempt);

                if (settings[SETTING_SCR_ENABLE]){
                    videoScreen = document.getElementById("video-screen");

                    $("#"+ID_SCREEN_BUTTON).click(function(event){
                        event.preventDefault();
                        API.screenStartup();
                    });

                    screenShotInterval = setInterval(takeScreenshotInterval, settings[SETTING_CAMSHOT_DELAY] || INTERVAL_DELAY);
                }

                if (settings[SETTING_FACE_ENABLE]){
                    $("#"+ID_FACE_BUTTON).click(function() {
                        event.preventDefault();
                        settings[SETTING_FACE_ALLOWED] = false;

                        let context = canvas.getContext('2d');
                        context.drawImage(videoWebcam, 0, 0, canvas.width, canvas.height);
                        let data = canvas.toDataURL('image/png');
                        photo.setAttribute('src', data);

                        let courseid = settings[SETTING_COURSEID];
                        let cmid = settings[SETTING_CMID]

                        let wsfunction = 'quizaccess_proctoring_validate_face';
                        let params = {
                            'courseid': courseid,
                            'cmid': cmid,
                            'webcampicture': data,
                        };

                        let request = {
                            methodname: wsfunction,
                            args: params
                        };
                        document.getElementById('loading_spinner').style.display = 'block';
                        Ajax.call([request])[0].done(function(data) {
                            settings[SETTING_FACE_ALLOWED] = false;
                            if (data.warnings.length < 1) {
                                document.getElementById('loading_spinner').style.display = 'none';
                                if (data.status === 'success') {
                                    settings[SETTING_FACE_ALLOWED] = true;
                                    $(videoWebcam).css("border", "10px solid green");
                                    $("#"+ID_FACE_RESULT).html('<span style="color: green">True</span>');
                                    $("#"+ID_FACE_BUTTON).addClass('hidden');
                                } else {
                                    $(videoWebcam).css("border", "10px solid red");
                                    $("#"+ID_FACE_RESULT).html('<span style="color: red">False</span>');
                                }
                            } else {
                                document.getElementById('loading_spinner').style.display = 'none';
                                if (videoWebcam) {
                                    warningAlert('error:takingimage', null, true);
                                }
                            }
                            updateAllow();
                        }).fail(Notification.exception);

                    });
                }

                return true;
            },
        };

        return API;
    });
