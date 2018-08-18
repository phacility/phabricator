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
    this._repositoryTokenizer = config.repositoryTokenizer;
    this._hardpoint = config.hardpoint;
    this._input = config.path_input;
    this._completeURI = config.completeURI;
    this._validateURI = config.validateURI;
    this._errorDisplay = config.error_display;
    this._textInputValues = {};

    this._icons = config.icons;

    this._initializeDatasource();
    this._initializeTypeahead(this._input);
  },
  members : {
    _repositoryTokenizer : null,

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
        var phid = this._getRepositoryPHID();
        if (phid) {
          this._textInputValues[phid] = this._typeahead.getValue();
        }
      }

      this._typeahead.listen(
        'change',
        JX.bind(this, function(value) {
          var phid = this._getRepositoryPHID();
          if (phid) {
            this._textInputValues[phid] = value;
          }

          this._validate();
        }));

      var repo_set_input = JX.bind(this, this._onrepochange);

      this._typeahead.listen('start', repo_set_input);

      this._repositoryTokenizer.listen('change', repo_set_input);

      this._typeahead.start();
      this._validate();
    },

    _onrepochange : function() {
      this._setPathInputBasedOnRepository(
        this._typeahead,
        this._textInputValues);

      this._datasource.setAuxiliaryData(
        {
          repositoryPHID: this._getRepositoryPHID()
        });

      // Since we've changed the repository, reset the results.
      this._datasource.resetResults();
    },

    _setPathInputBasedOnRepository : function(typeahead, lookup) {
      var phid = this._getRepositoryPHID();
      if (phid && lookup[phid]) {
        typeahead.setValue(lookup[phid]);
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

    _getRepositoryPHID: function() {
      var tokens = this._repositoryTokenizer.getTokens();
      var keys = JX.keys(tokens);

      if (keys.length) {
        return keys[0];
      }

      return null;
    },

    _validate : function() {
      var repo_phid = this._getRepositoryPHID();
      if (!repo_phid) {
        return;
      }

      var input = this._input;
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
        JX.bind(this, function(payload) {
          // Don't change validation display state if the input has been
          // changed since we started validation
          if (input.value !== input_value) {
            return;
          }

          if (payload.valid) {
            JX.DOM.setContent(error_display, JX.$H(this._icons.okay));
          } else {
            JX.DOM.setContent(error_display, JX.$H(this._icons.fail));
          }
        }));

      validation_request.listen('finally', function() {
        this._validationInflight = null;
      });

      validation_request.setData(
        {
          repositoryPHID : repo_phid,
          path : input_value
        });

      this._validationInflight = validation_request;
      JX.DOM.setContent(error_display, JX.$H(this._icons.test));
      validation_request.send();
    }
  }
});
