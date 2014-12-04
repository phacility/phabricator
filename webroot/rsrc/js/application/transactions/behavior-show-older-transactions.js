/**
 * @provides javelin-behavior-phabricator-show-older-transactions
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phabricator-busy
 */

JX.behavior('phabricator-show-older-transactions', function(config) {

  var loading = false;

  function get_hash() {
    return window.location.hash.replace(/^#/, '');
  }

  function hash_is_hidden() {
    var hash = get_hash();
    if (!hash) {
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
      var showOlderBlock = null;
      try {
        showOlderBlock = JX.DOM.find(
          JX.$(config.timelineID),
          'div',
          'show-older-block');
      } catch (not_found_exception) {
        // probably a garbage hash and we loaded everything looking
        // for it; just abort
        if (loading) {
          loading = false;
          JX.Busy.done();
        }
        return;
      }
      var showOlderLink = JX.DOM.find(
        showOlderBlock,
        'a',
        'show-older-link');
      if (!loading) {
        loading = true;
        JX.Busy.start();
      }
      fetch_older_workflow(
        showOlderLink.href,
        load_hidden_hash,
        showOlderBlock)
      .start();
    } else {
      if (loading) {
        loading = false;
        JX.Busy.done();
      }
      try {
        var target = JX.$(get_hash());
        JX.DOM.scrollTo(target);
      } catch (ignored) {
        // We did our best.
      }
    }
  }

  var show_older = function(swap, r) {
    JX.DOM.replace(swap, JX.$H(r.timeline).getFragment());
  };

  var load_hidden_hash = function(swap, r) {
    show_older(swap, r);
    check_hash();
  };

  var fetch_older_workflow = function(href, callback, swap) {
    return new JX.Workflow(href, config.renderData)
      .setHandler(JX.bind(null, callback, swap));
  };

  JX.Stratcom.listen(
    'click',
    ['show-older-link'],
    function(e) {
      e.kill();
      fetch_older_workflow(
        e.getNode('tag:a').href,
        show_older,
        e.getNode('show-older-block'))
      .start();
    });

  JX.Stratcom.listen('hashchange', null, check_hash);
  check_hash();

});
