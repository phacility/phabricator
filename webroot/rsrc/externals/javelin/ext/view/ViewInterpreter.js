/**
 * Experimental interpreter for nice views.
 * This is CoffeeScript:
 *
 * d = declare
 *   selectable: false
 *   boxOrientation: Orientation.HORIZONTAL
 *   additionalClasses: ['some-css-class']
 *   MultiAvatar ref: 'avatars'
 *   div
 *     flex: 1
 *     div(
 *       span className: 'some-css-class', ref: 'actorTargetLine'
 *       span className: 'message-css', ref: 'message'
 *     )
 *
 *     div
 *       boxOrientation: Orientation.HORIZONTAL
 *       className: 'attachment-css-class'
 *       div
 *         className: 'attachment-image-css-class'
 *         ref: 'attachmentImageContainer'
 *         boxOrientation: Orientation.HORIZONTAL
 *         div className: 'inline attachment-text', ref: 'attachmentText',
 *           div
 *             className: 'attachment-title'
 *             ref: 'attachmentTitle'
 *             flex: 1
 *           div
 *             className: 'attachment-subtitle'
 *             ref: 'attachmentSubtitle'
 *             flex: 1
 *         div className: 'clear'
 *     MiniUfi ref: 'miniUfi'
 *   FeedbackFlyout ref: 'feedbackFlyout'
 *
 * It renders to nested function calls of the form:
 * view({....options...}, child1, child2, ...);
 *
 * This view interpreter is meant to make it work.
 *
 * @provides javelin-view-interpreter
 * @requires javelin-view
 *           javelin-install
 *           javelin-dom
 */

JX.install('ViewInterpreter', {
  members : {
    register : function(name, view_cls) {
      this[name] = function(/* [properties, ]children... */) {
        var properties = arguments[0] || {};
        var children = Array.prototype.slice.call(arguments, 1);

        // Passing properties is optional
        if (properties instanceof JX.View ||
            properties instanceof JX.HTML ||
            properties.nodeType ||
            typeof properties === 'string') {
          children.unshift(properties);
          properties = {};
        }

        var result = new view_cls(properties).setName(name);
        result.addChildren(children);

        return result;
      };
    }
  }
});
