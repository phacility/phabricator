<?php

final class PhabricatorAuthSSHKeyTableView extends AphrontView {

  private $keys;
  private $canEdit;
  private $noDataString;
  private $showTrusted;
  private $showID;

  public static function newKeyActionsMenu(
    PhabricatorUser $viewer,
    PhabricatorSSHPublicKeyInterface $object) {

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    try {
      PhabricatorSSHKeyGenerator::assertCanGenerateKeypair();
      $can_generate = true;
    } catch (Exception $ex) {
      $can_generate = false;
    }

    $object_phid = $object->getPHID();

    $generate_uri = "/auth/sshkey/generate/?objectPHID={$object_phid}";
    $upload_uri = "/auth/sshkey/upload/?objectPHID={$object_phid}";
    $view_uri = "/auth/sshkey/for/{$object_phid}/";

    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->addAction(
        id(new PhabricatorActionView())
          ->setHref($upload_uri)
          ->setWorkflow(true)
          ->setDisabled(!$can_edit)
          ->setName(pht('Upload Public Key'))
          ->setIcon('fa-upload'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setHref($generate_uri)
          ->setWorkflow(true)
          ->setDisabled(!$can_edit || !$can_generate)
          ->setName(pht('Generate Keypair'))
          ->setIcon('fa-lock'))
      ->addAction(
        id(new PhabricatorActionView())
          ->setHref($view_uri)
          ->setName(pht('View History'))
          ->setIcon('fa-list-ul'));

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('SSH Key Actions'))
      ->setHref('#')
      ->setIcon('fa-gear')
      ->setDropdownMenu($action_view);
  }

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
            'href' => $key->getURI(),
          ),
          $key->getName()),
        $key->getIsTrusted() ? $trusted_icon : $untrusted_icon,
        $key->getKeyComment(),
        $key->getKeyType(),
        phabricator_datetime($key->getDateCreated(), $viewer),
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
        ));

    return $table;
  }

}
