<?php

final class PhabricatorSpacesControl extends AphrontFormControl {

  private $object;

  protected function shouldRender() {
    // Render this control only if some Spaces exist.
    return PhabricatorSpacesNamespaceQuery::getAllSpaces();
  }

  public function setObject(PhabricatorSpacesInterface $object) {
    $this->object = $object;
    return $this;
  }

  protected function getCustomControlClass() {
    return '';
  }

  protected function getOptions() {
    $viewer = $this->getUser();
    $viewer_spaces = PhabricatorSpacesNamespaceQuery::getViewerSpaces($viewer);

    $map = mpull($viewer_spaces, 'getNamespaceName', 'getPHID');
    asort($map);

    return $map;
  }

  public function renderInput() {
    $viewer = $this->getUser();

    $this->setLabel(pht('Space'));

    $value = $this->getValue();
    if ($value === null) {
      $value = $viewer->getDefaultSpacePHID();
    }

    return AphrontFormSelectControl::renderSelectTag(
      $value,
      $this->getOptions(),
      array(
        'name' => $this->getName(),
      ));
  }

}
