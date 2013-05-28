/**
 * @provides javelin-behavior-countdown-timer
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('countdown-timer', function(config) {

  var container = JX.$(config.container);
  calculateTimeLeft();

  function setComponent(which, content) {
    var component = JX.DOM.find(container, '*', 'phabricator-timer-' + which);
    JX.DOM.setContent(component, content);
  }

  function calculateTimeLeft() {
    var days = 0;
    var hours = 0;
    var minutes = 0;
    var seconds = 0;

    var current_timestamp = Math.round(new Date() / 1000);
    var delta = config.timestamp - current_timestamp;

    if (delta > 0) {
      days = Math.floor(delta/86400);
      delta -= days * 86400;

      hours = Math.floor(delta/3600);
      delta -= hours * 3600;

      minutes = Math.floor(delta / 60);
      delta -= minutes * 60;

      seconds = delta;

      setTimeout(calculateTimeLeft, 1000);
    }

    setComponent('days', days);
    setComponent('hours', hours);
    setComponent('minutes', minutes);
    setComponent('seconds', seconds);
  }
});

