/**
 * @requires javelin-magical-init
 *           javelin-install
 *           javelin-util
 *           javelin-vector
 *           javelin-stratcom
 * @provides javelin-dom
 *
 * @javelin-installs JX.$
 * @javelin-installs JX.$N
 * @javelin-installs JX.$H
 *
 * @javelin
 */


/**
 * Select an element by its "id" attribute, like ##document.getElementById()##.
 * For example:
 *
 *   var node = JX.$('some_id');
 *
 * This will select the node with the specified "id" attribute:
 *
 *   LANG=HTML
 *   <div id="some_id">...</div>
 *
 * If the specified node does not exist, @{JX.$()} will throw an exception.
 *
 * For other ways to select nodes from the document, see @{JX.DOM.scry()} and
 * @{JX.DOM.find()}.
 *
 * @param  string  "id" attribute to select from the document.
 * @return Node    Node with the specified "id" attribute.
 */
JX.$ = function(id) {

  if (__DEV__) {
    if (!id) {
      JX.$E('Empty ID passed to JX.$()!');
    }
  }

  var node = document.getElementById(id);
  if (!node || (node.id != id)) {
    if (__DEV__) {
      if (node && (node.id != id)) {
        JX.$E(
          'JX.$(\''+id+'\'): '+
          'document.getElementById() returned an element without the '+
          'correct ID. This usually means that the element you are trying '+
          'to select is being masked by a form with the same value in its '+
          '"name" attribute.');
      }
    }
    JX.$E('JX.$(\'' + id + '\') call matched no nodes.');
  }

  return node;
};

/**
 * Upcast a string into an HTML object so it is treated as markup instead of
 * plain text. See @{JX.$N} for discussion of Javelin's security model. Every
 * time you call this function you potentially open up a security hole. Avoid
 * its use wherever possible.
 *
 * This class intentionally supports only a subset of HTML because many browsers
 * named "Internet Explorer" have awkward restrictions around what they'll
 * accept for conversion to document fragments. Alter your datasource to emit
 * valid HTML within this subset if you run into an unsupported edge case. All
 * the edge cases are crazy and you should always be reasonably able to emit
 * a cohesive tag instead of an unappendable fragment.
 *
 * You may use @{JX.$H} as a shortcut for creating new JX.HTML instances:
 *
 *   JX.$N('div', {}, some_html_blob); // Treat as string (safe)
 *   JX.$N('div', {}, JX.$H(some_html_blob)); // Treat as HTML (unsafe!)
 *
 * @task build String into HTML
 * @task nodes HTML into Nodes
 */
