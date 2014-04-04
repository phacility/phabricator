/**
 * @requires javelin-install
 */

describe('Javelin Install', function() {

  it('should extend from an object', function() {
    JX.install('Animal', {
      properties: {
        name: 'bob'
      }
    });

    JX.install('Dog', {
      extend: 'Animal',

      members: {
        bark: function() {
          return 'bow wow';
        }
      }
    });

    var bob = new JX.Dog();
    expect(bob.getName()).toEqual('bob');
    expect(bob.bark()).toEqual('bow wow');
  });

  it('should create a class', function() {
    var Animal = JX.createClass({
      name: 'Animal',

      properties: {
        name: 'bob'
      }
    });

    var Dog = JX.createClass({
      name: 'Dog',

      extend: Animal,

      members: {
        bark: function() {
          return 'bow wow';
        }
      }
    });

    var bob = new Dog();
    expect(bob.getName()).toEqual('bob');
    expect(bob.bark()).toEqual('bow wow');
  });

  it('should call base constructor when construct is not provided', function() {
    var Base = JX.createClass({
      name: 'Base',

      construct: function() {
        this.baseCalled = true;
      }
    });

    var Sub = JX.createClass({
      name: 'Sub',
      extend: Base
    });

    var obj = new Sub();
    expect(obj.baseCalled).toBe(true);
  });

  it('should call intialize after install', function() {
    var initialized = false;
    JX.install('TestClass', {
      properties: {
        foo: 'bar'
      },
      initialize: function() {
        initialized = true;
      }
    });

    expect(initialized).toBe(true);
  });

  it('should call base ctor when construct is not provided in JX.install',
  function() {

    JX.install('Base', {
      construct: function() {
        this.baseCalled = true;
      }
    });

    JX.install('Sub', {
      extend: 'Base'
    });

    var obj = new JX.Sub();
    expect(obj.baseCalled).toBe(true);
  });

  it('[DEV] should throw when calling install with name', function() {
    ensure__DEV__(true, function() {
      expect(function() {
        JX.install('AngryAnimal', {
          name: 'Kitty'
        });
      }).toThrow();
    });
  });

  it('[DEV] should throw when calling createClass with initialize', function() {
    ensure__DEV__(true, function() {
     expect(function() {
        JX.createClass({
          initialize: function() {

          }
        });
      }).toThrow();
    });
  });

  it('initialize() should be able to access the installed class', function() {
    JX.install('SomeClassWithInitialize', {
      initialize : function() {
        expect(!!JX.SomeClassWithInitialize).toBe(true);
      }
    });
  });

  it('should work with toString and its friends', function() {
    JX.install('NiceAnimal', {
      members: {
        toString: function() {
          return 'I am very nice.';
        },

        hasOwnProperty: function() {
          return true;
        }
      }
    });

    expect(new JX.NiceAnimal().toString()).toEqual('I am very nice.');
    expect(new JX.NiceAnimal().hasOwnProperty('dont-haz')).toEqual(true);
  });

});
