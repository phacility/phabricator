/**
 * @provides javelin-behavior-countdown-timer
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('countdown-timer', function(config) {
  try {
    var container = JX.$(config.container);
  } catch (ignored) {
    return;
  }

  function setComponent(which, content) {
    var component = JX.DOM.find(container, '*', 'phabricator-timer-' + which);
    JX.DOM.setContent(component, content);
  }

  function calculateTimeLeft() {
    var days = 0;
    var hours = 0;
    var minutes = 0;
    var seconds = 0;
    var partial = 0;

    var current_timestamp = +new Date();

    var delta = (config.timestamp * 1000) - current_timestamp;

    if (delta > 0) {
      days = Math.floor(delta / 86400000);
      delta -= days * 86400000;

      hours = Math.floor(delta / 3600000);
      delta -= hours * 3600000;

      minutes = Math.floor(delta / 60000);
      delta -= minutes * 60000;

      seconds = Math.floor(delta / 1000);
      delta -= seconds * 1000;

      partial = Math.floor(delta / 100) || '0';

      setTimeout(calculateTimeLeft, 100);
    }

    setComponent('days', days);
    setComponent('hours', hours);
    setComponent('minutes', minutes);
    setComponent('seconds', [seconds, JX.$N('small', {}, ['.', partial])]);
  }

  calculateTimeLeft();
});
