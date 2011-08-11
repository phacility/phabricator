/**
 * @requires javelin-install
 *           javelin-typeahead
 *           javelin-dom
 *           javelin-request
 *           javelin-typeahead-ondemand-source
 *           javelin-util
 * @provides path-typeahead
 * @javelin
 */

JX.install('PathTypeahead', {
  construct : function(config) {
    this._repositorySelect = config.repo_select;
    this._hardpoint = config.hardpoint;
    this._input = config.path_input;
    this._completeURI = config.completeURI;
    this._validateURI = config.validateURI;
    this._errorDisplay = config.error_display;

    /*
     * Default values to preload the typeahead with, for extremely common
     * cases.
     */
    this._textInputValues = config.repositoryDefaultPaths;

    this._initializeDatasource();
    this._initializeTypeahead(this._input);
  },
  members : {
    /*
     * DOM <select> elem for choosing the repository of a path.
     */
    _repositorySelect : null,
    /*
     * DOM parent div "hardpoint" to be passed to the JX.Typeahead.
     */
    _hardpoint : null,
    /*
     * DOM element to display errors.
     */
    _errorDisplay : null,
    /*
     * URI to query for typeahead results, to be passed to the
     * TypeaheadOnDemandSource.
    */
    _completeURI : null,

    /*
     * Underlying JX.TypeaheadOnDemandSource instance
     */
    _datasource : null,

    /*
     * Underlying JX.Typeahead instance
     */
    _typeahead : null,

    /*
     * Underlying input
     */
    _input : null,

    /*
     * Whenever the user changes the typeahead value, we track the change
     * here, keyed by the selected repository ID. That way, we can restore
     * typed values if they change the repository choice and then change back.
     */
    _textInputValues : null,

    /*
     * Configurable endpoint for server-side path validation
     */
    _validateURI : null,

    /*
     * Keep the validation AJAX request so we don't send several.
     */
    _validationInflight : null,

    /*
     * Installs path-specific behaviors and then starts the underlying
     * typeahead.
     */
    start : function() {
      if (this._typeahead.getValue()) {
        this._textInputValues[this._repositorySelect.value] =
          this._typeahead.getValue();
      }

      this._typeahead.listen(
        'change',
        JX.bind(this, function(value) {
          this._textInputValues[this._repositorySelect.value] = value;
          this._validate();
        }));

      this._typeahead.listen(
        'choose',
        JX.bind(this, function() {
          setTimeout(JX.bind(this._typeahead, this._typeahead.refresh), 0);
        }));

      var repo_set_input = JX.bind(this, this._onrepochange);

      this._typeahead.listen('start', repo_set_input);
      JX.DOM.listen(
        this._repositorySelect,
        'change',
        null,
        repo_set_input);

      this._typeahead.start();
      this._validate();
    },

    _onrepochange : function() {
      this._setPathInputBasedOnRepository(
        this._typeahead,
        this._textInputValues);

      this._datasource.setAuxiliaryData(
        {repositoryPHID : this._repositorySelect.value}
      );
    },

    _setPathInputBasedOnRepository : function(typeahead, lookup) {
      if (lookup[this._repositorySelect.value]) {
        typeahead.setValue(lookup[this._repositorySelect.value]);
      } else {
        typeahead.setValue('/');
      }
    },

    _initializeDatasource : function() {
      this._datasource = new JX.TypeaheadOnDemandSource(this._completeURI);
      this._datasource.setNormalizer(this._datasourceNormalizer);
      this._datasource.setQueryDelay(40);
    },

    /*
     * Construct and initialize the Typeahead.
     * Must be called after initializing the datasource.
     */
    _initializeTypeahead : function(path_input) {
      this._typeahead = new JX.Typeahead(this._hardpoint, path_input);
      this._datasource.setMaximumResultCount(15);
      this._typeahead.setDatasource(this._datasource);
    },

    _datasourceNormalizer : function(str) {
      return ('' + str).replace(/[\/]+/g, '\/');
    },

    _validate : function() {
      var input = this._input;
      var repo_id = this._repositorySelect.value;
      var input_value = input.value;
      var error_display = this._errorDisplay;

      if (!input_value.length) {
        input.value = '/';
        input_value = '/';
      }

      if (this._validationInflight) {
        this._validationInflight.abort();
        this._validationInflight = null;
      }

      var validation_request = new JX.Request(
        this._validateURI,
        function(payload) {
          // Don't change validation display state if the input has been
          // changed since we started validation
          if (input.value === input_value) {
            if (payload.valid) {
              JX.DOM.alterClass(error_display, 'invalid', false);
              JX.DOM.alterClass(error_display, 'valid', true);
            } else {
              JX.DOM.alterClass(error_display, 'invalid', true);
              JX.DOM.alterClass(error_display, 'valid', false);
            }
            JX.DOM.setContent(error_display, payload.message);
          }
        });

      validation_request.listen('finally', function() {
        JX.DOM.alterClass(error_display, 'validating', false);
        this._validationInflight = null;
      });

      validation_request.setData(
        {
          repositoryPHID : repo_id,
          path : input_value
        });

      this._validationInflight = validation_request;

      validation_request.setTimeout(750);
      validation_request.send();
    }
  }
});
