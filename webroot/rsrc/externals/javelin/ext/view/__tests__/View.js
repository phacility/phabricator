/**
 * @requires javelin-view
 *           javelin-util
 */

describe('JX.View', function() {
  JX.install('TestView', {
    extend : 'View',
    construct : function(name, attrs, children) {
      JX.View.call(this, attrs, children);
      this.setName(name);
    },

    members : {
      getDefaultAttributeValues : function() {
        return {id: 'test'};
      },
      render : function(rendered_children) {
        return JX.$N(
          'span',
          {id : this.getAttr('id')},
          [this.getName()].concat(rendered_children)
        );
      }
    }
  });

  it('should by default render children that are passed in', function() {
    var t = new JX.TestView(
      '',
      {},
      [new JX.TestView('Hey', {id: 'child'}, [])]
    );
    var result = JX.ViewRenderer.render(t);
    expect(JX.DOM.scry(result, 'span').length).toBe(1);
  });

  it('should fail sanely with a bad getAttr call', function() {
    expect(new JX.TestView('', {}, []).getAttr('foo')).toBeUndefined();
  });

  it('should allow attribute setting with multiset', function() {
    var test_val = 'something else';
    expect(new JX.TestView('', {}, []).multisetAttr({
      id: 'some_id',
      other: test_val
    }).getAttr('other')).toBe(test_val);
  });

  it('should allow attribute setting with setAttr', function() {
    var test_val = 'something else';
    expect(new JX.TestView('', {}, [])
      .setAttr('other', test_val)
      .getAttr('other')).toBe(test_val);
  });

  it('should set default attributes per getDefaultAttributeValues', function() {
    // Also the test for getAttr
    expect(new JX.TestView('', {}, []).getAttr('id')).toBe('test');
  });
});
