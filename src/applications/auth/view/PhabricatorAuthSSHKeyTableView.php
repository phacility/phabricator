<?php

final class PhabricatorAuthSSHKeyTableView extends AphrontView {

  private $keys;
  private $canEdit;
  private $noDataString;
  private $showTrusted;
  private $showID;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }

  public function setShowTrusted($show_trusted) {
    $this->showTrusted = $show_trusted;
    return $this;
  }

  public function setShowID($show_id) {
    $this->showID = $show_id;
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

    $trusted_icon = id(new PHUIIconView())
      ->setIcon('fa-star blue');
    $untrusted_icon = id(new PHUIIconView())
      ->setIcon('fa-times grey');

    $rows = array();
    foreach ($keys as $key) {
      $rows[] = array(
        $key->getID(),
        javelin_tag(
          'a',
          array(
            'href' => '/auth/sshkey/edit/'.$key->getID().'/',
            'sigil' => 'workflow',
          ),
          $key->getName()),
        $key->getIsTrusted() ? $trusted_icon : $untrusted_icon,
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
          pht('ID'),
          pht('Name'),
          pht('Trusted'),
          pht('Comment'),
          pht('Type'),
          pht('Added'),
          null,
        ))
      ->setColumnVisibility(
        array(
          $this->showID,
          true,
          $this->showTrusted,
        ))
      ->setColumnClasses(
        array(
          '',
          'wide pri',
          'center',
          '',
          '',
          'right',
          'action',
        ));

    return $table;
  }

}
