<?php

abstract class NuanceGitHubImportCursor
  extends NuanceImportCursor {

  abstract protected function getGitHubAPIEndpointURI($user, $repository);
  abstract protected function newNuanceItemFromGitHubRecord(array $record);

  protected function getMaximumPage() {
    return 100;
  }

  protected function getPageSize() {
    return 100;
  }

  protected function getMinimumDelayBetweenPolls() {
    // Even if GitHub says we can, don't poll more than once every few seconds.
    // In particular, the Issue Events API does not advertise a poll interval
    // in a header.
    return 5;
  }

  final protected function shouldPullDataFromSource() {
    $now = PhabricatorTime::getNow();

    // Respect GitHub's poll interval header. If we made a request recently,
    // don't make another one until we've waited long enough.
    $ttl = $this->getCursorProperty('github.poll.ttl');
    if ($ttl && ($ttl >= $now)) {
      $this->logInfo(
        pht(
          'Respecting "%s" or minimum poll delay: waiting for %s second(s) '.
          'to poll GitHub.',
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

  final protected function pullDataFromSource() {
    $viewer = $this->getViewer();
    $now = PhabricatorTime::getNow();

    $source = $this->getSource();

    $user = $source->getSourceProperty('github.user');
    $repository = $source->getSourceProperty('github.repository');
    $api_token = $source->getSourceProperty('github.token');

    // This API only supports fetching 10 pages of 30 events each, for a total
    // of 300 events.
    $etag = null;
    $new_items = array();
    $hit_known_items = false;

    $max_page = $this->getMaximumPage();
    $page_size = $this->getPageSize();

    for ($page = 1; $page <= $max_page; $page++) {
      $uri = $this->getGitHubAPIEndpointURI($user, $repository);

      $data = array(
        'page' => $page,
        'per_page' => $page_size,
      );

      $future = id(new PhutilGitHubFuture())
        ->setAccessToken($api_token)
        ->setRawGitHubQuery($uri, $data);

      if ($page == 1) {
        $cursor_etag = $this->getCursorProperty('github.poll.etag');
        if ($cursor_etag) {
          $future->addHeader('If-None-Match', $cursor_etag);
        }
      }

      $this->logInfo(
        pht(
          'Polling GitHub Repository API endpoint "%s".',
          $uri));
      $response = $future->resolve();

      // Do this first: if we hit the rate limit, we get a response but the
      // body isn't valid.
      $this->updateRateLimits($response);

      if ($response->getStatus()->getStatusCode() == 304) {
        $this->logInfo(
          pht(
            'Received a 304 Not Modified from GitHub, no new events.'));
      }

      // This means we hit a rate limit or a "Not Modified" because of the
      // "ETag" header. In either case, we should bail out.
      if ($response->getStatus()->isError()) {
        $this->updatePolling($response, $now, false);
        $this->getCursorData()->save();
        return false;
      }

      if ($page == 1) {
        $etag = $response->getHeaderValue('ETag');
      }

      $records = $response->getBody();
      foreach ($records as $record) {
        $item = $this->newNuanceItemFromGitHubRecord($record);
        $item_key = $item->getItemKey();

        $this->logInfo(
          pht(
            'Fetched event "%s".',
            $item_key));

        $new_items[$item->getItemKey()] = $item;
      }

      if ($new_items) {
        $existing = id(new NuanceItemQuery())
          ->setViewer($viewer)
          ->withSourcePHIDs(array($source->getPHID()))
          ->withItemKeys(array_keys($new_items))
          ->execute();
        $existing = mpull($existing, null, 'getItemKey');
        foreach ($new_items as $key => $new_item) {
          if (isset($existing[$key])) {
            unset($new_items[$key]);
            $hit_known_items = true;

            $this->logInfo(
              pht(
                'Event "%s" is previously known.',
                $key));
          }
        }
      }

      if ($hit_known_items) {
        break;
      }

      if (count($records) < $page_size) {
        break;
      }
    }

    // TODO: When we go through the whole queue without hitting anything we
    // have seen before, we should record some sort of global event so we
    // can tell the user when the bridging started or was interrupted?
    if (!$hit_known_items) {
      $already_polled = $this->getCursorProperty('github.polled');
      if ($already_polled) {
        // TODO: This is bad: we missed some items, maybe because too much
        // stuff happened too fast or the daemons were broken for a long
        // time.
      } else {
        // TODO: This is OK, we're doing the initial import.
      }
    }

    if ($etag !== null) {
      $this->updateETag($etag);
    }

    $this->updatePolling($response, $now, true);

    // Reverse the new items so we insert them in chronological order.
    $new_items = array_reverse($new_items);

    $source->openTransaction();
      foreach ($new_items as $new_item) {
        $new_item->save();
      }
      $this->getCursorData()->save();
    $source->saveTransaction();

    foreach ($new_items as $new_item) {
      $new_item->scheduleUpdate();
    }

    return false;
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

  private function updateETag($etag) {

    $this->setCursorProperty('github.poll.etag', $etag);

    $this->logInfo(
      pht(
        'ETag for this request was "%s".',
        $etag));
  }

  private function updatePolling(
    PhutilGitHubResponse $response,
    $start,
    $success) {

    if ($success) {
      $this->setCursorProperty('github.polled', true);
    }

    $poll_interval = (int)$response->getHeaderValue('X-Poll-Interval');
    $poll_interval = max($this->getMinimumDelayBetweenPolls(), $poll_interval);

    $poll_ttl = $start + $poll_interval;
    $this->setCursorProperty('github.poll.ttl', $poll_ttl);

    $now = PhabricatorTime::getNow();

    $this->logInfo(
      pht(
        'Set API poll TTL to +%s second(s) (%s second(s) from now).',
        new PhutilNumber($poll_interval),
        new PhutilNumber($poll_ttl - $now)));
  }

}
