(function ($, Drupal, document) {
  Drupal.behaviors.ScormFieldScormOverlayOn = {
      attach: function (context, settings) {



        fullscreenBtn = document.getElementById("fullscreen-button");
        fullscreenElm = document.getElementById("scorm-field-scorm-overlay");

        // Close the fullscreen.
        fullscreenBtn.addEventListener('click', (e) => {
    
          if (fullscreenElm.requestFullscreen) {
            fullscreenElm.requestFullscreen();
          } else if (fullscreenElm.webkitRequestFullscreen) { /* Safari */
          fullscreenElm.webkitRequestFullscreen();
          } else if (elem.msRequestFullscreen) { /* IE11 */
          fullscreenElm.msRequestFullscreen();
          }
    
        });


        $('#scorm-field-scorm-overlay-button-on').click(function () {
          var overlay = document.getElementById("scorm-field-scorm-overlay");
          overlay.style.display = "block";
          overlay.classList.add("scorm_field__overlay");          
        });

      }
  };
  Drupal.behaviors.ScormFieldScormOverlayOff = {
    attach: function (context, settings) {
      $('#scorm-field-scorm-overlay-button-off').click(function () {
        var overlay = document.getElementById("scorm-field-scorm-overlay");
        //var buttonOn = document.getElementById("social-course-scorm-overlay-button-on");
        //buttonOn.style.display = "none";
        overlay.classList.remove("scorm_field__overlay");
      });
    }
};









}(jQuery, Drupal, this.document));


