<?php

$conn_w = id(new PhabricatorMetaMTAMail())->establishConnection('w');
$lists = new LiskRawMigrationIterator($conn_w, 'metamta_mailinglist');

echo pht('Migrating mailing lists...')."\n";

foreach ($lists as $list) {
  $name = $list['name'];
  $email = $list['email'];
  $uri = $list['uri'];
  $old_phid = $list['phid'];

  $username = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name);
  $username = preg_replace('/-{2,}/', '-', $username);
  $username = trim($username, '-');
  if (!strlen($username)) {
    $username = 'mailinglist';
  }
  $username .= '-list';

  $username_okay = false;
  for ($suffix = 1; $suffix <= 9; $suffix++) {
    if ($suffix == 1) {
      $effective_username = $username;
    } else {
      $effective_username = $username.$suffix;
    }

    $collision = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUsernames(array($effective_username))
      ->executeOne();
    if (!$collision) {
      $username_okay = true;
      break;
    }
  }

  if (!$username_okay) {
    echo pht(
      'Failed to migrate mailing list "%s": unable to generate a unique '.
      'username for it.')."\n";
    continue;
  }

  $username = $effective_username;
  if (!PhabricatorUser::validateUsername($username)) {
    echo pht(
      'Failed to migrate mailing list "%s": unable to generate a valid '.
      'username for it.',
      $name)."\n";
    continue;
  }

  $address = id(new PhabricatorUserEmail())->loadOneWhere(
    'address = %s',
    $email);
  if ($address) {
    echo pht(
      'Failed to migrate mailing list "%s": an existing user already '.
      'has the email address "%s".',
      $name,
      $email)."\n";
    continue;
  }

  $user = id(new PhabricatorUser())
    ->setUsername($username)
    ->setRealName(pht('Mailing List "%s"', $name))
    ->setIsApproved(1)
    ->setIsMailingList(1);

  $email_object = id(new PhabricatorUserEmail())
    ->setAddress($email)
    ->setIsVerified(1);

  try {
    id(new PhabricatorUserEditor())
      ->setActor($user)
      ->createNewUser($user, $email_object);
  } catch (Exception $ex) {
    echo pht(
      'Failed to migrate mailing list "%s": %s.',
      $name,
      $ex->getMessage())."\n";
    continue;
  }

  $new_phid = $user->getPHID();

  // NOTE: After the PHID type is removed we can't use any Edge code to
  // modify edges.

  $edge_type = PhabricatorSubscribedToObjectEdgeType::EDGECONST;
  $edge_inverse = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;

  $map = PhabricatorPHIDType::getAllTypes();
  foreach ($map as $type => $spec) {
    try {
      $object = $spec->newObject();
      if (!$object) {
        continue;
      }
      $object_conn_w = $object->establishConnection('w');
      queryfx(
        $object_conn_w,
        'UPDATE %T SET dst = %s WHERE dst = %s AND type = %s',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $new_phid,
        $old_phid,
        $edge_inverse);
    } catch (Exception $ex) {
      // Just ignore these; they're mostly tables not existing.
      continue;
    }
  }

  try {
    $dst_phids = queryfx_all(
      $conn_w,
      'SELECT dst FROM %T WHERE src = %s AND type = %s',
      PhabricatorEdgeConfig::TABLE_NAME_EDGE,
      $old_phid,
      $edge_type);
    if ($dst_phids) {
      $editor = new PhabricatorEdgeEditor();
      foreach ($dst_phids as $dst_phid) {
        $editor->addEdge($new_phid, $edge_type, $dst_phid['dst']);
      }
      $editor->save();
    }
  } catch (Exception $ex) {
    echo pht(
      'Unable to migrate some inverse edges for mailing list "%s": %s.',
      $name,
      $ex->getMessage())."\n";
    continue;
  }

  echo pht(
    'Migrated mailing list "%s" to mailing list user "%s".',
    $name,
    $user->getUsername())."\n";
}
