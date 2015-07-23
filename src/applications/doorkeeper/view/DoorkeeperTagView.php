<?php

final class DoorkeeperTagView extends AphrontView {

  private $xobj;

  public function setExternalObject(DoorkeeperExternalObject $xobj) {
    $this->xobj = $xobj;
    return $this;
  }

  public function render() {
    $xobj = $this->xobj;
    if (!$xobj) {
      throw new PhutilInvalidStateException('setExternalObject');
    }

    $tag_id = celerity_generate_unique_node_id();

    $href = $xobj->getObjectURI();

    $spec = array(
      'id' => $tag_id,
      'ref' => array(
        $xobj->getApplicationType(),
        $xobj->getApplicationDomain(),
        $xobj->getObjectType(),
        $xobj->getObjectID(),
      ),
    );

    Javelin::initBehavior('doorkeeper-tag', array('tags' => array($spec)));

    return id(new PHUITagView())
      ->setID($tag_id)
      ->setHref($href)
      ->setName($href)
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setExternal(true);
  }

}
