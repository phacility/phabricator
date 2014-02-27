/**
 * @provides javelin-behavior-icon-composer
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('icon-composer', function(config) {

  var nodes = {
    root: JX.$(config.dialogID),
    colorInput: JX.$(config.colorInputID),
    iconInput: JX.$(config.iconInputID),
    preview: JX.$(config.previewID)
  };

  var selected = {
    color: config.defaultColor,
    icon: config.defaultIcon
  };

  var redraw = function() {
    var ii;

    var colors = JX.DOM.scry(nodes.root, 'button', 'compose-select-color');
    for (ii = 0; ii < colors.length; ii++) {
      JX.DOM.alterClass(
        colors[ii],
        'profile-image-button-selected',
        (JX.Stratcom.getData(colors[ii]).color == selected.color));
    }

    var icons = JX.DOM.scry(nodes.root, 'button', 'compose-select-icon');
    for (ii = 0; ii < icons.length; ii++) {
      JX.DOM.alterClass(
        icons[ii],
        'profile-image-button-selected',
        (JX.Stratcom.getData(icons[ii]).icon == selected.icon));
    }

    nodes.colorInput.value = selected.color;
    nodes.iconInput.value = selected.icon;

    var classes = ['phui-icon-view', 'sprite-projects'];
    classes.push('compose-background-' + selected.color);
    classes.push('projects-' + selected.icon);

    nodes.preview.className = classes.join(' ');
  };

  JX.DOM.listen(
    nodes.root,
    'click',
    'compose-select-color',
    function (e) {
      e.kill();

      selected.color = e.getNodeData('compose-select-color').color;
      redraw();
    });

  JX.DOM.listen(
    nodes.root,
    'click',
    'compose-select-icon',
    function (e) {
      e.kill();

      selected.icon = e.getNodeData('compose-select-icon').icon;
      redraw();
    });

  redraw();

});
