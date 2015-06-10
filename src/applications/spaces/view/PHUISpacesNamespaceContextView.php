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

    $viewer = $this->getUser();
    return phutil_tag(
      'span',
      array(
        'class' => 'spaces-name',
      ),
      array(
        $viewer->renderHandle($space_phid),
        ' | ',
      ));
  }

}
