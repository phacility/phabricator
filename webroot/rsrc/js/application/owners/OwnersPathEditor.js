/**
 * @requires multirow-row-manager
 *           javelin-install
 *           path-typeahead
 *           javelin-dom
 *           javelin-util
 *           phabricator-prefab
 *           phuix-form-control-view
 * @provides owners-path-editor
 * @javelin
 */

JX.install('OwnersPathEditor', {
  construct : function(config) {
    var root = JX.$(config.root);

    this._rowManager = new JX.MultirowRowManager(
      JX.DOM.find(root, 'table', config.table));

    JX.DOM.listen(
      JX.DOM.find(root, 'a', config.add_button),
      'click',
      null,
      JX.bind(this, this._onaddpath));

    this._count = 0;
    this._inputTemplate = config.input_template;
    this._repositoryTokenizerSpec = config.repositoryTokenizerSpec;

    this._completeURI = config.completeURI;
    this._validateURI = config.validateURI;
    this._icons = config.icons;
    this._modeOptions = config.modeOptions;

    this._initializePaths(config.pathRefs);
  },
  members : {
    /*
     * MultirowRowManager for controlling add/remove behavior
     */
    _rowManager : null,

    /*
     * How many rows have been created, for form name generation.
     */
    _count : 0,
    /*
     * URL for the typeahead datasource.
     */
    _completeURI : null,
    /*
     * URL for path validation requests.
     */
    _validateURI : null,
    /*
     * Template typeahead markup to be copied per row.
     */
    _inputTemplate : null,
    /*
     * Most packages will be in one repository, so remember whenever
     * the user chooses a repository, and use that repository as the
     * default for future rows.
     */
    _lastRepositoryChoice : null,
    _icons: null,
    _modeOptions: null,

    /*
     * Initialize with 0 or more rows.
     * Adds one initial row if none are given.
     */
    _initializePaths : function(path_refs) {
      for (var k in path_refs) {
        this.addPath(path_refs[k]);
      }
      if (!JX.keys(path_refs).length) {
        this.addPath();
      }
    },

    /*
     * Build a row.
     */
    addPath : function(path_ref) {
      // Smart default repository. See _lastRepositoryChoice.
      if (path_ref) {
        this._lastRepositoryChoice = path_ref.repositoryValue;
      } else {
        path_ref = {
          repositoryValue: this._lastRepositoryChoice || {}
        };
      }

      var repo = this._newRepoCell(path_ref.repositoryValue);
      var path = this._newPathCell(path_ref.display);
      var icon = this._newIconCell();
      var mode_cell = this._newModeCell(path_ref.excluded);

      var row = this._rowManager.addRow(
        [
          mode_cell,
          repo.cell,
          path.cell,
          icon.cell
        ]);

      new JX.PathTypeahead({
        repositoryTokenizer: repo.tokenizer,
        path_input : path.input,
        hardpoint : path.hardpoint,
        error_display : icon.cell,
        completeURI : this._completeURI,
        validateURI : this._validateURI,
        icons: this._icons
      }).start();

      this._count++;
      return row;
    },

    _onaddpath : function(e) {
      e.kill();
      this.addPath();
    },

    _newModeCell: function(value) {
      var options = this._modeOptions;

      var name = 'exclude[' + this._count + ']';

      var control = JX.Prefab.renderSelect(options, value, {name: name});

      return JX.$N(
        'td',
        {
          className: 'owners-path-mode-control'
        },
        control);
    },

    _newRepoCell: function(value) {
      var repo_control = new JX.PHUIXFormControl()
        .setControl('tokenizer', this._repositoryTokenizerSpec)
        .setValue(value);

      var repo_tokenizer = repo_control.getTokenizer();
      var name = 'repo[' + this._count + ']';

      function get_phid() {
        var phids = repo_control.getValue();
        if (!phids.length) {
          return null;
        }

        return phids[0];
      }

      var input = JX.$N(
        'input',
        {
          type: 'hidden',
          name: name,
          value: get_phid()
        });

      repo_tokenizer.listen('change', JX.bind(this, function() {
        this._lastRepositoryChoice = repo_tokenizer.getTokens();

        input.value = get_phid();
      }));

      var cell = JX.$N(
        'td',
        {
          className: 'owners-path-repo-control'
        },
        [
          repo_control.getRawInputNode(),
          input
        ]);

      return {
        cell: cell,
        tokenizer: repo_tokenizer
      };
    },

    _newPathCell: function(value) {
      var path_cell = JX.$N(
        'td',
        {
          className: 'owners-path-path-control'
        },
        JX.$H(this._inputTemplate));

      var path_input = JX.DOM.find(path_cell, 'input');

      JX.copy(
        path_input,
        {
          value: value || '',
          name: 'path[' + this._count + ']'
        });

      var hardpoint = JX.DOM.find(
        path_cell,
        'div',
        'typeahead-hardpoint');

      return {
        cell: path_cell,
        input: path_input,
        hardpoint: hardpoint
      };
    },

    _newIconCell: function() {
      var cell = JX.$N(
        'td',
        {
          className: 'owners-path-icon-control'
        });

      return {
        cell: cell
      };
    }

  }

});
