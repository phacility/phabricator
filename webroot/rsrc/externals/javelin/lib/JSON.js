/**
 * Simple JSON serializer.
 *
 * @requires javelin-install
 * @provides javelin-json
 * @javelin
 */

/**
 * JSON serializer and parser. This class uses the native JSON parser if it is
 * available; if not, it provides an eval-based parser and a simple serializer.
 *
 * NOTE: This class uses eval() on some systems, without sanitizing input. It is
 * not safe to use with untrusted data. Javelin does not provide a library
 * suitable for parsing untrusted JSON.
 *
 * Usage is straightforward:
 *
 *    JX.JSON.stringify({"bees":"knees"}); // Returns string: '{"bees":"knees"}'
 *    JX.JSON.parse('{"bees":"knees"}');   // Returns object: {"bees":"knees"}
 *
 * @task json      JSON Manipulation
 * @task internal  Internal
 */
JX.install('JSON', {
  statics : {


/* -(  JSON Manipulation  )-------------------------------------------------- */


    /**
     * Parse a **trusted** JSON string into an object. Accepts a valid JSON
     * string and returns the object it encodes.
     *
     * NOTE: This method does not sanitize input and uses an eval-based parser
     * on some systems. It is **NOT SAFE** to use with untrusted inputs.
     *
     * @param   string A valid, trusted JSON string.
     * @return  object The object encoded by the JSON string.
     * @task json
     */
    parse : function(data) {
      if (typeof data != 'string') {
        return null;
      }

      if (window.JSON && JSON.parse) {
        var obj;
        try {
          obj = JSON.parse(data);
        } catch (e) {}
        return obj || null;
      }

      return eval('(' + data + ')');
    },

    /**
     * Serialize an object into a JSON string. Accepts an object comprised of
     * maps, lists and scalars and transforms it into a JSON representation.
     * This method has undefined behavior if you pass in other complicated
     * things, e.g. object graphs containing cycles, document.body, or Date
     * objects.
     *
     * @param   object  An object comprised of maps, lists and scalars.
     * @return  string  JSON representation of the object.
     * @task json
     */
    stringify : function(val) {
      if (window.JSON && JSON.stringify) {
        return JSON.stringify(val);
      }

      var out = [];
      if (
        val === null || val === true || val === false || typeof val == 'number'
      ) {
        return '' + val;
      }

      if (val.push && val.pop) {
        var v;
        for (var ii = 0; ii < val.length; ii++) {

          // For consistency with JSON.stringify(), encode undefined array
          // indices as null.
          v = (typeof val[ii] == 'undefined') ? null : val[ii];

          out.push(JX.JSON.stringify(v));
        }
        return '[' + out.join(',') + ']';
      }

      if (typeof val == 'string') {
        return JX.JSON._esc(val);
      }

      for (var k in val) {
        out.push(JX.JSON._esc(k) + ':' + JX.JSON.stringify(val[k]));
      }
      return '{' + out.join(',') + '}';
    },


/* -(  Internal  )----------------------------------------------------------- */


    // Lifted more or less directly from Crockford's JSON2.
    _escexp : /[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,

    // List of control character escape codes.
    _meta : {
      '\b' : '\\b',
      '\t' : '\\t',
      '\n' : '\\n',
      '\f' : '\\f',
      '\r' : '\\r',
      '"'  : '\\"',
      '\\' : '\\\\'
    },

    /**
     * Quote and escape a string for inclusion in serialized JSON. Finds
     * characters in the string which need to be escaped and uses
     * @{method:_replace} to escape them.
     *
     * @param string Unescaped string.
     * @return string Escaped string.
     * @task internal
     */
    _esc : function(str) {
      JX.JSON._escexp.lastIndex = 0;
      return JX.JSON._escexp.test(str) ?
        '"' + str.replace(JX.JSON._escexp, JX.JSON._replace) + '"' :
        '"' + str + '"';
    },

    /**
     * Helper callback for @{method:_esc}, escapes characters which can't be
     * represented normally in serialized JSON.
     *
     * @param string Unescaped character.
     * @return string Escaped character.
     * @task internal
     */
    _replace : function(m) {
      if (m in JX.JSON._meta) {
        return JX.JSON._meta[m];
      }
      return '\\u' + (('0000' + m.charCodeAt(0).toString(16)).slice(-4));
    }
  }
});
