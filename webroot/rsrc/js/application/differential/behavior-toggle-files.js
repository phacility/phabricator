/**
 * @provides javelin-behavior-differential-toggle-files
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-phtize
 */

JX.behavior('differential-toggle-files', function(config) {
  var pht = JX.phtize(config.pht);

  JX.Stratcom.listen(
    'differential-toggle-file',
    null,
    function(e) {
      if (e.getData().diff.length != 1) {
        return;
      }

      var diff = e.getData().diff[0],
          data = JX.Stratcom.getData(diff);
      if (data.hidden) {
        data.hidden = false;
        JX.DOM.show(diff);
        JX.DOM.remove(data.undo);
        data.undo = null;
      } else {
        data.hidden = true;
        data.undo = render_collapse_undo();
        JX.DOM.hide(diff);
        JX.DOM.listen(
          data.undo,
          'click',
          'differential-collapse-undo',
          function(e) {
            e.kill();
            data.hidden = false;
            JX.DOM.show(diff);
            JX.DOM.remove(data.undo);
            data.undo = null;
          });
        JX.DOM.appendContent(diff.parentNode, data.undo);
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
          var invoked = false;
          for (var i = 0; i < diffs.length; ++i) {
            if (JX.Stratcom.getData(diffs[i]).hidden) {
              JX.Stratcom.invoke('differential-toggle-file', null, {
                diff: [ diffs[i] ]
              });
              invoked = true;
            }
          }
          if (!invoked) {
            e.prevent();
          }
          return;
        }
        elt = elt.parentNode;
      }
      e.prevent();
    });

  JX.Stratcom.listen(
    'click',
    'tag:a',
    function(e) {
      var link = e.getNode('tag:a');
      var id = link.getAttribute('href');
      if (!id || !id.match(/^#.+/)) {
        return;
      }
      var raw = e.getRawEvent();
      if (raw.altKey || raw.ctrlKey || raw.metaKey || raw.shiftKey) {
        return;
      }
      // The target may have either a matching name or a matching id.
      var target;
      try {
        target = JX.$(id.substr(1));
      } catch(err) {
        var named = document.getElementsByName(id.substr(1));
        for (var i = 0; i < named.length; ++i) {
          if (named[i].tagName.toLowerCase() == 'a') {
            if (target) {
              return;
            }
            target = named[i];
          }
        }
        if (!target) {
          return;
        }
      }
      var event = JX.Stratcom.invoke('differential-toggle-file-request', null, {
        element: target
      });
      if (!event.getPrevented()) {
        // This event is processed after the hash has changed, so it doesn't
        // automatically jump there like we want.
        JX.DOM.scrollTo(target);
      }
  });

  var render_collapse_undo = function() {
    var link = JX.$N(
      'a',
      {href: '#', sigil: 'differential-collapse-undo'},
      pht('undo'));

    return JX.$N(
      'div',
      {className: 'differential-collapse-undo',
       sigil: 'differential-collapse-undo-div'},
      [pht('collapsed'), ' ', link]);
  };

});
