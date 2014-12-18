<?php

abstract class PhabricatorDashboardPanelType extends Phobject {

  abstract public function getPanelTypeKey();
  abstract public function getPanelTypeName();
  abstract public function getPanelTypeDescription();
  abstract public function getFieldSpecifications();

  abstract public function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine);

  public function initializeFieldsFromRequest(
    PhabricatorDashboardPanel $panel,
    PhabricatorCustomFieldList $field_list,
    AphrontRequest $request) {
    return;
  }

  /**
   * Should this panel pull content in over AJAX?
   *
   * Normally, panels use AJAX to render their content. This makes the page
   * interactable sooner, allows panels to render in parallel, and prevents one
   * slow panel from slowing everything down.
   *
   * However, some panels are very cheap to build (i.e., no expensive service
   * calls or complicated rendering). In these cases overall performance can be
   * improved by disabling async rendering so the panel rendering happens in the
   * same process.
   *
   * @return bool True to enable asynchronous rendering when appropriate.
   */
  public function shouldRenderAsync() {
    return true;
  }

  public function adjustPanelHeader(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine,
    PHUIActionHeaderView $header) {
    return $header;
  }

  public static function getAllPanelTypes() {
    static $types;

    if ($types === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      foreach ($objects as $object) {
        $key = $object->getPanelTypeKey();
        if (!empty($map[$key])) {
          $this_class = get_class($object);
          $that_class = get_class($map[$key]);
          throw new Exception(
            pht(
              'Two dashboard panels (of classes "%s" and "%s") have the '.
              'same panel type key ("%s"). Each panel type must have a '.
              'unique panel type key.',
              $this_class,
              $that_class,
              $key));
        }

        $map[$key] = $object;
      }

      $types = $map;
    }

    return $types;
  }

}
