/**
 * @provides javelin-behavior-differential-toggle-files
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-keyboard-shortcut
 */

JX.behavior('differential-toggle-files', function(config) {

  JX.Stratcom.listen(
    'differential-toggle-file',
    null,
    function(e) {
      if (e.getData().diff.length != 1) {
        return;
      }
      var diff = e.getData().diff[0],
          data = JX.Stratcom.getData(diff);
      if(data.hidden) {
        data.hidden = false;
        JX.DOM.show(diff);
      } else {
        data.hidden = true;
        JX.DOM.hide(diff);
      }
      JX.Stratcom.invoke('differential-toggle-file-toggled');
    });

  JX.Stratcom.listen(
    'differential-toggle-file-request',
    null,
    function(e) {
      var elt = e.getData().element;
      while (elt !== document.body) {
        if (JX.Stratcom.hasSigil(elt, 'differential-changeset')) {
          var diffs = JX.DOM.scry(elt, 'table', 'differential-diff');
          for (var i = 0; i < diffs.length; ++i) {
            if (JX.Stratcom.getData(diffs[i]).hidden) {
              JX.Stratcom.invoke('differential-toggle-file', null, {
                diff: [ diffs[i] ],
              });
            }
          }
          return;
        }
        elt = elt.parentNode;
      }
    });

  JX.Stratcom.listen(
    'click',
    'tag:a',
    function(e) {
      var link = e.getNode('tag:a');
      var id = link.getAttribute('href');
      if (!id.match(/^#.+/)) {
        return;
      }
      // The target may have either a matching name or a matching id.
      var target;
      try {
        target = JX.$(id.substr(1));
      } catch(err) {
        var named = document.getElementsByName(id.substr(1));
        var matches = [];
        for (var i = 0; i < named.length; ++i) {
          if (named[i].tagName.toLowerCase() == 'a') {
            matches.push(named[i]);
          }
        }
        if (matches.length == 1) {
          target = matches[0];
        } else {
          return;
        }
      }
      JX.Stratcom.invoke('differential-toggle-file-request', null, {
        element: target,
      });
      // This event is processed after the hash has changed, so it doesn't
      // automatically jump there like we want.
      JX.DOM.scrollTo(target);
    });
});
