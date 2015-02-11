<?php

final class DifferentialAsanaRepresentationField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:asana-representation';
  }

  public function getFieldName() {
    return pht('In Asana');
  }

  public function canDisableField() {
    return false;
  }

  public function getFieldDescription() {
    return pht('Shows revision representation in Asana.');
  }

  public function shouldAppearInPropertyView() {
    return (bool)PhabricatorEnv::getEnvConfig('asana.workspace-id');
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $viewer = $this->getViewer();
    $src_phid = $this->getObject()->getPHID();
    $edge_type = PhabricatorObjectHasAsanaTaskEdgeType::EDGECONST;

    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($src_phid))
      ->withEdgeTypes(array($edge_type))
      ->needEdgeData(true);

    $edges = $query->execute();
    if (!$edges) {
      return null;
    }

    $edge = head($edges[$src_phid][$edge_type]);

    if (!empty($edge['data']['gone'])) {
      return phutil_tag(
        'em',
        array(),
        pht('Asana Task Deleted'));
    }

    $ref = id(new DoorkeeperImportEngine())
      ->setViewer($viewer)
      ->withPHIDs(array($edge['dst']))
      ->needLocalOnly(true)
      ->executeOne();

    if (!$ref) {
      return null;
    }

    return id(new DoorkeeperTagView())
      ->setExternalObject($ref->getExternalObject());
  }

}
