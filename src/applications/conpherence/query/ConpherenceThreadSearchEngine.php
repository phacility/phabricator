<?php

final class ConpherenceThreadSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Rooms');
  }

  public function getApplicationClassName() {
    return 'PhabricatorConpherenceApplication';
  }

  public function newQuery() {
    return id(new ConpherenceThreadQuery())
      ->needParticipantCache(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Participants'))
        ->setKey('participants')
        ->setAliases(array('participant')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Contains Words'))
        ->setKey('fulltext'),
    );
  }

  protected function getDefaultFieldOrder() {
    return array(
      'participants',
      '...',
    );
  }

  protected function shouldShowOrderField() {
    return false;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    if ($map['participants']) {
      $query->withParticipantPHIDs($map['participants']);
    }
    if ($map['fulltext']) {
      $query->withFulltext($map['fulltext']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/conpherence/search/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    $names['all'] = pht('All Rooms');

    if ($this->requireViewer()->isLoggedIn()) {
      $names['participant'] = pht('Joined Rooms');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'participant':
        return $query->setParameter(
          'participants',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $conpherences,
    PhabricatorSavedQuery $query) {

    $recent = mpull($conpherences, 'getRecentParticipantPHIDs');
    return array_unique(array_mergev($recent));
  }

  protected function renderResultList(
    array $conpherences,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($conpherences, 'ConpherenceThread');

    $viewer = $this->requireViewer();

    $policy_objects = ConpherenceThread::loadViewPolicyObjects(
      $viewer,
      $conpherences);

    $engines = array();

    $fulltext = $query->getParameter('fulltext');
    if (strlen($fulltext) && $conpherences) {
      $context = $this->loadContextMessages($conpherences, $fulltext);

      $author_phids = array();
      foreach ($context as $phid => $messages) {
        $conpherence = $conpherences[$phid];

        $engine = id(new PhabricatorMarkupEngine())
          ->setViewer($viewer)
          ->setContextObject($conpherence);

        foreach ($messages as $group) {
          foreach ($group as $message) {
            $xaction = $message['xaction'];
            if ($xaction) {
              $author_phids[] = $xaction->getAuthorPHID();
              $engine->addObject(
                $xaction->getComment(),
                PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
            }
          }
        }
        $engine->process();

        $engines[$phid] = $engine;
      }

      $handles = $viewer->loadHandles($author_phids);
      $handles = iterator_to_array($handles);
    } else {
      $context = array();
    }

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($conpherences as $conpherence_phid => $conpherence) {
      $created = phabricator_date($conpherence->getDateCreated(), $viewer);
      $title = $conpherence->getDisplayTitle($viewer);
      $monogram = $conpherence->getMonogram();

      $icon_name = $conpherence->getPolicyIconName($policy_objects);
      $icon = id(new PHUIIconView())
        ->setIcon($icon_name);
      $item = id(new PHUIObjectItemView())
        ->setObjectName($conpherence->getMonogram())
        ->setHeader($title)
        ->setHref('/'.$conpherence->getMonogram())
        ->setObject($conpherence)
        ->addIcon('none', $created)
        ->addIcon(
          'none',
          pht('Messages: %d', $conpherence->getMessageCount()))
        ->addAttribute(
          array(
            $icon,
            ' ',
            pht(
              'Last updated %s',
              phabricator_datetime($conpherence->getDateModified(), $viewer)),
          ));

      $messages = idx($context, $conpherence_phid);
      if ($messages) {
        foreach ($messages as $group) {
          $rows = array();
          foreach ($group as $message) {
            $xaction = $message['xaction'];
            if (!$xaction) {
              continue;
            }

            $view = id(new ConpherenceTransactionView())
              ->setUser($viewer)
              ->setHandles($handles)
              ->setMarkupEngine($engines[$conpherence_phid])
              ->setConpherenceThread($conpherence)
              ->setConpherenceTransaction($xaction)
              ->setFullDisplay(false)
              ->addClass('conpherence-fulltext-result');

            if ($message['match']) {
              $view->addClass('conpherence-fulltext-match');
            }

            $rows[] = $view;
          }

          $box = id(new PHUIBoxView())
            ->appendChild($rows)
            ->addClass('conpherence-fulltext-results');
          $item->appendChild($box);
        }
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No threads found.'));

    return $result;
  }

  private function loadContextMessages(array $threads, $fulltext) {
    $phids = mpull($threads, 'getPHID');

    // We want to load a few messages for each thread in the result list, to
    // show some of the actual content hits to help the user find what they
    // are looking for.

    // This method is trying to batch this lookup in most cases, so we do
    // between one and "a handful" of queries instead of one per thread in
    // most cases. To do this:
    //
    //   - Load a big block of results for all of the threads.
    //   - If we didn't get a full block back, we have everything that matches
    //     the query. Sort it out and exit.
    //   - Otherwise, some threads had a ton of hits, so we might not be
    //     getting everything we want (we could be getting back 1,000 hits for
    //     the first thread). Remove any threads which we have enough results
    //     for and try again.
    //   - Repeat until we have everything or every thread has enough results.
    //
    // In the worst case, we could end up degrading to one query per thread,
    // but this is incredibly unlikely on real data.

    // Size of the result blocks we're going to load.
    $limit = 1000;

    // Number of messages we want for each thread.
    $want = 3;

    $need = $phids;
    $hits = array();
    while ($need) {
      $rows = id(new ConpherenceFulltextQuery())
        ->withThreadPHIDs($need)
        ->withFulltext($fulltext)
        ->setLimit($limit)
        ->execute();

      foreach ($rows as $row) {
        $hits[$row['threadPHID']][] = $row;
      }

      if (count($rows) < $limit) {
        break;
      }

      foreach ($need as $key => $phid) {
        if (count($hits[$phid]) >= $want) {
          unset($need[$key]);
        }
      }
    }

    // Now that we have all the fulltext matches, throw away any extras that we
    // aren't going to render so we don't need to do lookups on them.
    foreach ($hits as $phid => $rows) {
      if (count($rows) > $want) {
        $hits[$phid] = array_slice($rows, 0, $want);
      }
    }

    // For each fulltext match, we want to render a message before and after
    // the match to give it some context. We already know the transactions
    // before each match because the rows have a "previousTransactionPHID",
    // but we need to do one more query to figure out the transactions after
    // each match.

    // Collect the transactions we want to find the next transactions for.
    $after = array();
    foreach ($hits as $phid => $rows) {
      foreach ($rows as $row) {
        $after[] = $row['transactionPHID'];
      }
    }

    // Look up the next transactions.
    if ($after) {
      $after_rows = id(new ConpherenceFulltextQuery())
        ->withPreviousTransactionPHIDs($after)
        ->execute();
    } else {
      $after_rows = array();
    }

    // Build maps from PHIDs to the previous and next PHIDs.
    $prev_map = array();
    $next_map = array();
    foreach ($after_rows as $row) {
      $next_map[$row['previousTransactionPHID']] = $row['transactionPHID'];
    }

    foreach ($hits as $phid => $rows) {
      foreach ($rows as $row) {
        $prev = $row['previousTransactionPHID'];
        if ($prev) {
          $prev_map[$row['transactionPHID']] = $prev;
          $next_map[$prev] = $row['transactionPHID'];
        }
      }
    }

    // Now we're going to collect the actual transaction PHIDs, in order, that
    // we want to show for each thread.
    $groups = array();
    foreach ($hits as $thread_phid => $rows) {
      $rows = ipull($rows, null, 'transactionPHID');
      $done = array();
      foreach ($rows as $phid => $row) {
        if (isset($done[$phid])) {
          continue;
        }
        $done[$phid] = true;

        $group = array();

        // Walk backward, finding all the previous results. We can just keep
        // going until we run out of results because we've only loaded things
        // that we want to show.
        $prev = $phid;
        while (true) {
          if (!isset($prev_map[$prev])) {
            // No previous transaction, so we're done.
            break;
          }

          $prev = $prev_map[$prev];

          if (isset($rows[$prev])) {
            $match = true;
            $done[$prev] = true;
          } else {
            $match = false;
          }

          $group[] = array(
            'phid' => $prev,
            'match' => $match,
          );
        }

        if (count($group) > 1) {
          $group = array_reverse($group);
        }

        $group[] = array(
          'phid' => $phid,
          'match' => true,
        );

        $next = $phid;
        while (true) {
          if (!isset($next_map[$next])) {
            break;
          }

          $next = $next_map[$next];

          if (isset($rows[$next])) {
            $match = true;
            $done[$next] = true;
          } else {
            $match = false;
          }

          $group[] = array(
            'phid' => $next,
            'match' => $match,
          );
        }

        $groups[$thread_phid][] = $group;
      }
    }

    // Load all the actual transactions we need.
    $xaction_phids = array();
    foreach ($groups as $thread_phid => $group) {
      foreach ($group as $list) {
        foreach ($list as $item) {
          $xaction_phids[] = $item['phid'];
        }
      }
    }

    if ($xaction_phids) {
      $xactions = id(new ConpherenceTransactionQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($xaction_phids)
        ->needComments(true)
        ->execute();
      $xactions = mpull($xactions, null, 'getPHID');
    } else {
      $xactions = array();
    }

    foreach ($groups as $thread_phid => $group) {
      foreach ($group as $key => $list) {
        foreach ($list as $lkey => $item) {
          $xaction = idx($xactions, $item['phid']);
          if ($xaction->shouldHide()) {
            continue;
          }
          $groups[$thread_phid][$key][$lkey]['xaction'] = $xaction;
        }
      }
    }

    // TODO: Sort the groups chronologically?

    return $groups;
  }

}
