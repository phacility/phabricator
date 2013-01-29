/**
 * @provides javelin-behavior-pholio-mock-view
 * @requires javelin-behavior
 *           javelin-stratcom
 */
JX.behavior('pholio-mock-view', function(config) {
  JX.Stratcom.listen(
    'click', // Listen for clicks...
    'mock-thumbnail', // ...on nodes with sigil "mock-thumbnail".
    function(e) {
      var data = e.getNodeData('mock-thumbnail');

      var main = JX.$(config.mainID);
      main.src = data.fullSizeURI;
    });
});

