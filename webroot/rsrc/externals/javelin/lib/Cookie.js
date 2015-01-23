/**
 * @provides javelin-cookie
 * @requires javelin-install
 *           javelin-util
 * @javelin
 */

/*
 * API/Wrapper for document cookie access and manipulation based heavily on the
 * MooTools Cookie.js
 *
 * github.com/mootools/mootools-core/blob/master/Source/Utilities/Cookie.js
 *
 * Thx again, Moo.
 */
JX.install('Cookie', {

  /**
   * Setting cookies involves setting up a cookie object which is eventually
   * written.
   *
   *   var prefs = new JX.Cookie('prefs');
   *   prefs.setDaysToLive(5);
   *   prefs.setValue('1,0,10,1350');
   *   prefs.setSecure();
   *   prefs.write();
   *
   * Retrieving a cookie from the browser requires only a read() call on the
   * cookie object. However, because cookies have such a complex API you may
   * not be able to get your value this way if a path or domain was set when the
   * cookie was written. Good luck with that.
   *
   *   var prefs_string = new JX.Cookie('prefs').read();
   *
   * There is no real API in HTTP for deleting a cookie aside from setting the
   * cookie to expire immediately. This dispose method will null out the value
   * and expire the cookie as well.
   *
   *   new JX.Cookie('prefs').dispose();
   */
  construct : function(key) {
    if (__DEV__ &&
        (!key.length ||
         key.match(/^(?:expires|domain|path|secure)$/i) ||
         key.match(/[\s,;]/) ||
         key.indexOf('$') === 0)) {
      JX.$E('JX.Cookie(): Invalid cookie name. "' + key + '" provided.');
    }
    this.setKey(key);
    this.setTarget(document);
  },

  properties : {
    key : null,
    value : null,
    domain : null,
    path : null,
    daysToLive : 0,
    secure : true,
    target : null
  },

  members : {
    write : function() {
      this.setValue(encodeURIComponent(this.getValue()));

      var cookie_bits = [];
      cookie_bits.push(this.getValue());

      if (this.getDomain()) {
        cookie_bits.push('Domain=' + this.getDomain());
      }

      if (this.getPath()) {
        cookie_bits.push('Path=' + this.getPath());
      }

      var exp = new Date(JX.now() + this.getDaysToLive() * 1000 * 60 * 60 * 24);
      cookie_bits.push('Expires=' + exp.toGMTString());

      if (this.getSecure()) {
        cookie_bits.push('Secure');
      }

      var cookie_str = cookie_bits.join('; ') + ';';
      cookie_str = this.getKey() + '=' + cookie_str;
      this.getTarget().cookie = cookie_str;
    },

    read : function() {
      var key = this.getKey().replace(/([-.*+?^${}()|[\]\/\\])/g, '\\$1');
      var val = this.getTarget().cookie.match('(?:^|;)\\s*' + key + '=([^;]*)');
      return (val) ? decodeURIComponent(val[1]) : null;
    },

    dispose : function() {
      this.setValue(null);
      this.setDaysToLive(-1);
      this.write();
    }
  }
});
