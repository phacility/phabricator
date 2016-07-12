/**
 * @requires javelin-util
 */

describe('JX.isArray', function() {

  it('should correctly identify an array', function() {
    expect(JX.isArray([1, 2, 3])).toBe(true);

    expect(JX.isArray([])).toBe(true);
  });

  it('should return false on anything that is not an array', function() {
    expect(JX.isArray(1)).toBe(false);
    expect(JX.isArray('a string')).toBe(false);
    expect(JX.isArray(true)).toBe(false);
    expect(JX.isArray(/regex/)).toBe(false);

    expect(JX.isArray(new String('a super string'))).toBe(false);
    expect(JX.isArray(new Number(42))).toBe(false);
    expect(JX.isArray(new Boolean(false))).toBe(false);

    expect(JX.isArray({})).toBe(false);
    expect(JX.isArray({'0': 1, '1': 2, length: 2})).toBe(false);
    expect(JX.isArray((function(){
      return arguments;
    })('I', 'want', 'to', 'trick', 'you'))).toBe(false);
  });

  it('should identify an array from another context as an array', function() {
    var iframe = document.createElement('iframe');
    iframe.name = 'javelin-iframe-test';
    iframe.style.display = 'none';

    document.body.insertBefore(iframe, document.body.firstChild);
    var doc = iframe.contentWindow.document;
    doc.write(
      '<script>parent.MaybeArray = Array;</script>'
    );

    var array = MaybeArray(1, 2, 3);
    var array2 = new MaybeArray(1);
    array2[0] = 5;

    expect(JX.isArray(array)).toBe(true);
    expect(JX.isArray(array2)).toBe(true);
  });

});

describe('JX.bind', function() {

  it('should bind a function to a context', function() {
    var object = {a: 5, b: 3};
    JX.bind(object, function() {
      object.b = 1;
    })();
    expect(object).toEqual({a: 5, b: 1});
  });

  it('should bind a function without context', function() {
    var called;
    JX.bind(null, function() {
      called = true;
    })();
    expect(called).toBe(true);
  });

  it('should bind with arguments', function() {
    var list = [];
    JX.bind(null, function() {
      list.push.apply(list, JX.$A(arguments));
    }, 'a', 2, 'c', 4)();
    expect(list).toEqual(['a', 2, 'c', 4]);
  });

  it('should allow to pass additional arguments', function() {
    var list = [];
    JX.bind(null, function() {
      list.push.apply(list, JX.$A(arguments));
    }, 'a', 2)('c', 4);
    expect(list).toEqual(['a', 2, 'c', 4]);
  });

});
