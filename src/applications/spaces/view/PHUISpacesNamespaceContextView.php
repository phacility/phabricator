<?php

final class PHUISpacesNamespaceContextView extends AphrontView {

  private $object;

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function render() {
    $object = $this->getObject();

    $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID($object);
    if (!$space_phid) {
      return null;
    }

    // If the viewer can't see spaces, pretend they don't exist.
    $viewer = $this->getUser();
    if (!PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer)) {
      return null;
    }

    // If this is the default space, don't show a space label.
    $default = PhabricatorSpacesNamespaceQuery::getDefaultSpace();
    if ($default) {
      if ($default->getPHID() == $space_phid) {
        return null;
      }
    }

    return phutil_tag(
      'span',
      array(
        'class' => 'spaces-name',
      ),
      array(
        $viewer->renderHandle($space_phid)->setUseShortName(true),
        ' | ',
      ));
  }

}
