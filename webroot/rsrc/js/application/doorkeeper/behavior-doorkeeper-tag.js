/**
 * @provides javelin-behavior-doorkeeper-tag
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-json
 *           javelin-workflow
 *           javelin-magical-init
 */

JX.behavior('doorkeeper-tag', function(config, statics) {
  statics.tags = (statics.tags || []).concat(config.tags);
  statics.cache = statics.cache || {};

  // NOTE: We keep a cache in the browser of external objects that we've already
  // looked up. This is mostly to keep previews from being flickery messes.

  var load = function() {
    var tags = statics.tags;
    statics.tags = [];

    if (!tags.length) {
      return;
    }

    var have = [];
    var need = [];
    var keys = {};

    var draw = function(tags) {
      for (var ii = 0; ii < tags.length; ii++) {
        try {
          JX.DOM.replace(JX.$(tags[ii].id), JX.$H(tags[ii].markup));
        } catch (ignored) {
          // The tag may have been wiped out of the body by the time the
          // response returns, for whatever reason. That's fine, just don't
          // bother drawing it.
        }
        statics.cache[keys[tags[ii].id]] = tags[ii].markup;
      }
    };

    for (var ii = 0; ii < tags.length; ii++) {
      var tag_key = tags[ii].ref.join('@');
      if (tag_key in statics.cache) {
        have.push({id: tags[ii].id, markup: statics.cache[tag_key]});
      } else {
        need.push(tags[ii]);
        keys[tags[ii].id] = tag_key;
      }
    }

    if (have.length) {
      draw(have);
    }

    if (need.length) {
      new JX.Workflow('/doorkeeper/tags/', {tags: JX.JSON.stringify(need)})
        .setHandler(function(r) { draw(r.tags); })
        .start();
    }
  };

  JX.onload(load);
});
