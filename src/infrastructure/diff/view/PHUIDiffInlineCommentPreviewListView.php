<?php

final class PHUIDiffInlineCommentPreviewListView
  extends AphrontView {

  private $inlineComments = array();
  private $ownerPHID;

  public function setInlineComments(array $comments) {
    assert_instances_of($comments, 'PhabricatorApplicationTransactionComment');
    $this->inlineComments = $comments;
    return $this;
  }

  public function getInlineComments() {
    return $this->inlineComments;
  }

  public function setOwnerPHID($owner_phid) {
    $this->ownerPHID = $owner_phid;
    return $this;
  }

  public function getOwnerPHID() {
    return $this->ownerPHID;
  }

  public function render() {
    $viewer = $this->getViewer();

    $inlines = $this->getInlineComments();
    foreach ($inlines as $key => $inline) {
      $inlines[$key] = DifferentialInlineComment::newFromModernComment(
        $inline);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $owner_phid = $this->getOwnerPHID();

    $handles = $viewer->loadHandles(array($viewer->getPHID()));
    $handles = iterator_to_array($handles);

    $views = array();
    foreach ($inlines as $inline) {
      $views[] = id(new PHUIDiffInlineCommentDetailView())
        ->setUser($viewer)
        ->setInlineComment($inline)
        ->setMarkupEngine($engine)
        ->setHandles($handles)
        ->setEditable(false)
        ->setPreview(true)
        ->setCanMarkDone(false)
        ->setObjectOwnerPHID($owner_phid);
    }

    return $views;
  }

}
