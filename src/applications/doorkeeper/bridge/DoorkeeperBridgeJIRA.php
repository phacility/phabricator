<?php

final class DoorkeeperBridgeJIRA extends DoorkeeperBridge {

  const APPTYPE_JIRA = 'jira';
  const OBJTYPE_ISSUE = 'jira:issue';

  public function canPullRef(DoorkeeperObjectRef $ref) {
    if ($ref->getApplicationType() != self::APPTYPE_JIRA) {
      return false;
    }

    $types = array(
      self::OBJTYPE_ISSUE => true,
    );

    return isset($types[$ref->getObjectType()]);
  }

  public function pullRefs(array $refs) {

    $id_map = mpull($refs, 'getObjectID', 'getObjectKey');
    $viewer = $this->getViewer();

    $provider = PhabricatorJIRAAuthProvider::getJIRAProvider();
    if (!$provider) {
      return;
    }

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withAccountTypes(array($provider->getProviderType()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    if (!$accounts) {
      return $this->didFailOnMissingLink();
    }

    // TODO: When we support multiple JIRA instances, we need to disambiguate
    // issues (perhaps with additional configuration) or cast a wide net
    // (by querying all instances). For now, just query the one instance.
    $account = head($accounts);

    $futures = array();
    foreach ($id_map as $key => $id) {
      $futures[$key] = $provider->newJIRAFuture(
        $account,
        'rest/api/2/issue/'.phutil_escape_uri($id),
        'GET');
    }

    $results = array();
    $failed = array();
    foreach (new FutureIterator($futures) as $key => $future) {
      try {
        $results[$key] = $future->resolveJSON();
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
      $ref->setAttribute('name', pht('JIRA %s', $ref->getObjectID()));

      $did_fail = idx($failed, $ref->getObjectKey());
      if ($did_fail) {
        $ref->setSyncFailed(true);
        continue;
      }

      $result = idx($results, $ref->getObjectKey());
      if (!$result) {
        continue;
      }

      $fields = idx($result, 'fields', array());

      $ref->setIsVisible(true);
      $ref->setAttribute(
        'fullname',
        pht('JIRA %s %s', $result['key'], idx($fields, 'summary')));

      $ref->setAttribute('title', idx($fields, 'summary'));
      $ref->setAttribute('description', idx($result, 'description'));

      $obj = $ref->getExternalObject();
      if ($obj->getID()) {
        continue;
      }

      $this->fillObjectFromData($obj, $result);
      $this->saveExternalObject($ref, $obj);
    }
  }

  public function fillObjectFromData(DoorkeeperExternalObject $obj, $result) {
    // Convert the "self" URI, which points at the REST endpoint, into a
    // browse URI.
    $self = idx($result, 'self');
    $object_id = $obj->getObjectID();

    $uri = self::getJIRAIssueBrowseURIFromJIRARestURI($self, $object_id);
    if ($uri !== null) {
      $obj->setObjectURI($uri);
    }
  }

  public static function getJIRAIssueBrowseURIFromJIRARestURI(
    $uri,
    $object_id) {

    $uri = new PhutilURI($uri);

    // The JIRA install might not be at the domain root, so we may need to
    // keep an initial part of the path, like "/jira/". Find the API specific
    // part of the URI, strip it off, then replace it with the web version.
    $path = $uri->getPath();
    $pos = strrpos($path, 'rest/api/2/issue/');
    if ($pos === false) {
      return null;
    }

    $path = substr($path, 0, $pos);
    $path = $path.'browse/'.$object_id;
    $uri->setPath($path);

    return (string)$uri;
  }

}
