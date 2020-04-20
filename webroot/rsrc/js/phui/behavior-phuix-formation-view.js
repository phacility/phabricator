/**
 * @provides javelin-behavior-phuix-formation-view
 * @requires javelin-behavior
 *           phuix-formation-view
 *           phuix-formation-column-view
 *           phuix-formation-flank-view
 */

JX.behavior('phuix-formation-view', function(config) {

  var formation_node = JX.$(config.nodeID);
  var formation = new JX.PHUIXFormationView(formation_node);

  var count = config.columns.length;
  for (var ii = 0; ii < count; ii++) {
    var spec = config.columns[ii];
    var node = JX.$(spec.itemID);

    var column = new JX.PHUIXFormationColumnView(node)
      .setIsRightAligned(spec.isRightAligned)
      .setWidth(spec.width)
      .setIsVisible(spec.isVisible);

    if (spec.expanderID) {
      column.setExpanderNode(JX.$(spec.expanderID));
    }

    if (spec.resizer) {
      column
        .setResizerItem(JX.$(spec.resizer.itemID))
        .setResizerControl(JX.$(spec.resizer.controlID));
    }

    var colspec = spec.column;
    if (colspec) {
      if (colspec.type === 'flank') {
        var flank_node = JX.$(colspec.nodeID);

        var head = JX.$(colspec.headID);
        var body = JX.$(colspec.bodyID);
        var tail = JX.$(colspec.tailID);

        var flank = new JX.PHUIXFormationFlankView(flank_node, head, body, tail)
          .setIsFixed(colspec.isFixed);

        column.setFlank(flank);
      }
    }

    formation.addColumn(column);
  }

  formation.start();
});
