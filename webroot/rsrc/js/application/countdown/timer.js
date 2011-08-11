/**
 * @provides javelin-behavior-countdown-timer
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 */

JX.behavior('countdown-timer', function(config) {

  calculateTimeLeft();

  function calculateTimeLeft() {
    var days = 0;
    var hours = 0;
    var minutes = 0;
    var seconds = 0;

    var current_timestamp = Math.round(new Date() / 1000);
    var delta = config.timestamp - current_timestamp;

    if (delta <= 0) {
      JX.DOM.setContent(JX.$('phabricator-timer-days'), days);
      JX.DOM.setContent(JX.$('phabricator-timer-hours'), hours);
      JX.DOM.setContent(JX.$('phabricator-timer-minutes'), minutes);
      JX.DOM.setContent(JX.$('phabricator-timer-seconds'), seconds);
      return;
    }

    days = Math.floor(delta/86400);
    delta -= days * 86400;

    hours = Math.floor(delta/3600);
    delta -= hours * 3600;

    minutes = Math.floor(delta / 60);
    delta -= minutes * 60;

    seconds = delta;

    JX.DOM.setContent(JX.$('phabricator-timer-days'), days);
    JX.DOM.setContent(JX.$('phabricator-timer-hours'), hours);
    JX.DOM.setContent(JX.$('phabricator-timer-minutes'), minutes);
    JX.DOM.setContent(JX.$('phabricator-timer-seconds'), seconds);

    setTimeout(calculateTimeLeft, 1000);
  }
});

