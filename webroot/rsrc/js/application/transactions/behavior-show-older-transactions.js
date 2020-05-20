/**
 * @provides javelin-behavior-phabricator-show-older-transactions
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phabricator-busy
 */

JX.behavior('phabricator-show-older-transactions', function(config) {

  function get_hash() {
    return window.location.hash.replace(/^#/, '');
  }

  function hash_is_hidden() {
    var hash = get_hash();
    if (!hash) {
      return false;
    }

    // If the hash isn't purely numeric, ignore it. Comments always have
    // numeric hashes. See PHI43 and T12970.
    if (!hash.match(/^\d+$/)) {
      return false;
    }

    var id = 'anchor-'+hash;
    try {
      JX.$(id);
    } catch (not_found_exception) {
      return true;
    }
    return false;
  }

  function check_hash() {
    if (hash_is_hidden()) {
      load_older(load_hidden_hash_callback);
    }
  }

  function load_older(callback) {
    var showOlderBlock = null;
    try {
      showOlderBlock = JX.DOM.find(
        JX.$(config.timelineID),
        'div',
        'show-older-block');
    } catch (not_found_exception) {
      // we loaded everything...!
      return;
    }

    var showOlderLink = JX.DOM.find(
      showOlderBlock,
      'a',
      'show-older-link');
    var workflow = fetch_older_workflow(
      showOlderLink.href,
      callback,
      showOlderBlock);
    var routable = workflow.getRoutable()
      .setPriority(2000)
      .setType('workflow');
    JX.Router.getInstance().queue(routable);
  }

  var show_older = function(swap, r) {
    JX.DOM.replace(swap, JX.$H(r.timeline).getFragment());
    JX.Stratcom.invoke('resize');
  };

  var load_hidden_hash_callback = function(swap, r) {
    show_older(swap, r);

    // We aren't actually doing a scroll position because
    // `behavior-watch-anchor` will handle that for us.
  };

  var load_all_older_callback = function(swap, r) {
    show_older(swap, r);
    load_older(load_all_older_callback);
  };

  var fetch_older_workflow = function(href, callback, swap) {
    var params = {
      viewData: JX.JSON.stringify(config.viewData)
    };

    return new JX.Workflow(href, params)
      .setHandler(JX.bind(null, callback, swap));
  };

  JX.Stratcom.listen(
    'click',
    ['show-older-block'],
    function(e) {
      e.kill();
      var workflow = fetch_older_workflow(
        JX.DOM.find(
          e.getNode('show-older-block'),
          'a',
          'show-older-link').href,
        show_older,
        e.getNode('show-older-block'));
      var routable = workflow.getRoutable()
        .setPriority(2000)
        .setType('workflow');
      JX.Router.getInstance().queue(routable);
    });

  JX.Stratcom.listen('hashchange', null, check_hash);

  check_hash();

  new JX.KeyboardShortcut(['@'], 'Show all older changes in the timeline.')
    .setGroup('xactions')
    .setHandler(JX.bind(null, load_older, load_all_older_callback))
    .register();
});
