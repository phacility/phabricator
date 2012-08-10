/**
 * @provides javelin-behavior-ponder-votebox
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('ponder-votebox', function(config) {

  var node = JX.$(config.nodeid);
  var vote = config.vote;
  var count = config.count | 0;
  var targetURI = config.targetURI;

  var upnode, countnode, downnode;

  // this defines the behavior of the up/downvote
  // buttons, e.g. clicking 'up' transitions from
  // an 'up' vote to a 'none' vote
  var votecycle = {
    "1"  : { up : "0", down : "-1" },
    "0"  : { up : "1", down : "-1" },
    "-1" : { up : "1",  down : "0" }
  };

  var voteclass = {
    "0"  : "ponder-vote-none",
    "-1" : "ponder-vote-down",
    "1"  : "ponder-vote-up"
  };

  function decorate() {
    upnode = JX.$N('div');
    countnode = JX.$N('div');
    downnode = JX.$N('div');
    node.appendChild(upnode);
    node.appendChild(countnode);
    node.appendChild(downnode);
    JX.DOM.alterClass(upnode, "ponder-upbutton " + voteclass[vote], true);
    JX.DOM.alterClass(downnode, "ponder-downbutton " + voteclass[vote], true);
    JX.DOM.alterClass(countnode, "ponder-votecount", true);
  }

  function update_state() {
    upnode.className = "ponder-upbutton " + voteclass[vote];
    downnode.className = "ponder-downbutton " + voteclass[vote];
    JX.DOM.setContent(countnode, JX.$H(count.toString()));
  }

  function getdata() {
    return { phid : config.nodeid, vote : vote };
  }

  var request = new JX.PhabricatorShapedRequest(config.uri, JX.id, getdata);
  var trigger = JX.bind(request, request.trigger);

  function handle_upvote(e) {
    count += votecycle[vote].up - vote;
    vote = votecycle[vote].up;
    trigger();
    update_state();
  }

  function handle_downvote(e) {
    count += votecycle[vote].down - vote;
    vote = votecycle[vote].down;
    trigger();
    update_state();
  }

  decorate();
  update_state();
  JX.DOM.listen(upnode, 'click', null, handle_upvote);
  JX.DOM.listen(downnode, 'click', null, handle_downvote);
});
