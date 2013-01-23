/**
 * @requires javelin-cookie
 */

/*
 * These all are hope-and-pray tests because cookies have such a piss poor
 * API in HTTP and offer so little insight from JS. This is just a
 * supplement to the battle testing the cookie library has.
 */
describe('Javelin Cookie', function() {

  it('should create a cookie string with the correct format', function() {
    var doc = { cookie : null };
    var c = new JX.Cookie('omnom');
    c.setValue('nommy');
    c.setDaysToLive(5);
    c.setTarget(doc);
    c.setPath('/');
    c.setSecure(true);
    c.write();

    // Should be something like:
    // omnom=nommy; path=/; expires=Sat, 10 Dec 2011 05:00:34 GMT; Secure;

    expect(doc.cookie).toMatch(
      /^omnom=nommy;\sPath=\/;\sExpires=[^;]+;\sSecure;/);
  });

  it('should properly encode and decode special chars in cookie values',
    function() {
      var value = '!@#$%^&*()?+|/=\\{}[]<>';
      var doc = { cookie : null };
      var c = new JX.Cookie('data');
      c.setTarget(doc);
      c.setValue(value);
      c.write();

      var data = doc.cookie.substr(0, doc.cookie.indexOf(';'));

      // Make sure the raw value is all escaped
      expect(data).toEqual(
      'data=!%40%23%24%25%5E%26*()%3F%2B%7C%2F%3D%5C%7B%7D%5B%5D%3C%3E');

      // Make sure the retrieved value is all unescaped
      expect(c.read()).toEqual(value);
    });

});
