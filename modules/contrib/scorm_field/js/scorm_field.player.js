/**
 * @file
 * JS UI logic for SCORM player.
 *
 * @see js/lib/player.js
 * @see js/lib/api.js
 */

;(function($, Drupal, window, drupalSettings, undefined) {

  Drupal.behaviors.scormFieldScormPlayer = {

    attach: function(context, settings) {

      // Initiate the API.
      if (settings.scormVersion === '1.2') {
        var scormAPIobject = window.API;
        if (scormAPIobject === undefined) {
          scormAPIobject = new ScormFieldScorm12API(settings.scorm_data || {});
        }
      }
      else {
        var scormAPIobject = window.API_1484_11;
        if (window.API_1484_11 === undefined) {
          window.API_1484_11 = new ScormFieldScorm2004API(settings.scorm_data || {});
          scormAPIobject = window.API_1484_11;
        }

        // Register scos suspend data.
        if (settings.scormFieldScormUIPlayer && settings.scormFieldScormUIPlayer.cmiSuspendItems) {
          window.API_1484_11.registerSuspendItems(settings.scormFieldScormUIPlayer.cmiSuspendItems);
        }
      }

      // Register CMI paths.
      if (settings.scormFieldScormUIPlayer && settings.scormFieldScormUIPlayer.cmiPaths) {
        scormAPIobject.registerCMIPaths(settings.scormFieldScormUIPlayer.cmiPaths);
      }

      // Register default CMI data.
      if (settings.scormFieldScormUIPlayer && settings.scormFieldScormUIPlayer.cmiData) {
        for (var item in settings.scormFieldScormUIPlayer.cmiData) {
          scormAPIobject.registerCMIData(item, settings.scormFieldScormUIPlayer.cmiData[item]);
        }
      }


      // First we hide the next of finish button
      //$('.pager__item--next a', context).hide();

      // Get all SCORM players in our context.
      var $players = $('.scorm-ui-player', context);

      // If any players were found...
      if ($players.length) {

        // Register each player.
        // NOTE: SCORM only allows on SCORM package on the page at any given time.
        // Skip after the first one.
        var first = true;
        $players.each(function() {
          if (!first) {
            return false;
          }

          var element = this,
          $element = $(element),
          // Create a new ScormFieldScormUIPlayer().
          player = new ScormFieldScormUIPlayer(element),
          alertDataStored = false;


          player.init();
          var eventName = 'commit';
          if (settings.scormVersion === '1.2') {
            eventName = 'commit12';
          }
          // Listen on commit event, and send the data to the server.
          scormAPIobject.bind(eventName, function(value, data, scoId) {
            var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';
            console.log('ajax call triggered');
            //console.log(data);
            $.ajax({
              url: baseUrl + 'scorm-field-scorm/scorm/' + $element.data('scorm-id') + '/' + scoId + '/' + $element.data('node-id') + '/commit',
              data: { data: JSON.stringify(data) },
              async:   true,
              dataType: 'json',
              type: 'post',
              success: function(json) {
                if (alertDataStored) {
                  console.log(Drupal.t('We successfully stored your results. You can now proceed further.'));
                }
              }               
            });
          });

          // Listen to the unload event. Some users click "Next" or go to a different page, expecting
          // their data to be saved. We try to commit the data for them, hoping ot will get stored.
          $(window).bind('beforeunload', function() {
            if (settings.scormVersion === '1.2') {
              if (!scormAPIobject.isFinished) {
                scormAPIobject.LMSFinish('');
                alertDataStored = true;
              }
            }
            else {
              if (!scormAPIobject.isTerminated) {
                scormAPIobject.Commit('');
                alertDataStored = true;
                //return Drupal.t('It seems you did not finish the SCORM course, or maybe the SCORM course did not save your results. Should we try to store it for you ?');
              }
            }
          });

          // Add a class to the player, so the CSS can style it differently if needed.
          $element.addClass('js-processed');
          first = false;
        });
      }
    }

  };

})(jQuery, Drupal, window, drupalSettings);
