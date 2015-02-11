/**
 * @provides javelin-behavior-slowvote-embed
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-stratcom
 *           javelin-dom
 */
JX.behavior('slowvote-embed', function() {
  JX.Stratcom.listen(
    ['click'],
    'slowvote-option',
    function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }
      e.kill();

      var pollID = e.getNodeData('slowvote-embed').pollID;
      var voteURI = '/vote/' + pollID + '/';

      var request = new JX.Request(voteURI, function(r) {
        var updated_poll = JX.$H(r.contentHTML);
        var root = JX.$('phabricator-standard-page');

        var polls = JX.DOM.scry(root, 'div', 'slowvote-embed');

        for(var i = 0; i < polls.length; i++) {
          var data = JX.Stratcom.getData(polls[i]);

          if (data.pollID == pollID) {
            JX.DOM.replace(polls[i], updated_poll);
          }

        }

      });

      request.addData({vote: e.getNodeData('slowvote-option').optionID});
      request.send();

    });

});
