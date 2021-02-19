/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-vector
 *           javelin-request
 *           javelin-uri
 * @provides phui-hovercard
 * @javelin
 */

JX.install('Hovercard', {

  properties: {
    hovercardKey: null,
    objectPHID: null,
    contextPHID: null,
    isLoading: false,
    isLoaded: false,
    content: null
  },

  members: {
    newContentNode: function() {
      return JX.$H(this.getContent());
    }
  }

});
