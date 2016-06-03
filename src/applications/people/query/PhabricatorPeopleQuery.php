<?php

final class PhabricatorPeopleQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $usernames;
  private $realnames;
  private $emails;
  private $phids;
  private $ids;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $isAdmin;
  private $isSystemAgent;
  private $isMailingList;
  private $isDisabled;
  private $isApproved;
  private $nameLike;
  private $nameTokens;

  private $needPrimaryEmail;
  private $needProfile;
  private $needProfileImage;
  private $needAvailability;
  private $needBadges;
  private $cacheKeys = array();

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withEmails(array $emails) {
    $this->emails = $emails;
    return $this;
  }

  public function withRealnames(array $realnames) {
    $this->realnames = $realnames;
    return $this;
  }

  public function withUsernames(array $usernames) {
    $this->usernames = $usernames;
    return $this;
  }

  public function withDateCreatedBefore($date_created_before) {
    $this->dateCreatedBefore = $date_created_before;
    return $this;
  }

  public function withDateCreatedAfter($date_created_after) {
    $this->dateCreatedAfter = $date_created_after;
    return $this;
  }

  public function withIsAdmin($admin) {
    $this->isAdmin = $admin;
    return $this;
  }

  public function withIsSystemAgent($system_agent) {
    $this->isSystemAgent = $system_agent;
    return $this;
  }

  public function withIsMailingList($mailing_list) {
    $this->isMailingList = $mailing_list;
    return $this;
  }

  public function withIsDisabled($disabled) {
    $this->isDisabled = $disabled;
    return $this;
  }

  public function withIsApproved($approved) {
    $this->isApproved = $approved;
    return $this;
  }

  public function withNameLike($like) {
    $this->nameLike = $like;
    return $this;
  }

  public function withNameTokens(array $tokens) {
    $this->nameTokens = array_values($tokens);
    return $this;
  }

  public function needPrimaryEmail($need) {
    $this->needPrimaryEmail = $need;
    return $this;
  }

  public function needProfile($need) {
    $this->needProfile = $need;
    return $this;
  }

  public function needProfileImage($need) {
    $this->needProfileImage = $need;
    return $this;
  }

  public function needAvailability($need) {
    $this->needAvailability = $need;
    return $this;
  }

  public function needBadges($need) {
    $this->needBadges = $need;
    return $this;
  }

  public function needUserSettings($need) {
    $cache_key = PhabricatorUserPreferencesCacheType::KEY_PREFERENCES;

    if ($need) {
      $this->cacheKeys[$cache_key] = true;
    } else {
      unset($this->cacheKeys[$cache_key]);
    }

    return $this;
  }

  public function newResultObject() {
    return new PhabricatorUser();
  }

  protected function loadPage() {
    $table = new PhabricatorUser();
    $data = $this->loadStandardPageRows($table);

    if ($this->needPrimaryEmail) {
      $table->putInSet(new LiskDAOSet());
    }

    return $table->loadAllFromArray($data);
  }

  protected function didFilterPage(array $users) {
    if ($this->needProfile) {
      $user_list = mpull($users, null, 'getPHID');
      $profiles = new PhabricatorUserProfile();
      $profiles = $profiles->loadAllWhere(
        'userPHID IN (%Ls)',
        array_keys($user_list));

      $profiles = mpull($profiles, null, 'getUserPHID');
      foreach ($user_list as $user_phid => $user) {
        $profile = idx($profiles, $user_phid);

        if (!$profile) {
          $profile = PhabricatorUserProfile::initializeNewProfile($user);
        }

        $user->attachUserProfile($profile);
      }
    }

    if ($this->needBadges) {
      $awards = id(new PhabricatorBadgesAwardQuery())
        ->setViewer($this->getViewer())
        ->withRecipientPHIDs(mpull($users, 'getPHID'))
        ->execute();

      $awards = mgroup($awards, 'getRecipientPHID');

      foreach ($users as $user) {
        $user_awards = idx($awards, $user->getPHID(), array());
        $badge_phids = mpull($user_awards, 'getBadgePHID');
        $user->attachBadgePHIDs($badge_phids);
      }
    }

    if ($this->needProfileImage) {
      $rebuild = array();
      foreach ($users as $user) {
        $image_uri = $user->getProfileImageCache();
        if ($image_uri) {
          // This user has a valid cache, so we don't need to fetch any
          // data or rebuild anything.

          $user->attachProfileImageURI($image_uri);
          continue;
        }

        // This user's cache is invalid or missing, so we're going to rebuild
        // it.
        $rebuild[] = $user;
      }

      if ($rebuild) {
        $file_phids = mpull($rebuild, 'getProfileImagePHID');
        $file_phids = array_filter($file_phids);

        if ($file_phids) {
          // NOTE: We're using the omnipotent user here because older profile
          // images do not have the 'profile' flag, so they may not be visible
          // to the executing viewer. At some point, we could migrate to add
          // this flag and then use the real viewer, or just use the real
          // viewer after enough time has passed to limit the impact of old
          // data. The consequence of missing here is that we cache a default
          // image when a real image exists.
          $files = id(new PhabricatorFileQuery())
            ->setParentQuery($this)
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withPHIDs($file_phids)
            ->execute();
          $files = mpull($files, null, 'getPHID');
        } else {
          $files = array();
        }

        foreach ($rebuild as $user) {
          $image_phid = $user->getProfileImagePHID();
          if (isset($files[$image_phid])) {
            $image_uri = $files[$image_phid]->getBestURI();
          } else {
            $image_uri = PhabricatorUser::getDefaultProfileImageURI();
          }

          $user->writeProfileImageCache($image_uri);
          $user->attachProfileImageURI($image_uri);
        }
      }
    }

    if ($this->needAvailability) {
      $rebuild = array();
      foreach ($users as $user) {
        $cache = $user->getAvailabilityCache();
        if ($cache !== null) {
          $user->attachAvailability($cache);
        } else {
          $rebuild[] = $user;
        }
      }

      if ($rebuild) {
        $this->rebuildAvailabilityCache($rebuild);
      }
    }

    $this->fillUserCaches($users);

    return $users;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->nameTokens) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->emails) {
      $email_table = new PhabricatorUserEmail();
      $joins[] = qsprintf(
        $conn,
        'JOIN %T email ON email.userPHID = user.PHID',
        $email_table->getTableName());
    }

    if ($this->nameTokens) {
      foreach ($this->nameTokens as $key => $token) {
        $token_table = 'token_'.$key;
        $joins[] = qsprintf(
          $conn,
          'JOIN %T %T ON %T.userID = user.id AND %T.token LIKE %>',
          PhabricatorUser::NAMETOKEN_TABLE,
          $token_table,
          $token_table,
          $token_table,
          $token);
      }
    }

    return  $joins;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->usernames !== null) {
      $where[] = qsprintf(
        $conn,
        'user.userName IN (%Ls)',
        $this->usernames);
    }

    if ($this->emails !== null) {
      $where[] = qsprintf(
        $conn,
        'email.address IN (%Ls)',
        $this->emails);
    }

    if ($this->realnames !== null) {
      $where[] = qsprintf(
        $conn,
        'user.realName IN (%Ls)',
        $this->realnames);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'user.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'user.id IN (%Ld)',
        $this->ids);
    }

    if ($this->dateCreatedAfter) {
      $where[] = qsprintf(
        $conn,
        'user.dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore) {
      $where[] = qsprintf(
        $conn,
        'user.dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    if ($this->isAdmin !== null) {
      $where[] = qsprintf(
        $conn,
        'user.isAdmin = %d',
        (int)$this->isAdmin);
    }

    if ($this->isDisabled !== null) {
      $where[] = qsprintf(
        $conn,
        'user.isDisabled = %d',
        (int)$this->isDisabled);
    }

    if ($this->isApproved !== null) {
      $where[] = qsprintf(
        $conn,
        'user.isApproved = %d',
        (int)$this->isApproved);
    }

    if ($this->isSystemAgent !== null) {
      $where[] = qsprintf(
        $conn,
        'user.isSystemAgent = %d',
        (int)$this->isSystemAgent);
    }

    if ($this->isMailingList !== null) {
      $where[] = qsprintf(
        $conn,
        'user.isMailingList = %d',
        (int)$this->isMailingList);
    }

    if (strlen($this->nameLike)) {
      $where[] = qsprintf(
        $conn,
        'user.username LIKE %~ OR user.realname LIKE %~',
        $this->nameLike,
        $this->nameLike);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'user';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'username' => array(
        'table' => 'user',
        'column' => 'username',
        'type' => 'string',
        'reverse' => true,
        'unique' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $user = $this->loadCursorObject($cursor);
    return array(
      'id' => $user->getID(),
      'username' => $user->getUsername(),
    );
  }

  private function rebuildAvailabilityCache(array $rebuild) {
    $rebuild = mpull($rebuild, null, 'getPHID');

    // Limit the window we look at because far-future events are largely
    // irrelevant and this makes the cache cheaper to build and allows it to
    // self-heal over time.
    $min_range = PhabricatorTime::getNow();
    $max_range = $min_range + phutil_units('72 hours in seconds');

    // NOTE: We don't need to generate ghosts here, because we only care if
    // the user is attending, and you can't attend a ghost event: RSVP'ing
    // to it creates a real event.

    $events = id(new PhabricatorCalendarEventQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withInvitedPHIDs(array_keys($rebuild))
      ->withIsCancelled(false)
      ->withDateRange($min_range, $max_range)
      ->execute();

    // Group all the events by invited user. Only examine events that users
    // are actually attending.
    $map = array();
    foreach ($events as $event) {
      foreach ($event->getInvitees() as $invitee) {
        if (!$invitee->isAttending()) {
          continue;
        }

        $invitee_phid = $invitee->getInviteePHID();
        if (!isset($rebuild[$invitee_phid])) {
          continue;
        }

        $map[$invitee_phid][] = $event;
      }
    }

    foreach ($rebuild as $phid => $user) {
      $events = idx($map, $phid, array());

      $cursor = $min_range;
      if ($events) {
        // Find the next time when the user has no meetings. If we move forward
        // because of an event, we check again for events after that one ends.
        while (true) {
          foreach ($events as $event) {
            $from = $event->getDateFromForCache();
            $to = $event->getDateTo();
            if (($from <= $cursor) && ($to > $cursor)) {
              $cursor = $to;
              continue 2;
            }
          }
          break;
        }
      }

      if ($cursor > $min_range) {
        $availability = array(
          'until' => $cursor,
        );
        $availability_ttl = $cursor;
      } else {
        $availability = array(
          'until' => null,
        );
        $availability_ttl = $max_range;
      }

      // Never TTL the cache to longer than the maximum range we examined.
      $availability_ttl = min($availability_ttl, $max_range);

      $user->writeAvailabilityCache($availability, $availability_ttl);
      $user->attachAvailability($availability);
    }
  }

  private function fillUserCaches(array $users) {
    if (!$this->cacheKeys) {
      return;
    }

    $user_map = mpull($users, null, 'getPHID');
    $keys = array_keys($this->cacheKeys);

    $hashes = array();
    foreach ($keys as $key) {
      $hashes[] = PhabricatorHash::digestForIndex($key);
    }

    // First, pull any available caches. If we wanted to be particularly clever
    // we could do this with JOINs in the main query.

    $cache_table = new PhabricatorUserCache();
    $cache_conn = $cache_table->establishConnection('r');

    $cache_data = queryfx_all(
      $cache_conn,
      'SELECT cacheKey, userPHID, cacheData FROM %T
        WHERE cacheIndex IN (%Ls) AND userPHID IN (%Ls)',
      $cache_table->getTableName(),
      $hashes,
      array_keys($user_map));

    $need = array();

    $cache_data = igroup($cache_data, 'userPHID');
    foreach ($user_map as $user_phid => $user) {
      $raw_rows = idx($cache_data, $user_phid, array());
      if (!$raw_rows) {
        continue;
      }
      $raw_data = ipull($raw_rows, 'cacheData', 'cacheKey');

      foreach ($keys as $key) {
        if (isset($raw_data[$key]) || array_key_exists($key, $raw_data)) {
          continue;
        }
        $need[$key][$user_phid] = $user;
      }

      $user->attachRawCacheData($raw_data);
    }

    // If we missed any cache values, bulk-construct them now. This is
    // usually much cheaper than generating them on-demand for each user
    // record.

    if (!$need) {
      return;
    }

    $writes = array();
    foreach ($need as $cache_key => $need_users) {
      $type = PhabricatorUserCacheType::getCacheTypeForKey($cache_key);
      if (!$type) {
        continue;
      }

      $data = $type->newValueForUsers($cache_key, $need_users);

      foreach ($data as $user_phid => $value) {
        $raw_value = $type->getValueForStorage($value);
        $data[$user_phid] = $raw_value;
        $writes[] = array(
          'userPHID' => $user_phid,
          'key' => $cache_key,
          'type' => $type,
          'value' => $raw_value,
        );
      }

      foreach ($need_users as $user_phid => $user) {
        if (isset($data[$user_phid]) || array_key_exists($user_phid, $data)) {
          $user->attachRawCacheData(
            array(
              $cache_key => $data[$user_phid],
            ));
        }
      }
    }

    PhabricatorUserCache::writeCaches($writes);
  }
}
