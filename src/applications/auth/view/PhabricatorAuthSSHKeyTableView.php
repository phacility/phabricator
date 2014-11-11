<?php

final class PhabricatorAuthSSHKeyTableView extends AphrontView {

  private $keys;
  private $canEdit;
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }

  public function setKeys(array $keys) {
    assert_instances_of($keys, 'PhabricatorAuthSSHKey');
    $this->keys = $keys;
    return $this;
  }

  public function render() {
    $keys = $this->keys;
    $viewer = $this->getUser();

    if ($this->canEdit) {
      $delete_class = 'small grey button';
    } else {
      $delete_class = 'small grey button disabled';
    }

    $rows = array();
    foreach ($keys as $key) {
      $rows[] = array(
        javelin_tag(
          'a',
          array(
            'href' => '/auth/sshkey/edit/'.$key->getID().'/',
            'sigil' => 'workflow',
          ),
          $key->getName()),
        $key->getKeyComment(),
        $key->getKeyType(),
        phabricator_datetime($key->getDateCreated(), $viewer),
        javelin_tag(
          'a',
          array(
            'href' => '/auth/sshkey/delete/'.$key->getID().'/',
            'class' => $delete_class,
            'sigil' => 'workflow',
          ),
          pht('Delete')),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString($this->noDataString)
      ->setHeaders(
        array(
          pht('Name'),
          pht('Comment'),
          pht('Type'),
          pht('Added'),
          null,
        ))
      ->setColumnClasses(
        array(
          'wide pri',
          '',
          '',
          'right',
          'action',
        ));

    return $table;
  }

}
