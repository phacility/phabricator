/**
 * @provides phabricator-phtize
 * @requires javelin-util
 * @javelin-installs JX.phtize
 * @javelin
 */

JX.phtize = function(config) {

  return function(text) {
    if (!(text in config)) {
      if (__DEV__) {
        JX.$E('pht("' + text + '"): translation was not configured.');
      }

      return text;
    }

    return config[text];
  };

};