JX.install('HTML', {

  construct : function(str) {
    if (str instanceof JX.HTML) {
      this._content = str._content;
      return;
    }

    if (__DEV__) {
      if ((typeof str !== 'string') && (!str || !str.match)) {
        JX.$E(
          'new JX.HTML(<empty?>): ' +
          'call initializes an HTML object with an empty value.');
      }

      var tags = ['legend', 'thead', 'tbody', 'tfoot', 'column', 'colgroup',
                  'caption', 'tr', 'th', 'td', 'option'];
      var evil_stuff = new RegExp('^\\s*<(' + tags.join('|') + ')\\b', 'i');
      var match = str.match(evil_stuff);
      if (match) {
        JX.$E(
          'new JX.HTML("<' + match[1] + '>..."): ' +
          'call initializes an HTML object with an invalid partial fragment ' +
          'and can not be converted into DOM nodes. The enclosing tag of an ' +
          'HTML content string must be appendable to a document fragment. ' +
          'For example, <table> is allowed but <tr> or <tfoot> are not.');
      }

      var really_evil = /<script\b/;
      if (str.match(really_evil)) {
        JX.$E(
          'new JX.HTML("...<script>..."): ' +
          'call initializes an HTML object with an embedded script tag! ' +
          'Are you crazy?! Do NOT do this!!!');
      }

      var wont_work = /<object\b/;
      if (str.match(wont_work)) {
        JX.$E(
          'new JX.HTML("...<object>..."): ' +
          'call initializes an HTML object with an embedded <object> tag. IE ' +
          'will not do the right thing with this.');
      }

      // TODO(epriestley): May need to deny <option> more broadly, see
      // http://support.microsoft.com/kb/829907 and the whole mess in the
      // heavy stack. But I seem to have gotten away without cloning into the
      // documentFragment below, so this may be a nonissue.
    }

    this._content = str;
  },

  members : {
    _content : null,
    /**
     * Convert the raw HTML string into a DOM node tree.
     *
     * @task  nodes
     * @return DocumentFragment A document fragment which contains the nodes
     *                          corresponding to the HTML string you provided.
     */
    getFragment : function() {
      var wrapper = JX.$N('div');
      wrapper.innerHTML = this._content;
      var fragment = document.createDocumentFragment();
      while (wrapper.firstChild) {
        // TODO(epriestley): Do we need to do a bunch of cloning junk here?
        // See heavy stack. I'm disconnecting the nodes instead; this seems
        // to work but maybe my test case just isn't extensive enough.
        fragment.appendChild(wrapper.removeChild(wrapper.firstChild));
      }
      return fragment;
    },

    /**
     * Convert the raw HTML string into a single DOM node. This only works
     * if the element has a single top-level element. Otherwise, use
     * @{method:getFragment} to get a document fragment instead.
     *
     * @return Node Single node represented by the object.
     * @task nodes
     */
    getNode : function() {
      var fragment = this.getFragment();
      if (__DEV__) {
        if (fragment.childNodes.length < 1) {
          JX.$E('JX.HTML.getNode(): Markup has no root node!');
        }
        if (fragment.childNodes.length > 1) {
          JX.$E('JX.HTML.getNode(): Markup has more than one root node!');
        }
      }
      return fragment.firstChild;
    }

  }
});


/**
 * Build a new HTML object from a trustworthy string. JX.$H is a shortcut for
 * creating new JX.HTML instances.
 *
 * @task build
 * @param string A string which you want to be treated as HTML, because you
 *               know it is from a trusted source and any data in it has been
 *               properly escaped.
 * @return JX.HTML HTML object, suitable for use with @{JX.$N}.
 */
JX.$H = function(str) {
  return new JX.HTML(str);
};


/**
 * Create a new DOM node with attributes and content.
 *
 *   var link = JX.$N('a');
 *
 * This creates a new, empty anchor tag without any attributes. The equivalent
 * markup would be:
 *
 *   LANG=HTML
 *   <a />
 *
 * You can also specify attributes by passing a dictionary:
 *
 *   JX.$N('a', {name: 'anchor'});
 *
 * This is equivalent to:
 *
 *   LANG=HTML
 *   <a name="anchor" />
 *
 * Additionally, you can specify content:
 *
 *   JX.$N(
 *     'a',
 *     {href: 'http://www.javelinjs.com'},
 *     'Visit the Javelin Homepage');
 *
 * This is equivalent to:
 *
 *   LANG=HTML
 *   <a href="http://www.javelinjs.com">Visit the Javelin Homepage</a>
 *
 * If you only want to specify content, you can omit the attribute parameter.
 * That is, these calls are equivalent:
 *
 *   JX.$N('div', {}, 'Lorem ipsum...'); // No attributes.
 *   JX.$N('div', 'Lorem ipsum...')      // Same as above.
 *
 * Both are equivalent to:
 *
 *   LANG=HTML
 *   <div>Lorem ipsum...</div>
 *
 * Note that the content is treated as plain text, not HTML. This means it is
 * safe to use untrusted strings:
 *
 *   JX.$N('div', '<script src="evil.com" />');
 *
 * This is equivalent to:
 *
 *   LANG=HTML
 *   <div>&lt;script src="evil.com" /&gt;</div>
 *
 * That is, the content will be properly escaped and will not create a
 * vulnerability. If you want to set HTML content, you can use @{JX.HTML}:
 *
 *   JX.$N('div', JX.$H(some_html));
 *
 * **This is potentially unsafe**, so make sure you understand what you're
 * doing. You should usually avoid passing HTML around in string form. See
 * @{JX.HTML} for discussion.
 *
 * You can create new nodes with a Javelin sigil (and, optionally, metadata) by
 * providing "sigil" and "meta" keys in the attribute dictionary.
 *
 * @param string                  Tag name, like 'a' or 'div'.
 * @param dict|string|@{JX.HTML}? Property dictionary, or content if you don't
 *                                want to specify any properties.
 * @param string|@{JX.HTML}?      Content string (interpreted as plain text)
 *                                or @{JX.HTML} object (interpreted as HTML,
 *                                which may be dangerous).
 * @return Node                   New node with whatever attributes and
 *                                content were specified.
 */
