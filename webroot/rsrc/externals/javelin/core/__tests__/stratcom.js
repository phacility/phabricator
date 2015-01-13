/**
 * @requires javelin-stratcom
 *           javelin-dom
 */
describe('Stratcom Tests', function() {
  var node1 = document.createElement('div');
  JX.Stratcom.addSigil(node1, 'what');
  var node2 = document;
  var node3 = document.createElement('div');
  node3.className = 'what';

  it('should disallow document', function() {
    ensure__DEV__(true, function() {
      expect(function() {
        JX.Stratcom.listen('click', 'tag:#document', function() {});
      }).toThrow();
    });
  });

  it('should disallow window', function() {
    ensure__DEV__(true, function() {
      expect(function() {
        JX.Stratcom.listen('click', 'tag:window', function() {});
      }).toThrow();
    });
  });

  it('should test nodes for hasSigil', function() {
    expect(JX.Stratcom.hasSigil(node1, 'what')).toBe(true);
    expect(JX.Stratcom.hasSigil(node3, 'what')).toBe(false);

    ensure__DEV__(true, function() {
      expect(function() {
        JX.Stratcom.hasSigil(node2, 'what');
      }).toThrow();
    });
  });

  it('should be able to add sigils', function() {
    var node = document.createElement('div');
    JX.Stratcom.addSigil(node, 'my-sigil');
    expect(JX.Stratcom.hasSigil(node, 'my-sigil')).toBe(true);
    expect(JX.Stratcom.hasSigil(node, 'i-dont-haz')).toBe(false);
    JX.Stratcom.addSigil(node, 'javelin-rocks');
    expect(JX.Stratcom.hasSigil(node, 'my-sigil')).toBe(true);
    expect(JX.Stratcom.hasSigil(node, 'javelin-rocks')).toBe(true);

    // Should not arbitrarily take away other sigils
    JX.Stratcom.addSigil(node, 'javelin-rocks');
    expect(JX.Stratcom.hasSigil(node, 'my-sigil')).toBe(true);
    expect(JX.Stratcom.hasSigil(node, 'javelin-rocks')).toBe(true);
  });

  it('should test dataPersistence', function() {
    var n, d;

    n = JX.$N('div');
    d = JX.Stratcom.getData(n);
    expect(d).toEqual({});
    d.noise = 'quack';
    expect(JX.Stratcom.getData(n).noise).toEqual('quack');

    n = JX.$N('div');
    JX.Stratcom.addSigil(n, 'oink');
    d = JX.Stratcom.getData(n);
    expect(JX.Stratcom.getData(n)).toEqual({});
    d.noise = 'quack';
    expect(JX.Stratcom.getData(n).noise).toEqual('quack');

    ensure__DEV__(true, function(){
      var bad_values = [false, null, undefined, 'quack'];
      for (var ii = 0; ii < bad_values.length; ii++) {
        n = JX.$N('div');
        expect(function() {
          JX.Stratcom.addSigil(n, 'oink');
          JX.Stratcom.addData(n, bad_values[ii]);
        }).toThrow();
      }
    });

  });

  it('should allow the merge of additional data', function() {
    ensure__DEV__(true, function() {
      var clown = JX.$N('div');
      clown.setAttribute('data-meta', '0_0');
      JX.Stratcom.mergeData('0', {'0' : 'clown'});

      expect(JX.Stratcom.getData(clown)).toEqual('clown');

      var town = JX.$N('div');
      town.setAttribute('data-meta', '0_1');
      JX.Stratcom.mergeData('0', {'1' : 'town'});

      expect(JX.Stratcom.getData(clown)).toEqual('clown');
      expect(JX.Stratcom.getData(town)).toEqual('town');

      expect(function() {
        JX.Stratcom.mergeData('0', {'0' : 'oops'});
      }).toThrow();
    });
  });

  it('all listeners should be called', function() {
    ensure__DEV__(true, function() {
      var callback_count = 0;
      JX.Stratcom.listen('custom:eventA', null, function() {
        callback_count++;
      });

      JX.Stratcom.listen('custom:eventA', null, function() {
        callback_count++;
      });

      expect(callback_count).toEqual(0);
      JX.Stratcom.invoke('custom:eventA');
      expect(callback_count).toEqual(2);
    });
  });

  it('removed listeners should not be called', function() {
    ensure__DEV__(true, function() {
      var callback_count = 0;
      var listeners = [];
      var remove_listeners = function() {
        while (listeners.length) {
          listeners.pop().remove();
        }
      };

      listeners.push(
        JX.Stratcom.listen('custom:eventB', null, function() {
          callback_count++;
          remove_listeners();
        })
      );

      listeners.push(
        JX.Stratcom.listen('custom:eventB', null, function() {
          callback_count++;
          remove_listeners();
        })
      );

      expect(callback_count).toEqual(0);
      JX.Stratcom.invoke('custom:eventB');
      expect(listeners.length).toEqual(0);
      expect(callback_count).toEqual(1);
    });
  });

  it('should throw when accessing data in an unloaded block', function() {
    ensure__DEV__(true, function() {

      var n = JX.$N('div');
      n.setAttribute('data-meta', '9999999_9999999');

      var caught;
      try {
        JX.Stratcom.getData(n);
      } catch (error) {
        caught = error;
      }

      expect(caught instanceof Error).toEqual(true);
    });
  });

  // it('can set data serializer', function() {
  //   var uri = new JX.URI('http://www.facebook.com/home.php?key=value');
  //   uri.setQuerySerializer(JX.PHPQuerySerializer.serialize);
  //   uri.setQueryParam('obj', {
  //     num : 1,
  //     obj : {
  //       str : 'abc',
  //       i   : 123
  //     }
  //   });
  //   expect(decodeURIComponent(uri.toString())).toEqual(
  //     'http://www.facebook.com/home.php?key=value&' +
  //     'obj[num]=1&obj[obj][str]=abc&obj[obj][i]=123');
  // });

});
