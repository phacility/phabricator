<?php

/**
 * Defines a settings panel. Settings panels appear in the Settings application,
 * and behave like lightweight controllers -- generally, they render some sort
 * of form with options in it, and then update preferences when the user
 * submits the form. By extending this class, you can add new settings
 * panels.
 *
 * NOTE: This stuff is new and might not be completely stable.
 *
 * @task config   Panel Configuration
 * @task panel    Panel Implementation
 * @task internal Internals
 *
 * @group settings
 */
abstract class PhabricatorSettingsPanel {


/* -(  Panel Configuration  )------------------------------------------------ */


  /**
   * Return a unique string used in the URI to identify this panel, like
   * "example".
   *
   * @return string Unique panel identifier (used in URIs).
   * @task config
   */
  abstract public function getPanelKey();


  /**
   * Return a human-readable description of the panel's contents, like
   * "Example Settings".
   *
   * @return string Human-readable panel name.
   * @task config
   */
  abstract public function getPanelName();


  /**
   * Return a human-readable group name for this panel. For instance, if you
   * had several related panels like "Volume Settings" and
   * "Microphone Settings", you might put them in a group called "Audio".
   *
   * When displayed, panels are grouped with other panels that have the same
   * group name.
   *
   * @return string Human-readable panel group name.
   * @task config
   */
  abstract public function getPanelGroup();


  /**
   * Return false to prevent this panel from being displayed or used. You can
   * do, e.g., configuration checks here, to determine if the feature your
   * panel controls is unavailble in this install. By default, all panels are
   * enabled.
   *
   * @return bool True if the panel should be shown.
   * @task config
   */
  public function isEnabled() {
    return true;
  }


  /**
   * You can use this callback to generate multiple similar panels which all
   * share the same implementation. For example, OAuth providers each have a
   * separate panel, but the implementation for each panel is the same.
   *
   * To generate multiple panels, build them here and return a list. By default,
   * the current panel (`$this`) is returned alone. For most panels, this
   * is the right implementation.
   *
   * @return list<PhabricatorSettingsPanel> Zero or more panels.
   * @task config
   */
  public function buildPanels() {
    return array($this);
  }


/* -(  Panel Implementation  )----------------------------------------------- */


  /**
   * Process a user request for this settings panel. Implement this method like
   * a lightweight controller. If you return an @{class:AphrontResponse}, the
   * response will be used in whole. If you return anything else, it will be
   * treated as a view and composed into a normal settings page.
   *
   * Generally, render your settings panel by returning a form, then return
   * a redirect when the user saves settings.
   *
   * @param   AphrontRequest  Incoming request.
   * @return  wild            Response to request, either as an
   *                          @{class:AphrontResponse} or something which can
   *                          be composed into a @{class:AphrontView}.
   * @task panel
   */
  abstract public function processRequest(AphrontRequest $request);


  /**
   * Get the URI for this panel.
   *
   * @param string? Optional path to append.
   * @return string Relative URI for the panel.
   * @task panel
   */
  final public function getPanelURI($path = '') {
    $key = $this->getPanelKey();
    $key = phutil_escape_uri($key);
    return '/settings/panel/'.$key.'/'.ltrim($path, '/');
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Generates a key to sort the list of panels.
   *
   * @return string Sortable key.
   * @task internal
   */
  final public function getPanelSortKey() {
    return sprintf(
      '%s'.chr(255).'%s',
      $this->getPanelGroup(),
      $this->getPanelName());
  }

}
