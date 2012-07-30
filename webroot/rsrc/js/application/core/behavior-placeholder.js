/**
 * @provides javelin-behavior-placeholder
 * @requires javelin-behavior
 *           javelin-dom
 */

/**
 * Add placeholder text to an input. Config are:
 *
 *   - `id` (Required) ID of the element to add placeholder text to.
 *   - `text` (Required) Text to show.
 *
 * While the element is displaying placeholder text, the class `jx-placeholder`
 * is added to it. Normally, you use a lower-contrast color to indicate that
 * this text is instructional:
 *
 *   .jx-placeholder {
 *     color: #888888;
 *   }
 *
 * @group ui
 */
JX.behavior('placeholder', function(config) {
  var input = JX.$(config.id);
  var placeholder_visible = false;

  function update(show_placeholder) {
    placeholder_visible = show_placeholder;
    JX.DOM.alterClass(input, 'jx-placeholder', placeholder_visible);
  }

  function onfocus() {
    if (placeholder_visible) {
      input.value = '';
      update(false);
    }
  }

  function onblur() {
    if (!input.value) {
      input.value = config.text;
      update(true);
    }
  }

  JX.DOM.listen(input, 'focus', null, onfocus);
  JX.DOM.listen(input, 'blur', null, onblur);

  // When the user submits the form, remove the placeholder text (so it doesn't
  // get submitted to the server) and then restore it after the submit finishes.
  JX.DOM.listen(input.form, 'submit', null, function() {
    onfocus();
    setTimeout(onblur, 0);
  });

  // If the element isn't currently focused, show the placeholder text.
  if (document.activeElement != input) {
    onblur();
  }
});
