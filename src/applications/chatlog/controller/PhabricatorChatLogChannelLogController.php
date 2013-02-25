<?php

final class PhabricatorChatLogChannelLogController
  extends PhabricatorChatLogController {

  private $channelID;

  public function willProcessRequest(array $data) {
    $this->channelID = $data['channelID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $uri = clone $request->getRequestURI();
    $uri->setQueryParams(array());

    $pager = new AphrontCursorPagerView();
    $pager->setURI($uri);
    $pager->setPageSize(250);

    $query = id(new PhabricatorChatLogQuery())
      ->setViewer($user)
      ->withChannelIDs(array($this->channelID));

    $channel = id(new PhabricatorChatLogChannelQuery())
              ->setViewer($user)
              ->withIDs(array($this->channelID))
              ->executeOne();

    if (!$channel) {
      return new Aphront404Response();
    }

    list($after, $before, $map) = $this->getPagingParameters($request, $query);

    $pager->setAfterID($after);
    $pager->setBeforeID($before);

    $logs = $query->executeWithCursorPager($pager);

    // Show chat logs oldest-first.
    $logs = array_reverse($logs);


    // Divide all the logs into blocks, where a block is the same author saying
    // several things in a row. A block ends when another user speaks, or when
    // two minutes pass without the author speaking.

    $blocks = array();
    $block = null;

    $last_author = null;
    $last_epoch = null;
    foreach ($logs as $log) {
      $this_author = $log->getAuthor();
      $this_epoch  = $log->getEpoch();

      // Decide whether we should start a new block or not.
      $new_block = ($this_author !== $last_author) ||
                   ($this_epoch - (60 * 2) > $last_epoch);

      if ($new_block) {
        if ($block) {
          $blocks[] = $block;
        }
        $block = array(
          'id'      => $log->getID(),
          'epoch'   => $this_epoch,
          'author'  => $this_author,
          'logs'    => array($log),
        );
      } else {
        $block['logs'][] = $log;
      }

      $last_author = $this_author;
      $last_epoch = $this_epoch;
    }
    if ($block) {
      $blocks[] = $block;
    }

    // Figure out CSS classes for the blocks. We alternate colors between
    // lines, and highlight the entire block which contains the target ID or
    // date, if applicable.

    foreach ($blocks as $key => $block) {
      $classes = array();
      if ($key % 2) {
        $classes[] = 'alternate';
      }
      $ids = mpull($block['logs'], 'getID', 'getID');
      if (array_intersect_key($ids, $map)) {
        $classes[] = 'highlight';
      }
      $blocks[$key]['class'] = $classes ? implode(' ', $classes) : null;
    }


    require_celerity_resource('phabricator-chatlog-css');

    $out = array();
    foreach ($blocks as $block) {
      $author = $block['author'];
      $author = phutil_utf8_shorten($author, 18);
      $author = phutil_tag('td', array('class' => 'author'), $author);

      $message = mpull($block['logs'], 'getMessage');
      $message = implode("\n", $message);
      $message = phutil_tag('td', array('class' => 'message'), $message);

      $href = $uri->alter('at', $block['id']);
      $timestamp = $block['epoch'];
      $timestamp = phabricator_datetime($timestamp, $user);
      $timestamp = phutil_tag('a', array('href' => $href), $timestamp);
      $timestamp = phutil_tag(
        'td',
        array(
          'class' => 'timestamp',
        ),
        $timestamp);

      $out[] = phutil_tag(
        'tr',
        array(
          'class' => $block['class'],
        ),
        array($author, $message, $timestamp));
    }

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($channel->getChannelName())
          ->setHref($uri));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setMethod('GET')
      ->setAction($uri)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Date'))
          ->setName('date')
          ->setValue($request->getStr('date')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Jump')));


    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        hsprintf(
          '<div class="phabricator-chat-log-panel">%s<br />%s%s</div>',
          $form->render(),
          phutil_tag('table', array('class' => 'phabricator-chat-log'), $out),
          $pager->render()),
      ),
      array(
        'title' => pht('Channel Log'),
      ));
  }

  /**
   * From request parameters, figure out where we should jump to in the log.
   * We jump to either a date or log ID, but load a few lines of context before
   * it so the user can see the nearby conversation.
   */
  private function getPagingParameters(
    AphrontRequest $request,
    PhabricatorChatLogQuery $query) {

    $user = $request->getUser();

    $at_id = $request->getInt('at');
    $at_date = $request->getStr('date');

    $context_log = null;
    $map = array();

    $query = clone $query;
    $query->setLimit(8);

    if ($at_id) {
      // Jump to the log in question, and load a few lines of context before
      // it.
      $context_logs = $query
        ->setAfterID($at_id)
        ->execute();

      $context_log = last($context_logs);

      $map = array(
        $at_id => true,
      );

    } else if ($at_date) {
      $timezone = new DateTimeZone($user->getTimezoneIdentifier());
      try {
        $date = new DateTime($at_date, $timezone);
        $timestamp = $date->format('U');
      } catch (Exception $e) {
        $timestamp = null;
      }

      if ($timestamp) {
        $context_logs = $query
          ->withMaximumEpoch($timestamp)
          ->execute();

        $context_log = last($context_logs);

        $target_log = head($context_logs);
        if ($target_log) {
          $map = array(
            $target_log->getID() => true,
          );
        }
      }
    }

    if ($context_log) {
      $after = null;
      $before = $context_log->getID() - 1;
    } else {
      $after = $request->getInt('after');
      $before = $request->getInt('before');
    }

    return array($after, $before, $map);
  }

}
