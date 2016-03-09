<?php

abstract class NuanceWorker extends PhabricatorWorker {

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function loadItem($item_phid) {
    $item = id(new NuanceItemQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($item_phid))
      ->executeOne();

    if (!$item) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'There is no Nuance item with PHID "%s".',
          $item_phid));
    }

    return $item;
  }

}