JX.$N = function(tag, attr, content) {
  if (typeof content == 'undefined' &&
      (typeof attr != 'object' || attr instanceof JX.HTML)) {
    content = attr;
    attr = {};
  }

  if (__DEV__) {
    if (tag.toLowerCase() != tag) {
      JX.$E(
        '$N("'+tag+'", ...): '+
        'tag name must be in lower case; '+
        'use "'+tag.toLowerCase()+'", not "'+tag+'".');
    }
  }

  var node = document.createElement(tag);

  if (attr.style) {
    JX.copy(node.style, attr.style);
    delete attr.style;
  }

  if (attr.sigil) {
    JX.Stratcom.addSigil(node, attr.sigil);
    delete attr.sigil;
  }

  if (attr.meta) {
    JX.Stratcom.addData(node, attr.meta);
    delete attr.meta;
  }

  if (__DEV__) {
    if (('metadata' in attr) || ('data' in attr)) {
      JX.$E(
        '$N(' + tag + ', ...): ' +
        'use the key "meta" to specify metadata, not "data" or "metadata".');
    }
  }

  for (var k in attr) {
    if (attr[k] === null) {
      continue;
    }
    node[k] = attr[k];
  }

  if (content) {
    JX.DOM.setContent(node, content);
  }
  return node;
};


/**
 * Query and update the DOM. Everything here is static, this is essentially
 * a collection of common utility functions.
 *
 * @task stratcom Attaching Event Listeners
 * @task content Changing DOM Content
 * @task nodes Updating Nodes
 * @task serialize Serializing Forms
 * @task test Testing DOM Properties
 * @task convenience Convenience Methods
 * @task query Finding Nodes in the DOM
 * @task view Changing View State
 */
