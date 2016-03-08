<?php

final class NuanceGitHubRepositoryImportCursor
  extends NuanceImportCursor {

  const CURSORTYPE = 'github.repository';

  protected function shouldPullDataFromSource() {
    $now = PhabricatorTime::getNow();

    // Respect GitHub's poll interval header. If we made a request recently,
    // don't make another one until we've waited long enough.
    $ttl = $this->getCursorProperty('github.poll.ttl');
    if ($ttl && ($ttl >= $now)) {
      $this->logInfo(
        pht(
          'Respecting "%s": waiting for %s second(s) to poll GitHub.',
          'X-Poll-Interval',
          new PhutilNumber(1 + ($ttl - $now))));

      return false;
    }

    // Respect GitHub's API rate limiting. If we've exceeded the rate limit,
    // wait until it resets to try again.
    $limit = $this->getCursorProperty('github.limit.ttl');
    if ($limit && ($limit >= $now)) {
      $this->logInfo(
        pht(
          'Respecting "%s": waiting for %s second(s) to poll GitHub.',
          'X-RateLimit-Reset',
          new PhutilNumber(1 + ($limit - $now))));
      return false;
    }

    return true;
  }

  protected function pullDataFromSource() {
    $source = $this->getSource();

    $user = $source->getSourceProperty('github.user');
    $repository = $source->getSourceProperty('github.repository');
    $api_token = $source->getSourceProperty('github.token');

    $uri = "/repos/{$user}/{$repository}/events";
    $data = array();

    $future = id(new PhutilGitHubFuture())
      ->setAccessToken($api_token)
      ->setRawGitHubQuery($uri, $data);

    $etag = $this->getCursorProperty('github.poll.etag');
    if ($etag) {
      $future->addHeader('If-None-Match', $etag);
    }

    $this->logInfo(
      pht(
        'Polling GitHub Repository API endpoint "%s".',
        $uri));
    $response = $future->resolve();

    // Do this first: if we hit the rate limit, we get a response but the
    // body isn't valid.
    $this->updateRateLimits($response);

    // This means we hit a rate limit or a "Not Modified" because of the "ETag"
    // header. In either case, we should bail out.
    if ($response->getStatus()->isError()) {
      // TODO: Save cursor data!
      return false;
    }

    $this->updateETag($response);

    var_dump($response->getBody());
  }

  private function updateRateLimits(PhutilGitHubResponse $response) {
    $remaining = $response->getHeaderValue('X-RateLimit-Remaining');
    $limit_reset = $response->getHeaderValue('X-RateLimit-Reset');
    $now = PhabricatorTime::getNow();

    $limit_ttl = null;
    if (strlen($remaining)) {
      $remaining = (int)$remaining;
      if (!$remaining) {
        $limit_ttl = (int)$limit_reset;
      }
    }

    $this->setCursorProperty('github.limit.ttl', $limit_ttl);

    $this->logInfo(
      pht(
        'This key has %s remaining API request(s), '.
        'limit resets in %s second(s).',
        new PhutilNumber($remaining),
        new PhutilNumber($limit_reset - $now)));
  }

  private function updateETag(PhutilGitHubResponse $response) {
    $etag = $response->getHeaderValue('ETag');

    $this->setCursorProperty('github.poll.etag', $etag);

    $this->logInfo(
      pht(
        'ETag for this request was "%s".',
        $etag));
  }

}
