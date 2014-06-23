/**
 * @requires multirow-row-manager
 *           javelin-install
 *           path-typeahead
 *           javelin-dom
 *           javelin-util
 *           phabricator-prefab
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
    this._repositories = config.repositories;
    this._inputTemplate = config.input_template;

    this._completeURI = config.completeURI;
    this._validateURI = config.validateURI;
    this._repositoryDefaultPaths = config.repositoryDefaultPaths;

    this._initializePaths(config.pathRefs);
  },
  members : {
    /*
     * MultirowRowManager for controlling add/remove behavior
     */
    _rowManager : null,

    /*
     * Array of objects with 'name' and 'repo_id' keys for
     * selecting the repository of a path.
     */
    _repositories : null,

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

    _repositoryDefaultPaths : null,

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
        this._lastRepositoryChoice = path_ref.repositoryPHID;
      }
      path_ref = path_ref || {};

      var selected_repository = path_ref.repositoryPHID ||
        this._lastRepositoryChoice;
      var options = this._buildRepositoryOptions(selected_repository);
      var attrs = {
        name : 'repo[' + this._count + ']',
        className : 'owners-repo'
      };
      var repo_select = JX.$N('select', attrs, options);

      JX.DOM.listen(repo_select, 'change', null, JX.bind(this, function() {
        this._lastRepositoryChoice = repo_select.value;
      }));

      var repo_cell = JX.$N('td', {}, repo_select);
      var typeahead_cell = JX.$N(
        'td',
        JX.$H(this._inputTemplate));

      // Text input for path.
      var path_input = JX.DOM.find(typeahead_cell, 'input');
      JX.copy(
        path_input,
        {
          value : path_ref.path || '',
          name : 'path[' + this._count + ']'
        });

      // The Typeahead requires a display div called hardpoint.
      var hardpoint = JX.DOM.find(
        typeahead_cell,
        'div',
        'typeahead-hardpoint');

      var error_display = JX.$N(
        'div',
        {
          className : 'error-display validating'
        },
        'Validating...');

      var error_display_cell = JX.$N('td', {}, error_display);

      var exclude = JX.Prefab.renderSelect(
        {'0' : 'Include', '1' : 'Exclude'},
        path_ref.excluded,
        {name : 'exclude[' + this._count + ']'});
      var exclude_cell = JX.$N('td', {}, exclude);

      var row = this._rowManager.addRow(
        [exclude_cell, repo_cell, typeahead_cell, error_display_cell]);

      new JX.PathTypeahead({
        repositoryDefaultPaths : this._repositoryDefaultPaths,
        repo_select : repo_select,
        path_input : path_input,
        hardpoint : hardpoint,
        error_display : error_display,
        completeURI : this._completeURI,
        validateURI : this._validateURI}).start();

      this._count++;
      return row;
    },

    _onaddpath : function(e) {
      e.kill();
      this.addPath();
    },

    /**
     * Helper to build the options for the repository choice dropdown.
     */
    _buildRepositoryOptions : function(selected) {
      var repos = this._repositories;
      var result = [];
      for (var k in repos) {
        var attr = {
          value : k,
          selected : (selected == k)
        };
        result.push(JX.$N('option', attr, repos[k]));
      }
      return result;
    }
  }
});
