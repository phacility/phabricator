<?php

interface PhabricatorDraftInterface {

  public function newDraftEngine();

  public function getHasDraft(PhabricatorUser $viewer);
  public function attachHasDraft(PhabricatorUser $viewer, $has_draft);

}

/* -(  PhabricatorDraftInterface  )------------------------------------------ */
/*

  public function newDraftEngine() {
    return new <...>DraftEngine();
  }

  public function getHasDraft(PhabricatorUser $viewer) {
    return $this->assertAttachedKey($this->drafts, $viewer->getCacheFragment());
  }

  public function attachHasDraft(PhabricatorUser $viewer, $has_draft) {
    $this->drafts[$viewer->getCacheFragment()] = $has_draft;
    return $this;
  }

*/
