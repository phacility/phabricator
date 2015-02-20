/**
 * @requires javelin-uri javelin-php-serializer
 */
describe('Javelin URI', function() {

  it('should understand parts of a uri', function() {
    var uri = JX.$U('http://www.facebook.com:123/home.php?key=value#fragment');
    expect(uri.getProtocol()).toEqual('http');
    expect(uri.getDomain()).toEqual('www.facebook.com');
    expect(uri.getPort()).toEqual('123');
    expect(uri.getPath()).toEqual('/home.php');
    expect(uri.getQueryParams()).toEqual({'key' : 'value'});
    expect(uri.getFragment()).toEqual('fragment');
  });

  it('can accept null as uri string', function() {
    var uri = JX.$U(null);
    expect(uri.getProtocol()).toEqual(undefined);
    expect(uri.getDomain()).toEqual(undefined);
    expect(uri.getPath()).toEqual(undefined);
    expect(uri.getQueryParams()).toEqual({});
    expect(uri.getFragment()).toEqual(undefined);
    expect(uri.toString()).toEqual('');
  });

  it('can accept empty string as uri string', function() {
    var uri = JX.$U('');
    expect(uri.getProtocol()).toEqual(undefined);
    expect(uri.getDomain()).toEqual(undefined);
    expect(uri.getPath()).toEqual(undefined);
    expect(uri.getQueryParams()).toEqual({});
    expect(uri.getFragment()).toEqual(undefined);
    expect(uri.toString()).toEqual('');
  });

  it('should understand relative uri', function() {
    var uri = JX.$U('/home.php?key=value#fragment');
    expect(uri.getProtocol()).toEqual(undefined);
    expect(uri.getDomain()).toEqual(undefined);
    expect(uri.getPath()).toEqual('/home.php');
    expect(uri.getQueryParams()).toEqual({'key' : 'value'});
    expect(uri.getFragment()).toEqual('fragment');
  });

  function charRange(from, to) {
    var res = '';
    for (var i = from.charCodeAt(0); i <= to.charCodeAt(0); i++) {
      res += String.fromCharCode(i);
    }
    return res;
  }

  it('should reject unsafe domains', function() {
    var unsafe_chars =
      '\x00;\\%\u2047\u2048\ufe56\ufe5f\uff03\uff0f\uff1f' +
      charRange('\ufdd0', '\ufdef') + charRange('\ufff0', '\uffff');
    for (var i = 0; i < unsafe_chars.length; i++) {
      expect(function() {
        JX.$U('http://foo' + unsafe_chars.charAt(i) + 'bar');
      }).toThrow();
    }
  });

  it('should allow safe domains', function() {
    var safe_chars =
      '-._' + charRange('a', 'z') + charRange('A', 'Z') + charRange('0', '9') +
      '\u2046\u2049\ufdcf\ufdf0\uffef';
    for (var i = 0; i < safe_chars.length; i++) {
      var domain = 'foo' + safe_chars.charAt(i) + 'bar';
      var uri = JX.$U('http://' + domain);
      expect(uri.getDomain()).toEqual(domain);
    }
  });

  it('should set slash as the default path', function() {
    var uri = JX.$U('http://www.facebook.com');
    expect(uri.getPath()).toEqual('/');
  });

  it('should set empty map as the default query data', function() {
    var uri = JX.$U('http://www.facebook.com/');
    expect(uri.getQueryParams()).toEqual({});
  });

  it('should set undefined as the default fragment', function() {
    var uri = JX.$U('http://www.facebook.com/');
    expect(uri.getFragment()).toEqual(undefined);
  });

  it('should understand uri with no path', function() {
    var uri = JX.$U('http://www.facebook.com?key=value');
    expect(uri.getPath()).toEqual('/');
    expect(uri.getQueryParams()).toEqual({'key' : 'value'});
  });

  it('should understand multiple query keys', function() {
    var uri = JX.$U('/?clown=town&herp=derp');
    expect(uri.getQueryParams()).toEqual({
      'clown' : 'town',
      'herp' : 'derp'
    });
  });

  it('does not set keys for nonexistant data', function() {
    var uri = JX.$U('/?clown=town');
    expect(uri.getQueryParams().herp).toEqual(undefined);
  });

  it('does not parse different types of query data', function() {
    var uri = JX.$U('/?str=string&int=123&bool=true&badbool=false&raw');
    expect(uri.getQueryParams()).toEqual({
      'str' : 'string',
      'int' : '123',
      'bool' : 'true',
      'badbool' : 'false',
      'raw' : ''
    });
  });

  it('should act as string', function() {
    var string = 'http://www.facebook.com/home.php?key=value';
    var uri = JX.$U(string);
    expect(uri.toString()).toEqual(string);
    expect('' + uri).toEqual(string);
  });

  it('can remove path', function() {
    var uri = JX.$U('http://www.facebook.com/home.php?key=value');
    uri.setPath(undefined);
    expect(uri.getPath()).toEqual(undefined);
    expect(uri.toString()).toEqual('http://www.facebook.com/?key=value');
  });

  it('can remove queryData by undefining it', function() {
    var uri = JX.$U('http://www.facebook.com/home.php?key=value');
    uri.setQueryParams(undefined);
    expect(uri.getQueryParams()).toEqual(undefined);
    expect(uri.toString()).toEqual('http://www.facebook.com/home.php');
  });

  it('can remove queryData by replacing it', function() {
    var uri = JX.$U('http://www.facebook.com/home.php?key=value');
    uri.setQueryParams({});
    expect(uri.getQueryParams()).toEqual({});
    expect(uri.toString()).toEqual('http://www.facebook.com/home.php');
  });

  it('can amend to removed queryData', function() {
    var uri = JX.$U('http://www.facebook.com/home.php?key=value');
    uri.setQueryParams({});
    expect(uri.getQueryParams()).toEqual({});
    uri.addQueryParams({'herp' : 'derp'});
    expect(uri.getQueryParams()).toEqual({'herp' : 'derp'});
    expect(uri.toString()).toEqual(
      'http://www.facebook.com/home.php?herp=derp');
  });

  it('should properly decode entities', function() {
    var uri = JX.$U('/?from=clown+town&to=cloud%20city&pass=cloud%2Bcountry');
    expect(uri.getQueryParams()).toEqual({
      'from' : 'clown town',
      'to' : 'cloud city',
      'pass' : 'cloud+country'
    });
    expect(uri.toString()).toEqual(
        '/?from=clown%20town&to=cloud%20city&pass=cloud%2Bcountry');
  });

  it('can add query data', function() {
    var uri = JX.$U('http://www.facebook.com/');
    uri.addQueryParams({'key' : 'value'});
    expect(uri.getQueryParams()).toEqual({'key' : 'value'});
    expect(uri.toString()).toEqual('http://www.facebook.com/?key=value');
    uri.setQueryParam('key', 'lock');
    expect(uri.getQueryParams()).toEqual({'key' : 'lock'});
    expect(uri.toString()).toEqual('http://www.facebook.com/?key=lock');
  });

  it('can add different types of query data', function() {
    var uri = new JX.URI();
    uri.setQueryParams({
      'str' : 'string',
      'int' : 123,
      'bool' : true,
      'badbool' : false,
      'raw' : ''
    });
    expect(uri.toString()).toEqual(
      '?str=string&int=123&bool=true&badbool=false&raw');
  });

  it('should properly encode entities in added query data', function() {
    var uri = new JX.URI();
    uri.addQueryParams({'key' : 'two words'});
    expect(uri.getQueryParams()).toEqual({'key' : 'two words'});
    expect(uri.toString()).toEqual('?key=two%20words');
  });

  it('can add multiple query data', function() {
    var uri = JX.$U('http://www.facebook.com/');
    uri.addQueryParams({
      'clown' : 'town',
      'herp' : 'derp'
    });
    expect(uri.getQueryParams()).toEqual({
      'clown' : 'town',
      'herp' : 'derp'
    });
    expect(uri.toString()).toEqual(
      'http://www.facebook.com/?clown=town&herp=derp');
  });

  it('can append to existing query data', function() {
    var uri = JX.$U('/?key=value');
    uri.addQueryParams({'clown' : 'town'});
    expect(uri.getQueryParams()).toEqual({
      'key' : 'value',
      'clown' : 'town'
    });
    expect(uri.toString()).toEqual('/?key=value&clown=town');
  });

  it('can merge with existing query data', function() {
    var uri = JX.$U('/?key=value&clown=town');
    uri.addQueryParams({
      'clown' : 'ville',
      'herp' : 'derp'
    });
    expect(uri.getQueryParams()).toEqual({
      'key' : 'value',
      'clown' : 'ville',
      'herp' : 'derp'
    });
    expect(uri.toString()).toEqual('/?key=value&clown=ville&herp=derp');
  });

  it('can replace query data', function() {
    var uri = JX.$U('/?key=value&clown=town');
    uri.setQueryParams({'herp' : 'derp'});
    expect(uri.getQueryParams()).toEqual({'herp' : 'derp'});
    expect(uri.toString()).toEqual('/?herp=derp');
  });

  it('can remove query data', function() {
    var uri = JX.$U('/?key=value&clown=town');
    uri.addQueryParams({'key' : null});
    expect(uri.getQueryParams()).toEqual({
      'clown' : 'town',
      'key' : null
    });
    expect(uri.toString()).toEqual('/?clown=town');
  });

  it('can remove multiple query data', function() {
    var uri = JX.$U('/?key=value&clown=town&herp=derp');
    uri.addQueryParams({'key' : null, 'herp' : undefined});
    expect(uri.getQueryParams()).toEqual({
      'clown' : 'town',
      'key' : null,
      'herp' : undefined
    });
    expect(uri.toString()).toEqual('/?clown=town');
  });

  it('can remove non existent query data', function() {
    var uri = JX.$U('/?key=value');
    uri.addQueryParams({'magic' : null});
    expect(uri.getQueryParams()).toEqual({
      'key' : 'value',
      'magic' : null
    });
    expect(uri.toString()).toEqual('/?key=value');
  });

  it('can build uri from scratch', function() {
    var uri = new JX.URI();
    uri.setProtocol('http');
    uri.setDomain('www.facebook.com');
    uri.setPath('/home.php');
    uri.setQueryParams({'key' : 'value'});
    uri.setFragment('fragment');
    expect(uri.toString()).toEqual(
      'http://www.facebook.com/home.php?key=value#fragment');
  });

  it('no global state interference', function() {
    var uri = JX.$U();
    expect(uri.getQueryParams()).not.toEqual({'key' : 'value'});
  });

  it('should not loop indefinitely when parsing empty params', function() {
    expect(JX.$U('/?&key=value').getQueryParams()).toEqual({'key' : 'value'});
    expect(JX.$U('/?&&&key=value').getQueryParams()).toEqual({'key' : 'value'});
    expect(JX.$U('/?&&').getQueryParams()).toEqual({});
  });

  it('should parse values with =', function() {
    expect(JX.$U('/?x=1=1').getQueryParams()).toEqual({'x' : '1=1'});
  });

});
