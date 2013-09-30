/**
 * @provides javelin-behavior-audio-source
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 * @javelin
 */

/**
 * Allows an element to behave as an audio source. It plays a sound either
 * when the user scrolls it into view, or loops a sound which gets louder and
 * louder as the user gets closer.
 */
JX.behavior('audio-source', function(config, statics) {
  if (!window.Audio) {
    return;
  }

  var audio = new Audio();
  audio.setAttribute('src', config.audioURI);

  if (config.loop) {
    audio.setAttribute('loop', true);
  }

  audio.load();

  config.audio = audio;

  statics.items = statics.items || [];
  statics.items.push(config);

  if (statics.initialized) {
    return;
  }
  statics.initialized = true;

  var onupdate = function() {
    timeout = null;

    var scroll = JX.Vector.getScroll();
    var view = JX.Vector.getViewport();
    var view_mid = scroll.y + (view.y / 2);

    for (var ii = 0; ii < statics.items.length; ii++) {
      var item = statics.items[ii];
      if (!item.element) {
        try {
          item.element = JX.$(item.sourceID);
        } catch (ignored) {
          continue;
        }
      }

      var pos = JX.Vector.getPos(statics.items[ii].element);
      var dim = JX.Vector.getDim(statics.items[ii].element);

      var item_mid = pos.y + (dim.y / 2);
      var item_distance = Math.abs(item_mid - view_mid);

      // item_distance is the number of pixels between the vertical middle
      // of the macro and the vertical middle of the viewport. We divide it
      // by the viewport height to get the "number of viewports" away from
      // the middle we are, then map that to [0, 1], where 0 means that the
      // image is far away from the viewport and 1 means the image is pretty
      // much in the middle of the viewport.

      var near = 1.25 - ((item_distance / view.y) * 1.25);
      near = Math.max(0, near);
      near = Math.min(1, near);

      if (near === 0) {
        if (item.playing) {
          item.audio.pause();
          item.playing = false;

          // If this isn't an ambient/looping sound, it only gets to fire
          // once. Even if it didn't finish, throw it out.
          if (!item.loop) {
            statics.items.splice(ii, 1);
            ii--;
          }
        }
        continue;
      } else {
        if (!item.playing) {
          if (!item.loop && near < 1) {
            // Don't start playing one-shot items until they're solidly on
            // screen.
            continue;
          }
          item.audio.volume = near;
          item.playing = true;
          item.audio.play();
        } else {
          item.audio.volume = near;
        }
      }
    }

  };

  var timeout;
  var onadjust = function() {
    timeout && clearTimeout(timeout);
    timeout = setTimeout(onupdate, 200);
  };

  JX.Stratcom.listen(['scroll', 'resize'], null, onadjust);
  onadjust();

});
