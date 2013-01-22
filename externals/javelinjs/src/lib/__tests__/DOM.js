/**
 * @requires javelin-uri javelin-php-serializer
 */
describe('JX.DOM', function() {

  describe('uniqID', function() {
    it('must expect the unexpected', function() {
      // Form with an in <input /> named "id", which collides with the "id"
      // attribute.
      var form_id = JX.$N('form', {}, JX.$N('input', {name : 'id'}));
      var form_ok = JX.$N('form', {}, JX.$N('input', {name : 'ok'}));

      // Test that we avoid issues when "form.id" is actually the node named
      // "id".
      var id = JX.DOM.uniqID(form_id);
      expect(typeof id).toBe('string');
      expect(!!id).toBe(true);

      var ok = JX.DOM.uniqID(form_ok);
      expect(typeof ok).toBe('string');
      expect(!!ok).toBe(true);

      expect(id).toNotEqual(ok);
    });
  });

  describe('invoke', function() {
    it('should invoke custom events', function() {
      var span = JX.$N('span', 'test');
      var div = JX.$N('div', {}, span);
      var data = { duck: 'quack' };

      var invoked = false;
      var bubbled = false;
      JX.DOM.listen(span, 'custom', null, function(event) {
        expect(event.getTarget()).toBe(span);
        expect(event.getType()).toBe('custom');
        expect(event.getData()).toBe(data);
        invoked = true;
      });
      JX.DOM.listen(div, 'custom', null, function(event) {
        expect(event.getTarget()).toBe(span); // not div
        bubbled = true;
      });
      JX.DOM.invoke(span, 'custom', data);
      expect(invoked).toBe(true);
      expect(bubbled).toBe(true);
    });

    it('should not allow invoking native events', function() {
      ensure__DEV__(true, function() {
        expect(function() {
          JX.DOM.invoke(JX.$N('div'), 'click');
        }).toThrow();
      });
    });
  });


  describe('setContent', function() {
    var node;

    beforeEach(function() {
      node = JX.$N('div');
    });

    it('should insert a node', function() {
      var content = JX.$N('p');

      JX.DOM.setContent(node, content);
      expect(node.childNodes[0]).toEqual(content);
      expect(node.childNodes.length).toEqual(1);
    });

    it('should insert two nodes', function() {
      var content = [JX.$N('p'), JX.$N('div')];

      JX.DOM.setContent(node, content);
      expect(node.childNodes[0]).toEqual(content[0]);
      expect(node.childNodes[1]).toEqual(content[1]);
      expect(node.childNodes.length).toEqual(2);
    });

    it('should accept a text node', function() {
      var content = 'This is not the text you are looking for';

      JX.DOM.setContent(node, content);
      expect(node.innerText || node.textContent).toEqual(content);
      expect(node.childNodes.length).toEqual(1);
    });

    it('should accept nodes and strings in an array', function() {
      var content = [
        'This is not the text you are looking for',
        JX.$N('div')
      ];

      JX.DOM.setContent(node, content);
      expect(node.childNodes[0].nodeValue).toEqual(content[0]);
      expect(node.childNodes[1]).toEqual(content[1]);
      expect(node.childNodes.length).toEqual(2);
    });

    it('should accept a JX.HTML instance', function() {
      var content = JX.$H('<div />');

      JX.DOM.setContent(node, content);
      // Can not rely on an equals match because JX.HTML creates nodes on
      // the fly
      expect(node.childNodes[0].tagName).toEqual('DIV');
      expect(node.childNodes.length).toEqual(1);
    });

    it('should accept multiple JX.HTML instances', function() {
      var content = [JX.$H('<div />'), JX.$H('<a href="#"></a>')];

      JX.DOM.setContent(node, content);
      expect(node.childNodes[0].tagName).toEqual('DIV');
      expect(node.childNodes[1].tagName).toEqual('A');
      expect(node.childNodes.length).toEqual(2);
    });

    it('should accept nested arrays', function() {
      var content = [['a', 'b'], 'c'];

      JX.DOM.setContent(node, content);
      expect(node.childNodes.length).toEqual(3);
    });

    it('should retain order when prepending', function() {
      var content = [JX.$N('a'), JX.$N('b')];

      JX.DOM.setContent(node, JX.$N('div'));
      JX.DOM.prependContent(node, content);

      expect(node.childNodes[0].tagName).toEqual('A');
      expect(node.childNodes[1].tagName).toEqual('B');
      expect(node.childNodes[2].tagName).toEqual('DIV');
      expect(node.childNodes.length).toEqual(3);
    });

    it('should retain order when doing nested prepends', function() {
      // Note nesting.
      var content = [[JX.$N('a'), JX.$N('b')]];

      JX.DOM.prependContent(node, content);

      expect(node.childNodes[0].tagName).toEqual('A');
      expect(node.childNodes[1].tagName).toEqual('B');
      expect(node.childNodes.length).toEqual(2);
    });

    it('should ignore empty elements', function() {
      var content = [null, undefined, [], JX.$N('p'), 2, JX.$N('div'), false,
        [false, [0], [[]]], [[undefined], [,,,,,,,]]];

      JX.DOM.setContent(node, content);
      expect(node.childNodes[0].tagName).toEqual('P');
      expect(node.childNodes[2].tagName).toEqual('DIV');
      expect(node.childNodes.length).toEqual(4);
    });

    it('should fail when given an object with toString', function() {
      // This test is just documenting the behavior of an edge case, we could
      // later choose to support these objects.

      var content = {toString : function() { return 'quack'; }};

      var ex;
      try {
        // We expect JX.DOM.setContent() to throw an exception when processing
        // this object, since it will try to append it directly into the DOM
        // and the browser will reject it, as it isn't a node.
        JX.DOM.setContent(node, content);
      } catch (exception) {
        ex = exception;
      }

      expect(!!ex).toBe(true);
    });

    it('should not cause array order side effects', function() {
      var content = ['a', 'b'];
      var original = [].concat(content);

      JX.DOM.prependContent(node, content);

      expect(content).toEqual(original);
    });

    it('should allow numbers', function() {
      var content = 3;

      JX.DOM.setContent(node, content);
      expect(node.innerText || node.textContent).toEqual('3');
    });

    it('should work by re-setting a value', function() {
      JX.DOM.setContent(node, 'text');
      JX.DOM.setContent(node, 'another text');

      expect(node.innerText || node.textContent).toEqual('another text');
    });
  });

});
