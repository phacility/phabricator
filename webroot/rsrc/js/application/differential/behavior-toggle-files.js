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
        if (JX.Stratcom.hasSigil(elt, 'differential-diff') &&
            JX.Stratcom.getData(elt).hidden) {
          JX.Stratcom.invoke('differential-toggle-file', null, {
            diff: [ elt ],
          });
          return;
        }
        elt = elt.parentNode;
      }
    });

  JX.Stratcom.listen(
    'hashchange',
    null,
    function(e) {
      var id = window.location.hash;
      if (!id.match(/^#/)) {
        return;
      }
      JX.Stratcom.invoke('differential-toggle-file-request', null, {
        element: JX.$(id.substr(1)),
      });
      // This event is processed after the hash has changed, so it doesn't
      // automatically jump there like we want.
      JX.DOM.scrollTo(JX.$(id.substr(1)));
    });
});
