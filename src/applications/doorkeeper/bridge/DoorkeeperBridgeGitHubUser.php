<?php

final class DoorkeeperBridgeGitHubUser
  extends DoorkeeperBridgeGitHub {

  const OBJTYPE_GITHUB_USER = 'github.user';

  public function canPullRef(DoorkeeperObjectRef $ref) {
    if (!parent::canPullRef($ref)) {
      return false;
    }

    if ($ref->getObjectType() !== self::OBJTYPE_GITHUB_USER) {
      return false;
    }

    return true;
  }

  public function pullRefs(array $refs) {
    $token = $this->getGitHubAccessToken();
    if (!strlen($token)) {
      return null;
    }

    $template = id(new PhutilGitHubFuture())
      ->setAccessToken($token);

    $futures = array();
    $id_map = mpull($refs, 'getObjectID', 'getObjectKey');
    foreach ($id_map as $key => $id) {
      // GitHub doesn't provide a way to query for users by ID directly, but we
      // can list all users, ordered by ID, starting at some particular ID,
      // with a page size of one, which will achieve the desired effect.
      $one_less = ($id - 1);
      $uri = "/users?since={$one_less}&per_page=1";

      $data = array();
      $futures[$key] = id(clone $template)
        ->setRawGitHubQuery($uri, $data);
    }

    $results = array();
    $failed = array();
    foreach (new FutureIterator($futures) as $key => $future) {
      try {
        $results[$key] = $future->resolve();
      } catch (Exception $ex) {
        if (($ex instanceof HTTPFutureResponseStatus) &&
            ($ex->getStatusCode() == 404)) {
          // TODO: Do we end up here for deleted objects and invisible
          // objects?
        } else {
          phlog($ex);
          $failed[$key] = $ex;
        }
      }
    }

    $viewer = $this->getViewer();

    foreach ($refs as $ref) {
      $ref->setAttribute('name', pht('GitHub User %s', $ref->getObjectID()));

      $did_fail = idx($failed, $ref->getObjectKey());
      if ($did_fail) {
        $ref->setSyncFailed(true);
        continue;
      }

      $result = idx($results, $ref->getObjectKey());
      if (!$result) {
        continue;
      }

      $body = $result->getBody();
      if (!is_array($body) || !count($body)) {
        $ref->setSyncFailed(true);
        continue;
      }

      $spec = head($body);
      if (!is_array($spec)) {
        $ref->setSyncFailed(true);
        continue;
      }

      // Because we're using a paging query to load each user, if a user (say,
      // user ID 123) does not exist for some reason, we might get the next
      // user (say, user ID 124) back. Make sure the user we got back is really
      // the user we expect.
      $id = idx($spec, 'id');
      if ($id !== $ref->getObjectID()) {
        $ref->setSyncFailed(true);
        continue;
      }

      $ref->setIsVisible(true);
      $ref->setAttribute('api.raw', $spec);
      $ref->setAttribute('name', $spec['login']);

      $obj = $ref->getExternalObject();
      $this->fillObjectFromData($obj, $spec);

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $obj->save();
      unset($unguarded);
    }
  }

  public function fillObjectFromData(DoorkeeperExternalObject $obj, $spec) {
    $uri = $spec['html_url'];
    $obj->setObjectURI($uri);

    $login = $spec['login'];

    $obj->setDisplayName(pht('%s <%s>', $login, pht('GitHub')));
  }

}
