/**
 * @provides javelin-behavior-ponder-votebox
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-request
 */

JX.behavior('ponder-votebox', function() {

  function handle_vote(e, vote) {
    e.kill();

    var root = e.getNode('ponder-votable');
    var data = e.getNodeData('ponder-votable');

    if (data.vote != vote) {
      // Remove the user's current vote, if they have one.
      data.count -= data.vote;
      data.vote = vote;
      data.count += vote;
    } else {
      // User is undoing their vote.
      data.vote = 0;
      data.count -= vote;
    }

    var upv = JX.DOM.find(root, 'a', 'upvote');
    JX.DOM.alterClass(upv, 'ponder-vote-active', (data.vote > 0));

    var downv = JX.DOM.find(root, 'a', 'downvote');
    JX.DOM.alterClass(downv, 'ponder-vote-active', (data.vote < 0));

    JX.DOM.setContent(
      JX.DOM.find(root, 'div', 'ponder-vote-count'),
      data.count);

    new JX.Request(e.getTarget().href, JX.bag)
      .setData({vote: data.vote})
      .send();
  }

  JX.Stratcom.listen(
    'click',
    'downvote',
    function(e) {
      handle_vote(e, -1);
    });

  JX.Stratcom.listen(
    'click',
    'upvote',
    function(e) {
      handle_vote(e, 1);
    });
});
