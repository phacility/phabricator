<?php

final class PhabricatorApplicationTransactionJSONDiffDetailView
  extends PhabricatorApplicationTransactionDetailView {

  public function setNew($new_object) {
    $json = new PhutilJSON();
    $this->setNewText($json->encodeFormatted($new_object));
    return $this;
  }

  public function setOld($old_object) {
    $json = new PhutilJSON();
    $this->setOldText($json->encodeFormatted($old_object));
    return $this;
  }
}
