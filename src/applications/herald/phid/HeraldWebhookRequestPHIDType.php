<?php

final class HeraldWebhookRequestPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HWBR';

  public function getTypeName() {
    return pht('Webhook Request');
  }

  public function newObject() {
    return new HeraldWebhook();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HeraldWebhookRequestQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $request = $objects[$phid];
      $handle->setName(pht('Webhook Request %d', $request->getID()));
    }
  }

}
