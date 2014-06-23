/**
 * @provides javelin-behavior-phame-post-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('phame-post-preview', function(config) {

  var body        = JX.$(config.body);
  var title       = JX.$(config.title);
  var phame_title = JX.$(config.phame_title);
  var sync_titles = true;

  var titleCallback = function() {
    if (!sync_titles) {
      return;
    }
    var title_string  = title.value;
    phame_title.value = normalizeSlug(title_string);
  };

  var phameTitleKeyupCallback = function () {
    // stop sync'ing once user edits phame_title directly
    sync_titles    = false;
    var normalized = normalizeSlug(phame_title.value, true);
    if (normalized == phame_title.value) {
      return;
    }
    var position = phame_title.value.length;
    if ('selectionStart' in phame_title) {
      position = phame_title.selectionStart;
    }
    phame_title.value = normalized;
    if ('setSelectionRange' in phame_title) {
      phame_title.focus();
      phame_title.setSelectionRange(position, position);
    }
  };

  var phameTitleBlurCallback = function () {
    phame_title.value = normalizeSlug(phame_title.value);
  };

  // This is a sort of implementation of PhabricatorSlug::normalize
  var normalizeSlug = function (slug, spare_trailing_underscore) {
    var s = slug.toLowerCase().replace(/[^a-z0-9/]+/g, '_').substr(0, 63);
    if (spare_trailing_underscore) {
      // do nothing
    } else {
      s = s.replace(/_$/g, '');
    }
    return s;
  };

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    return {
      body        : body.value,
      title       : title.value,
      phame_title : phame_title.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(body,        'keydown', null, trigger);
  JX.DOM.listen(title,       'keydown', null, trigger);
  JX.DOM.listen(title,       'keyup',   null, titleCallback);
  JX.DOM.listen(phame_title, 'keydown', null, trigger);
  JX.DOM.listen(phame_title, 'keyup',   null, phameTitleKeyupCallback);
  JX.DOM.listen(phame_title, 'blur',    null, phameTitleBlurCallback);
  request.start();

});
