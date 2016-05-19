<?php

final class PhabricatorAuthSSHKeySearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $sshKeyObject;

  public function setSSHKeyObject(PhabricatorSSHPublicKeyInterface $object) {
    $this->sshKeyObject = $object;
    return $this;
  }

  public function getSSHKeyObject() {
    return $this->sshKeyObject;
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function getResultTypeDescription() {
    return pht('SSH Keys');
  }

  public function getApplicationClassName() {
    return 'PhabricatorAuthApplication';
  }

  public function newQuery() {
    $object = $this->getSSHKeyObject();
    $object_phid = $object->getPHID();

    return id(new PhabricatorAuthSSHKeyQuery())
      ->withObjectPHIDs(array($object_phid));
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }


  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    $object = $this->getSSHKeyObject();
    $object_phid = $object->getPHID();

    return "/auth/sshkey/for/{$object_phid}/{$path}";
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Keys'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $keys,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($keys, 'PhabricatorAuthSSHKey');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($keys as $key) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('SSH Key %d', $key->getID()))
        ->setHeader($key->getName())
        ->setHref($key->getURI());

      if (!$key->getIsActive()) {
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No matching SSH keys.'));

    return $result;
  }
}
