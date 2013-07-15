<?php

final class DifferentialAsanaRepresentationFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return (bool)PhabricatorEnv::getEnvConfig('asana.workspace-id');
  }

  public function renderLabelForRevisionView() {
    return pht('In Asana:');
  }

  public function renderValueForRevisionView() {
    $viewer = $this->getUser();
    $src_phid = $this->getRevision()->getPHID();
    $edge_type = PhabricatorEdgeConfig::TYPE_PHOB_HAS_ASANATASK;

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

    $tag_id = celerity_generate_unique_node_id();
    $xobj = $ref->getExternalObject();
    $href = $xobj->getObjectURI();

    Javelin::initBehavior(
      'doorkeeper-tag',
      array(
        'tags' => array(
          array(
            'id' => $tag_id,
            'ref' => array(
              $ref->getApplicationType(),
              $ref->getApplicationDomain(),
              $ref->getObjectType(),
              $ref->getObjectID(),
            ),
          ),
        ),
      ));

    return id(new PhabricatorTagView())
      ->setID($tag_id)
      ->setName($href)
      ->setHref($href)
      ->setType(PhabricatorTagView::TYPE_OBJECT)
      ->setExternal(true);
  }

}
