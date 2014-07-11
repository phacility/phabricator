/**
 * @requires javelin-install
 * @javelin
 */

/**
 * This is not a real class, but @{function:JX.install} provides several methods
 * which exist on all Javelin classes. This class documents those methods.
 *
 * @task events Builtin Events
 */
JX.install('Base', {
  members : {

    /**
     * Invoke a class event, notifying all listeners. You must declare the
     * events your class invokes when you install it; see @{function:JX.install}
     * for documentation. Any arguments you provide will be passed to listener
     * callbacks.
     *
     * @param   string      Event type, must be declared when class is
     *                      installed.
     * @param   ...         Zero or more arguments.
     *
     * @return  @{JX.Event} Event object which was dispatched.
     * @task events
     */
    invoke : function(type, more) {
      // <docstub only, see JX.install()> //
    },

    /**
     * Listen for events emitted by this object instance. You can also use
     * the static flavor of this method to listen to events emitted by any
     * instance of this object.
     *
     * See also @{method:JX.Stratcom.listen}.
     *
     * @param  string   Type of event to listen for.
     * @param  function Function to call when this event occurs.
     * @return object   A reference to the installed listener. You can later
     *                  remove the listener by calling this object's remove()
     *                  method.
     * @task events
     */
    listen : function(type, callback) {
      // <docstub only, see JX.install()> //
    }

  },
  statics : {

    /**
     * Static listen interface for listening to events produced by any instance
     * of this class. See @{method:listen} for documentation.
     *
     * @param  string   Type of event to listen for.
     * @param  function Function to call when this event occurs.
     * @return object   A reference to the installed listener. You can later
     *                  remove the listener by calling this object's remove()
     *                  method.
     * @task events
     */
    listen : function(type, callback) {
      // <docstub only, see JX.install()> //
    }

  }
});
