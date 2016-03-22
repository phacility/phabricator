<?php

final class DoorkeeperBridgeGitHubIssue
  extends DoorkeeperBridgeGitHub {

  const OBJTYPE_GITHUB_ISSUE = 'github.issue';

  public function canPullRef(DoorkeeperObjectRef $ref) {
    if (!parent::canPullRef($ref)) {
      return false;
    }

    if ($ref->getObjectType() !== self::OBJTYPE_GITHUB_ISSUE) {
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
      list($user, $repository, $number) = $this->parseGitHubIssueID($id);
      $uri = "/repos/{$user}/{$repository}/issues/{$number}";
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
      $ref->setAttribute('name', pht('GitHub Issue %s', $ref->getObjectID()));

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

      $ref->setIsVisible(true);
      $ref->setAttribute('api.raw', $body);
      $ref->setAttribute('name', $body['title']);

      $obj = $ref->getExternalObject();

      $this->fillObjectFromData($obj, $result);

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $obj->save();
      unset($unguarded);
    }
  }

  public function fillObjectFromData(DoorkeeperExternalObject $obj, $result) {
    $body = $result->getBody();
    $uri = $body['html_url'];
    $obj->setObjectURI($uri);

    $title = idx($body, 'title');
    $description = idx($body, 'body');

    $created = idx($body, 'created_at');
    $created = strtotime($created);

    $state = idx($body, 'state');

    $obj->setProperty('task.title', $title);
    $obj->setProperty('task.description', $description);
    $obj->setProperty('task.created', $created);
    $obj->setProperty('task.state', $state);
  }

}
