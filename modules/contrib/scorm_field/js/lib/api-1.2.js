/**
 * @file
 * Defines the SCORM 1.2 API object.
 *
 * This is the Social course SCORM UI implementation of the SCORM API
 * object, used for communicating with the Open Social LMS.
 */

;(function($, Drupal, window, undefined) {

  /**
   * Implementation of the SCORM API.
   *
   * @constructor
   */
  var ScormFieldScorm12API = function() {
    this.error = '0';
    this.isInitialized = false;
    this.isFinished = false;
    this.skipCheck = false;
    this.registeredCMIPaths = [];
    this.readOnlyCMIPaths = [];
    this.writeOnlyCMIPaths = [];

    // Event callbacks.
    this.eventCallbacks = {
      initialize12: [],
      commit12: [],
      'pre-commit12': [],
      'post-commit12': [],
      'pre-getvalue12': [],
      'post-getvalue12': [],
      'pre-setvalue12': [],
      'post-setvalue12': []
    };

    // Set default data values.
    this.data = {
      scorm_version: '1.2',
      cmi: {

      }
    };
  };

  /**
   * @const Requested CMI value is currently not available.
   */
  ScormFieldScorm12API.VALUE_NOT_AVAILABLE = 'VALUE_NOT_AVAILABLE';

  /**
   * @const Requested CMI value is invalid.
   */
   ScormFieldScorm12API.CMI_NOT_VALID = 'CMI_NOT_VALID';

  /**
   * @const Requested CMI value is not yet implemented by Social Course Scorm.
   */
   ScormFieldScorm12API.CMI_NOT_IMPLEMENTED = 'CMI_NOT_IMPLEMENTED';

  /**
   * @const Requested CMI value is write-only.
   */
   ScormFieldScorm12API.VALUE_WRITE_ONLY = 'VALUE_WRITE_ONLY';

  /**
   * @const Requested CMI value is read-only.
   */
   ScormFieldScorm12API.VALUE_READ_ONLY = 'VALUE_READ_ONLY';

  /**
   * @const Requested CMI child value does not exist.
   */
   ScormFieldScorm12API.CHILD_DOES_NOT_EXIST = 'CHILD_DOES_NOT_EXIST';

  /**
   * Implements LMSInitialize().
   */
   ScormFieldScorm12API.prototype.LMSInitialize = function(value) {
    // The value MUST be an empty string.
    // If it's not empty, don't bother initializing the package.
    if (value !== '') {
      // Invalid argument.
      this.error = '201';
      return 'false';
    }

    if (!this.isInitialized) {
      this.isInitialized = true;
    }
    else {
      // Already initialized.
      this.error = '301';
      return 'false';
    }

    this.trigger('initialize12', value);

    // Successfully initialized the package.
    this.error = '0';
    return 'true';
  }

  /**
   * Implements LMSFinish().
   */
   ScormFieldScorm12API.prototype.LMSFinish = function(value) {
    if (value !== '') {
      this.error =  '201';
      return 'false';
    }

    // Check initialization.
    if (this.isInitialized) {
      // Commit values before finish.
      this.LMSCommit('');
      this.isFinished = true;
      this.isInitialized = false;
      this.error =  '0';
      return 'true';
    }
    else {
      // Not initialized.
      this.error = '301';
      return 'false';
    }
    this.error =  '101';
    return 'false';
  }

  /**
   * Implements LMSGetValue().
   */
   ScormFieldScorm12API.prototype.LMSGetValue = function(cmiElement) {
    // Cannot get a value if not initialized.
    // Set the error to 301 end return ''.
    if (!this.isInitialized) {
      this.error = '301';
      return '';
    }
    // Cannot get a value if terminated.
    // Set the error to 101 end return ''.
    else if (this.isFinished) {
      this.error = '101';
      return '';
    }

    // Must provide a cmiElement. If no valid identifier is provided,
    // set the error to 201 and return ''.
    if (cmiElement === undefined || cmiElement === null || cmiElement === '') {
      this.error = '201';
      return '';
    }

    this.trigger('pre-getvalue12', cmiElement);

    // Find the CMI value.
    try {
      var result = this._getCMIData(cmiElement);

      // If the value is not available, set the error to 101
      // and return ''.
      if (result === ScormFieldScorm12API.VALUE_NOT_AVAILABLE || result === ScormFieldScorm12API.CMI_NOT_VALID || result === ScormFieldScorm12API.CHILD_DOES_NOT_EXIST) {
        if (cmiElement === 'cmi.interactions._count' && result === ScormFieldScorm12API.CHILD_DOES_NOT_EXIST) {
          return '0';
        }
        this.error = '101';
        return '';
      }
      // If the value is write-only, set the error to 404 and
      // return ''.
      else if (result === ScormFieldScorm12API.VALUE_WRITE_ONLY) {
          this.error = '404';
          return '';
        }
        // For currently unimplemented values, set the error to 401
        // and return ''.
        else if (result === ScormFieldScorm12API.CMI_NOT_IMPLEMENTED) {
            this.error = '401';
            return '';
          }
          // If the value was found, return it and set the error to '0'.
          else {
            this.error = '0';
            //console.log('GetValueFound', cmiElement, result);
            this.trigger('post-getvalue12', cmiElement, result);
            return result;
          }

    }
    catch (e) {
      // If anything fails, for whatever reason, set the error to 101 and
      // return ''.
      this.error = '101';
      return '';
    }
  }

  /**
   * Implements LMSSetValue().
   */
   ScormFieldScorm12API.prototype.LMSSetValue = function(cmiElement, value) {

    // Cannot get a value if not initialized.
    // Set the error to 301.
    if (!this.isInitialized) {
      this.error = '301';
      return 'true';
    }
    // Cannot get a value if finished.
    // Set the error to 101.
    else if (this.isFinished) {
      this.error = '101';
      return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
    }

    // Must provide a cmiElement. If no valid identifier is provided,
    // set the error to 201.
    if (cmiElement === undefined || cmiElement === null || cmiElement === '' || typeof cmiElement !== 'string') {
      this.error = '201';
      return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
    }

    // The value must either be a String or a number. All other values have to be rejected.
    // Return 'false' and set the error to 201.
    if (typeof value !== 'string' && typeof value !== 'number') {
      this.error = '201';
      return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
    }

    this.trigger('pre-setvalue12', cmiElement, value);

    // Find the CMI value.
    try {
        var result = this._setCMIData(cmiElement, value);

        // If the value does not exist, set the error to 101
        // and return 'false'.
        if (result === ScormFieldScorm2004API.CMI_NOT_VALID) {
          //console.log('SetValue', 'NOT VALID');
          this.error = '101';
          return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
        }
        // For currently unimplemented values, set the error to 401
        // and return 'false'.
        else if (result === ScormFieldScorm2004API.CMI_NOT_IMPLEMENTED) {
          //console.log('SetValue', 'NOT IMPLEMENTED');
          this.error = '401';
          return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
        }
        // For read-only values, set the error to 403 and return 'false'.
        else if (result === ScormFieldScorm2004API.VALUE_READ_ONLY) {
            //console.log('SetValue', 'NOT WRITABLE');
            this.error = '403';
            return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
          }
    }
    catch (e) {
      // If anything fails, for whatever reason, set the error to 101.
      //console.log('SetValue', 'THREW ERROR');
      this.error = '101';
      return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
    }

    this.trigger('post-setvalue12', cmiElement, value);

    this.error = '0';
    return 'true';
  }

  /**
   * Implements LMSCommit().
   */
   ScormFieldScorm12API.prototype.LMSCommit = function(value) {

    // The value MUST be an empty string (per SCORM.1.2).
    // If it's not empty, don't bother terminating the package.
    if (value !== '') {
      this.error =  '201';
      return 'false';
    }

    // Can only commit if the session was initialized. Else, set error to
    // 301 and return 'false'.
    if (!this.isInitialized) {
      this.error = '301';
      return 'false';
    }
    // If already terminated, set the error to 101 and return 'false'.
    else if (this.isFinished) {
      this.error = '101';
      return 'true'; // 'false'; As per SCORM.1.2, should return false. However, to prevent annoying alerts from popping up in certain, malfunctioning packages, we return true.
    }

    this.trigger('pre-commit12', value, this.data);

    try {
      var $el = $(document),
        $iframe = $el.find('.scorm-ui-player-iframe-wrapper iframe'),
        iframe = $iframe[0];
      var scoId = iframe.src.split('scorm-field-scorm/player/sco/').pop();
      this.trigger('commit12', value, this.data, scoId);
    }
    catch (e) {
      // If anything fails, for whatever reason, set the error to 101 and
      // return 'false'.
      this.error = '101';
      return 'false';
    }

    this.trigger('post-commit12', value, this.data);

    this.error =  '0';
    return 'true';
  }

  /**
   * Implements LMSGetLastError().
   */
   ScormFieldScorm12API.prototype.LMSGetLastError = function() {
    return this.error;
  }

  /**
   * Implements LMSGetErrorString().
   */
   ScormFieldScorm12API.prototype.LMSGetErrorString = function(value) {
    if (value != "") {
      var errorString = new Array();
      errorString["0"] = "No error";
      errorString["101"] = "General exception";
      errorString["201"] = "Invalid argument error";
      errorString["202"] = "Element cannot have children";
      errorString["203"] = "Element not an array - cannot have count";
      errorString["301"] = "Not initialized";
      errorString["401"] = "Not implemented error";
      errorString["402"] = "Invalid set value, element is a keyword";
      errorString["403"] = "Element is read only";
      errorString["404"] = "Element is write only";
      errorString["405"] = "Incorrect data type";
      //console.log('LMSGetErrorString', value, errorString[value]);
      return errorString[value];
    }
    else {
      //console.log('LMSGetErrorString', value, "No error string found!");
      return "";
    }
  }

  /**
   * Implements LMSGetDiagnostic().
   */
   ScormFieldScorm12API.prototype.LMSGetDiagnostic = function() {
    //console.log('LMSGetDiagnostic');
    // @todo
    return '';
  }

  /**
   * Bind an event listener to the API.
   *
   * @param {String} event
   * @param {Function} callback
   */
   ScormFieldScorm12API.prototype.bind = function(event, callback) {
    if (this.eventCallbacks[event] === undefined) {
      throw { name: "ScormAPIUnknownEvent", message: "Can't bind/trigger event '" + event + "'" };
    }
    else {
      this.eventCallbacks[event].push(callback);
    }
  }

  /**
   * Trigger the passed event. All parameters (except the event name) are passed
   * to the registered callback.
   *
   * @param {String} event
   */
   ScormFieldScorm12API.prototype.trigger = function() {
    var args = Array.prototype.slice.call(arguments),
      event = args.shift();

    if (this.eventCallbacks[event] === undefined) {
      throw { name: "ScormAPIUnknownEvent", message: "Can't bind/trigger event '" + event + "'" };
    }
    else {
      for (var i = 0, len = this.eventCallbacks[event].length; i < len; i++) {
        this.eventCallbacks[event][i].apply(this, args);
      }
    }
  }

  /**
   * Register CMI paths.
   *
   * This will make the API tell the SCO the passed paths
   * are available and implemented. When reading/writing these values,
   * the API will behave as the SCO expects.
   *
   * @param {Object} cmiPaths
   *        A hash map of paths, where each item has a writeOnly or readOnly property.
   */
   ScormFieldScorm12API.prototype.registerCMIPaths = function(cmiPaths) {

    for (var cmiPath in cmiPaths) {
      if (cmiPath) {
        this.registeredCMIPaths.push(cmiPath);
        if (cmiPaths[cmiPath].readOnly !== undefined && cmiPaths[cmiPath].readOnly) {
          this.readOnlyCMIPaths.push(cmiPath);
        }
        else if (cmiPaths[cmiPath].writeOnly !== undefined && cmiPaths[cmiPath].writeOnly) {
          this.writeOnlyCMIPaths.push(cmiPath);
        }
      }
    }
  }

  /**
   * Register CMI data.
   *
   * This is different from SetValue, as it allows developers to set entire
   * data structures very quickly. This should be used on initialization for
   * providing data the SCO will need.
   *
   * Warning ! This can override data previously set by other callers. Use with caution.
   *
   * @see _setCMIData().
   *
   * @param {String} cmiPath
   * @param {Object} data
   */
   ScormFieldScorm12API.prototype.registerCMIData = function(cmiPath, data) {
    this._setCMIData(cmiPath, data, true);
  }

  ScormFieldScorm12API.prototype._setCMIData = function(cmiPath, value, skipValidation) {  

    if (!skipValidation) {
      // Check if the CMI path is valid. If not, return CMI_NOT_VALID.
      if (!this._validCMIDataPath(cmiPath)) {
        return ScormFieldScorm12API.CMI_NOT_VALID;
      }
      // Check if the CMI path is implemented. If not, return CMI_NOT_IMPLEMENTED.
      else if (!this._implementedCMIDataPath(cmiPath)) {
        return ScormFieldScorm12API.CMI_NOT_IMPLEMENTED;
      }
      // Check if the CMI path is read-only. If so, return VALUE_READ_ONLY.
      else if (this._readOnlyCMIDataPath(cmiPath)) {
          return ScormFieldScorm12API.VALUE_READ_ONLY;
        }
    }

    // Recursively walk the data tree and get the requested leaf.
    var pathTree = cmiPath.split('.'),
    // Get the first path element, usually 'cmi'.
      path = pathTree.shift(),
    // Get the last path element.
      leaf = pathTree.length ? pathTree.pop() : false;

    // If the root does not exist, initialize an empty object.
    if (this.data[path] === undefined) {
      this.data[path] = {};
    }

    // Get the root element data.
    var data = this.data[path];

    // If the leaf is not set, we don't need to walk any tree. Set the value immediately.
    if (!leaf) {
      data = value;
    }
    // Else, we walk the tree recursively creating all elements if needed.
    else {
      var prevPaths = [path];
      // Recursively walk the tree.
      while (pathTree.length) {
        path = pathTree.shift();

        // If the property does not exist yet, create it.
        if (data[path] === undefined) {
          // If the property is numerical, we're dealing with an array.
          if (/^[0-9]+$/.test(path)) {
            // If the key is 0, and the parent is not an array, reset the parent to an array object.
            // Push an empty element onto the array.
            if (path === '0' && data.length === undefined) {
              // Just resetting data to [] loses it's relationship with this.data. We have no choice
              // but to use eval() here.
              if (prevPaths[0].length && prevPaths[0] === 'cmi' && prevPaths[1].length && prevPaths[1] === 'interactions') {
                let command_eval = 'this.data';
                for (let i = 0; i < prevPaths.length; i++) {
                  if (jQuery.isNumeric(prevPaths[i])) {
                    command_eval = command_eval + '[' + prevPaths[i] + ']';
                  }
                  else {
                    command_eval = command_eval + '.' + prevPaths[i];
                  }
                }
                command_eval = command_eval + ' = [];';
                eval(command_eval);

                command_eval = 'data = this.data';
                for (let i = 0; i < prevPaths.length; i++) {
                  if (jQuery.isNumeric(prevPaths[i])) {
                    command_eval = command_eval + '[' + prevPaths[i] + ']';
                  }
                  else {
                    command_eval = command_eval + '.' + prevPaths[i];
                  }
                }
                command_eval = command_eval + ';';
                eval(command_eval);
              }
              else {
                eval('this.data.' + prevPaths.join('.') + ' = [];');
                eval('data = this.data.' + prevPaths.join('.') + ';');
              }

              data.push({});
            }
            // If the parent is an array object, but the given key is out of bounds, throw an error.
            else if (data.length < path) {
              throw { name: "CMIDataOutOfBounds", message: "Out of bounds. Cannot set [" + path + "] on " + cmiPath + ", as it contains only " + data.length + " elements." };
            }
            // Finally, if this is an array, and the key is valid, but there's no element yet,
            // push an empty element onto the array.
            else if (data[path] === undefined) {
                data.push({});
              }
          }
          // Else, we're dealing with a hash.
          else {
            data[path] = {};
          }
        }

        data = data[path];
        prevPaths.push(path);
      }

      data[leaf] = value;
    }
  }

  /**
   * Fetch the CMI data by recursively checking the CMI data tree.
   *
   * @param {String} cmiPath
   * @param {Boolean} skipValidation
   *
   * @returns {String}
   */
   ScormFieldScorm12API.prototype._getCMIData = function(cmiPath, skipValidation) {
    if (!skipValidation) {
      // Check if the CMI path is valid. If not, return CMI_NOT_VALID.
      if (!this._validCMIDataPath(cmiPath)) {
        return ScormFieldScorm12API.CMI_NOT_VALID;
      }
      // Check if the CMI path is write-only. If so, return VALUE_WRITE_ONLY.
      else if (this._writeOnlyCMIDataPath(cmiPath)) {
        return ScormFieldScorm12API.VALUE_WRITE_ONLY;
      }
      // Check if the CMI path is implemented. If not, return CMI_NOT_IMPLEMENTED.
      else if (!this._implementedCMIDataPath(cmiPath)) {
          return ScormFieldScorm12API.CMI_NOT_IMPLEMENTED;
        }
    }

    // Recursively walk the data tree and get the requested leaf.
    var pathTree = cmiPath.split('.'),
    // Get the first path element, usually 'cmi'.
      path = pathTree.shift(),
    // Get the root element data.
      data = this.data[path] !== undefined ? this.data[path] : null,
    // Are there more parts ? If so, flag this as looking for children.
      checkChildren = pathTree.length  > 1;

    // Recursively walk the tree.
    while (data && pathTree.length) {
      path = pathTree.shift();

      // Special case: if we request the length of an array, check if the current
      // data is an array. If so, get its length and break out of the loop.
      // If not, throw an error.
      if (path === '_count') {
        if (data.length !== undefined) {
          data = data.length;
          break;
        }
        else {
          throw new EvalError("Can only get the '_count' property for array data. CMI path: " + cmiPath);
        }
      }
      else {
        data = data[path] !== undefined ? data[path] : null;
      }
    }

    if (data !== null) {
      return data;
    }
    else {
      // If we were looking for an element children, return CHILD_DOES_NOT_EXIST.
      if (checkChildren) {
        return ScormFieldScorm12API.CHILD_DOES_NOT_EXIST;
      }
      // Else, return VALUE_NOT_AVAILABLE.
      else {
        return ScormFieldScorm12API.VALUE_NOT_AVAILABLE;
      }
    }
  }

  /**
   * Check if the given CMI path is valid and usable.
   *
   * @param {String} cmiPath
   *
   * @returns {Boolean}
   */
   ScormFieldScorm12API.prototype._validCMIDataPath = function(cmiPath) {

     // Normalize the path.
    var normalizedPath = this.normalizeCMIPath(cmiPath);

    var keys = [

      // Real CMI paths, from SCORM 1.2 requirement document.
      'cmi.core._children',
      'cmi.core.student_id',
      'cmi.core.student_name',
      'cmi.core.lesson_location',
      'cmi.core.credit',
      'cmi.core.lesson_status',
      'cmi.core.entry',
      'cmi.core.score_children',
      'cmi.core.score.raw',
      'cmi.core.score.max',
      'cmi.core.score.min',
      'cmi.core.score._children',
      'cmi.core.total_time',
      'cmi.core.exit',
      'cmi.core.session_time',
      'cmi.suspend_data',
      'cmi.launch_data',
      'cmi.objectives._children',
      'cmi.objectives._count',
      'cmi.objectives.n.id',
      'cmi.objectives.n.score._children',
      'cmi.objectives.n.score.raw',
      'cmi.objectives.n.score.max',
      'cmi.objectives.n.score.min',
      'cmi.objectives.n.status',
      'cmi.student_preference._children',
      'cmi.student_preference.audio',
      'cmi.student_preference.language',
      'cmi.student_preference.speed',
      'cmi.student_preference.text',
      'adl.nav.request',
      'cmi.interactions',
      'cmi.interactions._children',
      'cmi.interactions._count',
      'cmi.interactions.n.id',
      'cmi.interactions.n.type',
      'cmi.interactions.n.result',
      'cmi.interactions.n.timestamp',
      'cmi.interactions.n.student_response',
      'cmi.interactions.n.correct_responses._count',
      'cmi.interactions.n.correct_responses.n.pattern',
      'cmi.interactions.n.objectives._count',
      'cmi.interactions.n.objectives.n.id'
    ];

    return keys.indexOf(normalizedPath) !== -1;
  }

  ScormFieldScorm12API.prototype.normalizeCMIPath = function(cmiPath) {
    return cmiPath.replace(/\.[0-9]+\./g, '.n.');
  }

  /**
   * Check if the given CMI path is write-only.
   *
   * @param {String} cmiPath
   *
   * @returns {Boolean}
   */
   ScormFieldScorm12API.prototype._writeOnlyCMIDataPath = function(cmiPath) {
    // Normalize the path.
    var normalizedPath = this.normalizeCMIPath(cmiPath);

    // Check implemented paths.
    return this.writeOnlyCMIPaths.indexOf(normalizedPath) !== -1;
  }

  /**
   * Check if the given CMI path is read-only.
   *
   * @param {String} cmiPath
   *
   * @returns {Boolean}
   */
   ScormFieldScorm12API.prototype._readOnlyCMIDataPath = function(cmiPath) {
    // Normalize the path.
    var normalizedPath = this.normalizeCMIPath(cmiPath);

    // Check implemented paths.
    return this.readOnlyCMIPaths.indexOf(normalizedPath) !== -1;
  }

  /**
   * Check if the given CMI path is implemented by Social Course Scorm.
   *
   * @param {String} cmiPath
   *
   * @returns {Boolean}
   */
   ScormFieldScorm12API.prototype._implementedCMIDataPath = function(cmiPath) {
    // Normalize the path.
    var normalizedPath = this.normalizeCMIPath(cmiPath);

    // Check implemented paths.
    //console.log(this.registeredCMIPaths, normalizedPath, 'found: ', this.registeredCMIPaths.indexOf(normalizedPath) !== -1);
    return this.registeredCMIPaths.indexOf(normalizedPath) !== -1;
  }

  // Export.
  window.API = new ScormFieldScorm12API();

})(jQuery, Drupal, window);