JX.install('DOM', {
  statics : {
    _autoid : 0,
    _uniqid : 0,
    _metrics : {},
    _frameNode: null,
    _contentNode: null,


/* -(  Changing DOM Content  )----------------------------------------------- */


    /**
     * Set the content of some node. This uses the same content semantics as
     * other Javelin content methods, see @{function:JX.$N} for a detailed
     * explanation. Previous content will be replaced: you can also
     * @{method:prependContent} or @{method:appendContent}.
     *
     * @param Node  Node to set content of.
     * @param mixed Content to set.
     * @return void
     * @task content
     */
    setContent : function(node, content) {
      if (__DEV__) {
        if (!JX.DOM.isNode(node)) {
          JX.$E(
            'JX.DOM.setContent(<yuck>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      while (node.firstChild) {
        JX.DOM.remove(node.firstChild);
      }
      JX.DOM.appendContent(node, content);
    },


    /**
     * Prepend content to some node. This method uses the same content semantics
     * as other Javelin methods, see @{function:JX.$N} for an explanation. You
     * can also @{method:setContent} or @{method:appendContent}.
     *
     * @param Node  Node to prepend content to.
     * @param mixed Content to prepend.
     * @return void
     * @task content
     */
    prependContent : function(node, content) {
      if (__DEV__) {
        if (!JX.DOM.isNode(node)) {
          JX.$E(
            'JX.DOM.prependContent(<junk>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      this._insertContent(node, content, this._mechanismPrepend, true);
    },


    /**
     * Append content to some node. This method uses the same content semantics
     * as other Javelin methods, see @{function:JX.$N} for an explanation. You
     * can also @{method:setContent} or @{method:prependContent}.
     *
     * @param Node Node to append the content of.
     * @param mixed Content to append.
     * @return void
     * @task content
     */
    appendContent : function(node, content) {
      if (__DEV__) {
        if (!JX.DOM.isNode(node)) {
          JX.$E(
            'JX.DOM.appendContent(<bleh>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      this._insertContent(node, content, this._mechanismAppend);
    },


    /**
     * Internal, add content to a node by prepending.
     *
     * @param Node  Node to prepend content to.
     * @param Node  Node to prepend.
     * @return void
     * @task content
     */
    _mechanismPrepend : function(node, content) {
      node.insertBefore(content, node.firstChild);
    },


    /**
     * Internal, add content to a node by appending.
     *
     * @param Node  Node to append content to.
     * @param Node  Node to append.
     * @task content
     */
    _mechanismAppend : function(node, content) {
      node.appendChild(content);
    },


    /**
     * Internal, add content to a node using some specified mechanism.
     *
     * @param Node      Node to add content to.
     * @param mixed     Content to add.
     * @param function  Callback for actually adding the nodes.
     * @param bool      True if array elements should be passed to the mechanism
     *                  in reverse order, i.e. the mechanism prepends nodes.
     * @return void
     * @task content
     */
    _insertContent : function(parent, content, mechanism, reverse) {
      if (JX.isArray(content)) {
        if (reverse) {
          content = [].concat(content).reverse();
        }
        for (var ii = 0; ii < content.length; ii++) {
          JX.DOM._insertContent(parent, content[ii], mechanism, reverse);
        }
      } else {
        var type = typeof content;
        if (content instanceof JX.HTML) {
          content = content.getFragment();
        } else if (type == 'string' || type == 'number') {
          content = document.createTextNode(content);
        }

        if (__DEV__) {
          if (content && !content.nodeType) {
            JX.$E(
              'JX.DOM._insertContent(<node>, ...): '+
              'second argument must be a string, a number, ' +
              'a DOM node or a JX.HTML instance');
          }
        }

        content && mechanism(parent, content);
      }
    },


/* -(  Updating Nodes  )----------------------------------------------------- */


    /**
     * Remove a node from its parent, so it is no longer a child of any other
     * node.
     *
     * @param Node Node to remove.
     * @return Node The node.
     * @task nodes
     */
    remove : function(node) {
      node.parentNode && JX.DOM.replace(node, null);
      return node;
    },


    /**
     * Replace a node with some other piece of content. This method obeys
     * Javelin content semantics, see @{function:JX.$N} for an explanation.
     * You can also @{method:setContent}, @{method:prependContent}, or
     * @{method:appendContent}.
     *
     * @param Node Node to replace.
     * @param mixed Content to replace it with.
     * @return Node the original node.
     * @task nodes
     */
    replace : function(node, replacement) {
      if (__DEV__) {
        if (!node.parentNode) {
          JX.$E(
            'JX.DOM.replace(<node>, ...): '+
            'node has no parent node, so it can not be replaced.');
        }
      }

      var mechanism;
      if (node.nextSibling) {
        mechanism = JX.bind(node.nextSibling, function(parent, content) {
          parent.insertBefore(content, this);
        });
      } else {
        mechanism = this._mechanismAppend;
      }
      var parent = node.parentNode;
      parent.removeChild(node);
      this._insertContent(parent, replacement, mechanism);

      return node;
    },


/* -(  Serializing Forms  )-------------------------------------------------- */


    /**
     * Converts a form into a list of <name, value> pairs.
     *
     * Note: This function explicity does not match for submit inputs as there
     * could be multiple in a form. It's the caller's obligation to add the
     * submit input value if desired.
     *
     * @param   Node  The form element to convert into a list of pairs.
     * @return  List  A list of <name, value> pairs.
     * @task serialize
     */
    convertFormToListOfPairs : function(form) {
      var elements = form.getElementsByTagName('*');
      var data = [];
      for (var ii = 0; ii < elements.length; ++ii) {
        if (!elements[ii].name) {
          continue;
        }
        if (elements[ii].disabled) {
          continue;
        }
        var type = elements[ii].type;
        var tag  = elements[ii].tagName;
        if ((type in {radio: 1, checkbox: 1} && elements[ii].checked) ||
             type in {text: 1, hidden: 1, password: 1, email: 1, tel: 1,
                      number: 1} ||
             tag in {TEXTAREA: 1, SELECT: 1}) {
          data.push([elements[ii].name, elements[ii].value]);
        }
      }
      return data;
    },


    /**
     * Converts a form into a dictionary mapping input names to values. This
     * will overwrite duplicate inputs in an undefined way.
     *
     * @param   Node  The form element to convert into a dictionary.
     * @return  Dict  A dictionary of form values.
     * @task serialize
     */
    convertFormToDictionary : function(form) {
      var data = {};
      var pairs = JX.DOM.convertFormToListOfPairs(form);
      for (var ii = 0; ii < pairs.length; ii++) {
        data[pairs[ii][0]] = pairs[ii][1];
      }
      return data;
    },


/* -(  Testing DOM Properties  )--------------------------------------------- */


    /**
     * Test if an object is a valid Node.
     *
     * @param wild Something which might be a Node.
     * @return bool True if the parameter is a DOM node.
     * @task test
     */
    isNode : function(node) {
      return !!(node && node.nodeName && (node !== window));
    },


    /**
     * Test if an object is a node of some specific (or one of several) types.
     * For example, this tests if the node is an ##<input />##, ##<select />##,
     * or ##<textarea />##.
     *
     *   JX.DOM.isType(node, ['input', 'select', 'textarea']);
     *
     * @param   wild        Something which might be a Node.
     * @param   string|list One or more tags which you want to test for.
     * @return  bool        True if the object is a node, and it's a node of one
     *                      of the provided types.
     * @task    test
     */
    isType : function(node, of_type) {
      node = ('' + (node.nodeName || '')).toUpperCase();
      of_type = JX.$AX(of_type);
      for (var ii = 0; ii < of_type.length; ++ii) {
        if (of_type[ii].toUpperCase() == node) {
          return true;
        }
      }
      return false;
    },


    /**
     * Listen for events occuring beneath a specific node in the DOM. This is
     * similar to @{JX.Stratcom.listen()}, but allows you to specify some node
     * which serves as a scope instead of the default scope (the whole document)
     * which you get if you install using @{JX.Stratcom.listen()} directly. For
     * example, to listen for clicks on nodes with the sigil 'menu-item' below
     * the root menu node:
     *
     *   var the_menu = getReferenceToTheMenuNodeSomehow();
     *   JX.DOM.listen(the_menu, 'click', 'menu-item', function(e) { ... });
     *
     * @task stratcom
     * @param Node        The node to listen for events underneath.
     * @param string|list One or more event types to listen for.
     * @param list?       A path to listen on, or a list of paths.
     * @param function    Callback to invoke when a matching event occurs.
     * @return object     A reference to the installed listener. You can later
     *                    remove the listener by calling this object's remove()
     *                    method.
     */
    listen : function(node, type, path, callback) {
      var auto_id = ['autoid:' + JX.DOM._getAutoID(node)];
      path = JX.$AX(path || []);
      if (!path.length) {
        path = auto_id;
      } else {
        for (var ii = 0; ii < path.length; ii++) {
          path[ii] = auto_id.concat(JX.$AX(path[ii]));
        }
      }
      return JX.Stratcom.listen(type, path, callback);
    },


    /**
     * Invoke a custom event on a node. This method is a companion to
     * @{method:JX.DOM.listen} and parallels @{method:JX.Stratcom.invoke} in
     * the same way that method parallels @{method:JX.Stratcom.listen}.
     *
     * This method can not be used to invoke native events (like 'click').
     *
     * @param Node      The node to invoke an event on.
     * @param string    Custom event type.
     * @param dict      Event data.
     * @return JX.Event The event object which was dispatched to listeners.
     *                  The main use of this is to test whether any
     *                  listeners prevented the event.
     */
    invoke : function(node, type, data) {
      if (__DEV__) {
        if (type in JX.__allowedEvents) {
          throw new Error(
            'JX.DOM.invoke(..., "' + type + '", ...): ' +
            'you cannot invoke with the same type as a native event.');
        }
      }
      return JX.Stratcom.dispatch({
        target: node,
        type: type,
        customData: data
      });
    },


    uniqID : function(node) {
      if (!node.getAttribute('id')) {
        node.setAttribute('id', 'uniqid_'+(++JX.DOM._uniqid));
      }
      return node.getAttribute('id');
    },

    alterClass : function(node, className, add) {
      if (__DEV__) {
        if (add !== false && add !== true) {
          JX.$E(
            'JX.DOM.alterClass(...): ' +
            'expects the third parameter to be Boolean: ' +
            add + ' was provided');
        }
      }

      var has = ((' '+node.className+' ').indexOf(' '+className+' ') > -1);
      if (add && !has) {
        node.className += ' '+className;
      } else if (has && !add) {
        node.className = node.className.replace(
          new RegExp('(^|\\s)' + className + '(?:\\s|$)', 'g'), ' ').trim();
      }
    },

    htmlize : function(str) {
      return (''+str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    },


    /**
     * Show one or more elements, by removing their "display" style. This
     * assumes you have hidden them with @{method:hide}, or explicitly set
     * the style to `display: none;`.
     *
     * @task convenience
     * @param ... One or more nodes to remove "display" styles from.
     * @return void
     */
    show : function() {
      var ii;

      if (__DEV__) {
        for (ii = 0; ii < arguments.length; ++ii) {
          if (!arguments[ii]) {
            JX.$E(
              'JX.DOM.show(...): ' +
              'one or more arguments were null or empty.');
          }
        }
      }

      for (ii = 0; ii < arguments.length; ++ii) {
        arguments[ii].style.display = '';
      }
    },


    /**
     * Hide one or more elements, by setting `display: none;` on them. This is
     * a convenience method. See also @{method:show}.
     *
     * @task convenience
     * @param ... One or more nodes to set "display: none" on.
     * @return void
     */
    hide : function() {
      var ii;

      if (__DEV__) {
        for (ii = 0; ii < arguments.length; ++ii) {
          if (!arguments[ii]) {
            JX.$E(
              'JX.DOM.hide(...): ' +
              'one or more arguments were null or empty.');
          }
        }
      }

      for (ii = 0; ii < arguments.length; ++ii) {
        arguments[ii].style.display = 'none';
      }
    },

    textMetrics : function(node, pseudoclass, x) {
      if (!this._metrics[pseudoclass]) {
        var n = JX.$N(
          'var',
          {className: pseudoclass});
        this._metrics[pseudoclass] = n;
      }
      var proxy = this._metrics[pseudoclass];
      document.body.appendChild(proxy);
      proxy.style.width = x ? (x+'px') : '';
      JX.DOM.setContent(
        proxy,
        JX.$H(JX.DOM.htmlize(node.value).replace(/\n/g, '<br />')));
      var metrics = JX.Vector.getDim(proxy);
      document.body.removeChild(proxy);
      return metrics;
    },


    /**
     * Search the document for DOM nodes by providing a root node to look
     * beneath, a tag name, and (optionally) a sigil. Nodes which match all
     * specified conditions are returned.
     *
     * @task query
     *
     * @param  Node    Root node to search beneath.
     * @param  string  Tag name, like 'a' or 'textarea'.
     * @param  string  Optionally, a sigil which nodes are required to have.
     *
     * @return list    List of matching nodes, which may be empty.
     */
    scry : function(root, tagname, sigil) {
      if (__DEV__) {
        if (!JX.DOM.isNode(root)) {
          JX.$E(
            'JX.DOM.scry(<yuck>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      var nodes = root.getElementsByTagName(tagname);
      if (!sigil) {
        return JX.$A(nodes);
      }
      var result = [];
      for (var ii = 0; ii < nodes.length; ii++) {
        if (JX.Stratcom.hasSigil(nodes[ii], sigil)) {
          result.push(nodes[ii]);
        }
      }
      return result;
    },


    /**
     * Select a node uniquely identified by a root, tagname and sigil. This
     * is similar to JX.DOM.scry() but expects exactly one result.
     *
     * @task query
     *
     * @param  Node    Root node to search beneath.
     * @param  string  Tag name, like 'a' or 'textarea'.
     * @param  string  Optionally, sigil which selected node must have.
     *
     * @return Node    Node uniquely identified by the criteria.
     */
    find : function(root, tagname, sigil) {
      if (__DEV__) {
        if (!JX.DOM.isNode(root)) {
          JX.$E(
            'JX.DOM.find(<glop>, "'+tagname+'", "'+sigil+'"): '+
            'first argument must be a DOM node.');
        }
      }

      var result = JX.DOM.scry(root, tagname, sigil);

      if (__DEV__) {
        if (result.length > 1) {
          JX.$E(
            'JX.DOM.find(<node>, "'+tagname+'", "'+sigil+'"): '+
            'matched more than one node.');
        }
      }

      if (!result.length) {
        JX.$E(
          'JX.DOM.find(<node>, "' + tagname + '", "' + sigil + '"): ' +
          'matched no nodes.');
      }

      return result[0];
    },


    /**
     * Select a node uniquely identified by an anchor, tagname, and sigil. This
     * is similar to JX.DOM.find() but walks up the DOM tree instead of down
     * it.
     *
     * @param   Node    Node to look above.
     * @param   string  Optional tag name, like 'a' or 'textarea'.
     * @param   string  Optionally, sigil which selected node must have.
     * @return  Node    Matching node.
     *
     * @task    query
     */
    findAbove : function(anchor, tagname, sigil) {
      if (__DEV__) {
        if (!JX.DOM.isNode(anchor)) {
          JX.$E(
            'JX.DOM.findAbove(<glop>, "' + tagname + '", "' + sigil + '"): ' +
            'first argument must be a DOM node.');
        }
      }

      var result = anchor.parentNode;
      while (true) {
        if (!result) {
          break;
        }
        if (!tagname || JX.DOM.isType(result, tagname)) {
          if (!sigil || JX.Stratcom.hasSigil(result, sigil)) {
            break;
          }
        }
        result = result.parentNode;
      }

      if (!result) {
        JX.$E(
          'JX.DOM.findAbove(<node>, "' + tagname + '", "' + sigil + '"): ' +
          'no matching node.');
      }

      return result;
    },


    /**
     * Focus a node safely. This is just a convenience wrapper that allows you
     * to avoid IE's habit of throwing when nearly any focus operation is
     * invoked.
     *
     * @task convenience
     * @param Node Node to move cursor focus to, if possible.
     * @return void
     */
    focus : function(node) {
      try { node.focus(); } catch (lol_ie) {}
    },


    /**
     * Set specific nodes as content and frame nodes for the document.
     *
     * This will cause @{method:scrollTo} and @{method:scrollToPosition} to
     * affect the given frame node instead of the window. This is useful if the
     * page content is broken into multiple panels which scroll independently.
     *
     * Normally, both nodes are the document body.
     *
     * @task view
     * @param Node Node to set as the scroll frame.
     * @param Node Node to set as the content frame.
     * @return void
     */
    setContentFrame: function(frame_node, content_node) {
      JX.DOM._frameNode = frame_node;
      JX.DOM._contentNode = content_node;
    },


    /**
     * Get the current content frame, or `document.body` if one has not been
     * set.
     *
     * @task view
     * @return Node The node which frames the main page content.
     * @return void
     */
    getContentFrame: function() {
      return JX.DOM._contentNode || document.body;
    },

    /**
     * Scroll to the position of an element in the document.
     *
     * If @{method:setContentFrame} has been used to set a frame, that node is
     * scrolled.
     *
     * @task view
     * @param Node Node to move document scroll position to, if possible.
     * @return void
     */
    scrollTo : function(node) {
      var pos = JX.Vector.getPosWithScroll(node);
      JX.DOM.scrollToPosition(0, pos.y);
    },

    /**
     * Scroll to a specific position in the document.
     *
     * If @{method:setContentFrame} has been used to set a frame, that node is
     * scrolled.
     *
     * @task view
     * @param int X position, in pixels.
     * @param int Y position, in pixels.
     * @return void
     */
    scrollToPosition: function(x, y) {
      var self = JX.DOM;
      if (self._frameNode) {
        self._frameNode.scrollLeft = x;
        self._frameNode.scrollTop = y;
      } else {
        window.scrollTo(x, y);
      }
    },

    _getAutoID : function(node) {
      if (!node.getAttribute('data-autoid')) {
        node.setAttribute('data-autoid', 'autoid_'+(++JX.DOM._autoid));
      }
      return node.getAttribute('data-autoid');
    }
  }
});
