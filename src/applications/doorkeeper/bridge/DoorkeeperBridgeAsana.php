<?php

final class DoorkeeperBridgeAsana extends DoorkeeperBridge {

  const APPTYPE_ASANA   = 'asana';
  const APPDOMAIN_ASANA = 'asana.com';
  const OBJTYPE_TASK    = 'asana:task';

  public function canPullRef(DoorkeeperObjectRef $ref) {
    if ($ref->getApplicationType() != self::APPTYPE_ASANA) {
      return false;
    }

    if ($ref->getApplicationDomain() != self::APPDOMAIN_ASANA) {
      return false;
    }

    $types = array(
      self::OBJTYPE_TASK => true,
    );

    return isset($types[$ref->getObjectType()]);
  }

  public function pullRefs(array $refs) {

    $id_map = mpull($refs, 'getObjectID', 'getObjectKey');
    $viewer = $this->getViewer();

    $provider = PhabricatorAsanaAuthProvider::getAsanaProvider();
    if (!$provider) {
      return;
    }

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withAccountTypes(array($provider->getProviderType()))
      ->withAccountDomains(array($provider->getProviderDomain()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    if (!$accounts) {
      return $this->didFailOnMissingLink();
    }

    // TODO: If the user has several linked Asana accounts, we just pick the
    // first one arbitrarily. We might want to try using all of them or do
    // something with more finesse. There's no UI way to link multiple accounts
    // right now so this is currently moot.
    $account = head($accounts);

    $token = $provider->getOAuthAccessToken($account);
    if (!$token) {
      return;
    }

    $template = id(new PhutilAsanaFuture())
      ->setAccessToken($token);

    $futures = array();
    foreach ($id_map as $key => $id) {
      $futures[$key] = id(clone $template)
        ->setRawAsanaQuery("tasks/{$id}");
    }

    $results = array();
    $failed = array();
    foreach (new FutureIterator($futures) as $key => $future) {
      try {
        $results[$key] = $future->resolve();
      } catch (Exception $ex) {
        if (($ex instanceof HTTPFutureResponseStatus) &&
            ($ex->getStatusCode() == 404)) {
          // This indicates that the object has been deleted (or never existed,
          // or isn't visible to the current user) but it's a successful sync of
          // an object which isn't visible.
        } else {
          // This is something else, so consider it a synchronization failure.
          phlog($ex);
          $failed[$key] = $ex;
        }
      }
    }

    foreach ($refs as $ref) {
      $ref->setAttribute('name', pht('Asana Task %s', $ref->getObjectID()));

      $did_fail = idx($failed, $ref->getObjectKey());
      if ($did_fail) {
        $ref->setSyncFailed(true);
        continue;
      }

      $result = idx($results, $ref->getObjectKey());
      if (!$result) {
        continue;
      }

      $ref->setIsVisible(true);
      $ref->setAttribute('asana.data', $result);
      $ref->setAttribute('fullname', pht('Asana: %s', $result['name']));
      $ref->setAttribute('title', $result['name']);
      $ref->setAttribute('description', $result['notes']);

      $obj = $ref->getExternalObject();
      if ($obj->getID()) {
        continue;
      }

      $this->fillObjectFromData($obj, $result);
      $this->saveExternalObject($ref, $obj);
    }
  }

  public function fillObjectFromData(DoorkeeperExternalObject $obj, $result) {
    $id = $result['id'];
    $uri = "https://app.asana.com/0/{$id}/{$id}";
    $obj->setObjectURI($uri);
  }

}
