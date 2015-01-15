/**
 * Dumb HTML views. Mostly to demonstrate how the visitor pattern over these
 * views works, as driven by validation. I'm not convinced it's actually a good
 * idea to do validation.
 *
 * @provides javelin-view-html
 * @requires javelin-install
 *           javelin-dom
 *           javelin-view-visitor
 *           javelin-util
 */

JX.install('HTMLView', {
  extend: 'View',
  members : {
    render: function(rendered_children) {
      return JX.$N(this.getName(), this.getAllAttributes(), rendered_children);
    },
    validate: function() {
      this.accept(JX.HTMLView.getValidatingVisitor());
    }
  },

  statics: {
    getValidatingVisitor: function() {
      return new JX.ViewVisitor(JX.HTMLView.validate);
    },

    validate: function(view) {
      var spec = this._getHTMLSpec();
      if (!(view.getName() in spec)) {
        throw new Error('invalid tag');
      }

      var tag_spec = spec[view.getName()];

      var attrs = view.getAllAttributes();
      for (var attr in attrs) {
        if (!(attr in tag_spec)) {
          throw new Error('invalid attr');
        }

        var validator = tag_spec[attr];
        if (typeof validator === 'function') {
          return validator(attrs[attr]);
        }
      }

      return true;
    },

    _validateRel: function(target) {
      return target in {
        '_blank': 1,
        '_self': 1,
        '_parent': 1,
        '_top': 1
      };
    },
    _getHTMLSpec: function() {
      var attrs_any_can_have = {
        className: 1,
        id: 1,
        sigil: 1
      };

      var form_elem_attrs = {
        name: 1,
        value: 1
      };

      var spec = {
        a: { href: 1, target: JX.HTMLView._validateRel },
        b: {},
        blockquote: {},
        br: {},
        button: JX.copy({}, form_elem_attrs),
        canvas: {},
        code: {},
        dd: {},
        div: {},
        dl: {},
        dt: {},
        em: {},
        embed: {},
        fieldset: {},
        form: { type: 1 },
        h1: {},
        h2: {},
        h3: {},
        h4: {},
        h5: {},
        h6: {},
        hr: {},
        i: {},
        iframe: { src: 1 },
        img: { src: 1, alt: 1 },
        input: JX.copy({}, form_elem_attrs),
        label: {'for': 1},
        li: {},
        ol: {},
        optgroup: {},
        option: JX.copy({}, form_elem_attrs),
        p: {},
        pre: {},
        q: {},
        select: {},
        span: {},
        strong: {},
        sub: {},
        sup: {},
        table: {},
        tbody: {},
        td: {},
        textarea: {},
        tfoot: {},
        th: {},
        thead: {},
        tr: {},
        ul: {}
      };

      for (var k in spec) {
        JX.copy(spec[k], attrs_any_can_have);
      }

      return spec;
    },
    registerToInterpreter: function(view_interpreter) {
      var spec = this._getHTMLSpec();
      for (var tag in spec) {
        view_interpreter.register(tag, JX.HTMLView);
      }
      return view_interpreter;
    }
  }
});
