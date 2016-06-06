/**
 * @provides javelin-behavior-setup-check-https
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('setup-check-https', function(config) {

  var server_https = config.server_https;

  var client_uri = new JX.URI(window.location.href);
  var client_protocol = client_uri.getProtocol();
  var client_https = (client_protocol === 'https');

  if (server_https === client_https) {
    return;
  }

  var doc_link = JX.$N(
    'a',
    {
      href: config.doc_href,
      target: '_blank'
    },
    config.doc_name);

  var content = [
    config.message,
    ' ',
    doc_link,
  ];

  new JX.Notification()
    .alterClassName('jx-notification-alert', true)
    .setContent(content)
    .setDuration(0)
    .show();
});
