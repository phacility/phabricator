/**
 * @provides javelin-fx
 * @requires javelin-color javelin-install javelin-util
 * @javelin
 *
 * Based on moo.fx (moofx.mad4milk.net).
 */

JX.install('FX', {

  events: ['start', 'complete'],

  construct: function(element) {
    this._config = {};
    this.setElement(element);
    this.setTransition(JX.FX.Transitions.sine);
  },

  properties: {
    fps: 50,
    wait: true,
    duration: 500,
    element: null,
    property: null,
    transition: null
  },

  members: {
    _to: null,
    _now: null,
    _from: null,
    _start: null,
    _config: null,
    _interval: null,

    start: function(config) {
      if (__DEV__) {
        if (!config) {
          throw new Error('What styles do you want to animate?');
        }
        if (!this.getElement()) {
          throw new Error('What element do you want to animate?');
        }
      }
      if (this._interval && this.getWait()) {
        return;
      }
      var from = {};
      var to = {};
      for (var prop in config) {
        from[prop] = config[prop][0];
        to[prop] = config[prop][1];
        if (/color/i.test(prop)) {
          from[prop] = JX.Color.hexToRgb(from[prop], true);
          to[prop] = JX.Color.hexToRgb(to[prop], true);
        }
      }
      this._animate(from, to);
      return this;
    },

    stop: function() {
      clearInterval(this._interval);
      this._interval = null;
      return this;
    },

    then: function(func) {
      var token = this.listen('complete', function() {
        token.remove();
        func();
      });
      return this;
    },

    _animate: function(from, to) {
      if (!this.getWait()) {
        this.stop();
      }
      if (this._interval) {
        return;
      }
      setTimeout(JX.bind(this, this.invoke, 'start'), 10);
      this._from = from;
      this._to = to;
      this._start = JX.now();
      this._interval = setInterval(
        JX.bind(this, this._tween),
        Math.round(1000 / this.getFps()));

      // Immediately update to the initial values.
      this._tween();
    },

    _tween: function() {
      var now = JX.now();
      var prop;
      if (now < this._start + this.getDuration()) {
        this._now = now - this._start;
        for (prop in this._from) {
          this._config[prop] = this._compute(this._from[prop], this._to[prop]);
        }
      } else {
        setTimeout(JX.bind(this, this.invoke, 'complete'), 10);

        // Compute the final position using the transition function, in case
        // the function applies transformations.
        this._now = this.getDuration();
        for (prop in this._from) {
          this._config[prop] = this._compute(this._from[prop], this._to[prop]);
        }
        this.stop();
      }
      this._render();
    },

    _compute: function(from, to) {
      if (JX.isArray(from)) {
        return from.map(function(value, ii) {
          return Math.round(this._compute(value, to[ii]));
        }, this);
      }
      var delta = to - from;
      return this.getTransition()(this._now, from, delta, this.getDuration());
    },

    _render: function() {
      var style = this.getElement().style;
      for (var prop in this._config) {
        var value = this._config[prop];
        if (prop == 'opacity') {
          value = parseInt(100 * value, 10);
          if (window.ActiveXObject) {
            style.filter = 'alpha(opacity=' + value + ')';
          } else {
            style.opacity = value / 100;
          }
        } else if (/color/i.test(prop)) {
          style[prop] = 'rgb(' + value + ')';
        } else {
          style[prop] = value + 'px';
        }
      }
    }
  },

  statics: {
    fade: function(element, visible) {
      return new JX.FX(element).setDuration(250).start({
        opacity: visible ? [0, 1] : [1, 0]
      });
    },

    highlight: function(element, color) {
      color = color || '#fff8dd';
      return new JX.FX(element).setDuration(1000).start({
        backgroundColor: [color, '#fff']
      });
    },

    /**
     * Easing equations based on work by Robert Penner
     * http://www.robertpenner.com/easing/
     */
    Transitions: {
      linear: function(t, b, c, d) {
        return c * t / d + b;
      },

      sine: function(t, b, c, d) {
        return -c / 2 * (Math.cos(Math.PI * t / d) - 1) + b;
      },

      sineIn: function(t, b, c, d) {
        if (t == d) {
          return c + b;
        }
        return -c * Math.cos(t / d * (Math.PI / 2)) + c + b;
      },

      sineOut: function(t, b, c, d) {
        if (t == d) {
          return c + b;
        }
        return c * Math.sin(t / d * (Math.PI / 2)) + b;
      },

      elastic: function(t, b, c, d, a, p) {
        if (t === 0) { return b; }
        if ((t /= d) == 1) { return b + c; }
        if (!p) { p = d * 0.3; }
        if (!a) { a = 1; }
        var s;
        if (a < Math.abs(c)) {
          a = c;
          s = p / 4;
        } else {
          s = p / (2 * Math.PI) * Math.asin(c / a);
        }
        return a * Math.pow(2, -10 * t) *
          Math.sin((t * d - s) * (2 * Math.PI) / p) + c + b;
      },

      bounce: function(t, b, c, d) {
        if ((t /= d) < (1 / 2.75)) {
          return c * (7.5625 * t * t) + b;
        } else if (t < (2 / 2.75)) {
          return c * (7.5625 * (t -= (1.5 / 2.75)) * t + 0.75) + b;
        } else if (t < (2.5 / 2.75)) {
          return c * (7.5625 * (t -= (2.25 / 2.75)) * t + 0.9375) + b;
        } else {
          return c * (7.5625 * (t -= (2.625 / 2.75)) * t + 0.984375) + b;
        }
      }
    }
  }
});
