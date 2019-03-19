/**
 * @provides javelin-workboard-drop-effect
 * @requires javelin-install
 *           javelin-dom
 * @javelin
 */

JX.install('WorkboardDropEffect', {

  properties: {
    icon: null,
    color: null,
    content: null
  },

  statics: {
    newFromDictionary: function(map) {
      return new JX.WorkboardDropEffect()
        .setIcon(map.icon)
        .setColor(map.color)
        .setContent(JX.$H(map.content));
    }
  },

  members: {
    newNode: function() {
      var icon = new JX.PHUIXIconView()
        .setIcon(this.getIcon())
        .setColor(this.getColor())
        .getNode();

      return JX.$N('li', {}, [icon, this.getContent()]);
    }
  }
});
