<?php

final class MetaMTANotificationType
  extends MetaMTAConstants {

  const TYPE_DIFFERENTIAL_REVIEWERS      = 'differential-reviewers';
  const TYPE_DIFFERENTIAL_CLOSED         = 'differential-committed';
  const TYPE_DIFFERENTIAL_CC             = 'differential-cc';
  const TYPE_DIFFERENTIAL_COMMENT        = 'differential-comment';
  const TYPE_DIFFERENTIAL_UPDATED        = 'differential-updated';
  const TYPE_DIFFERENTIAL_REVIEW_REQUEST = 'differential-review-request';
  const TYPE_DIFFERENTIAL_OTHER          = 'differential-other';

  const TYPE_MANIPHEST_STATUS         = 'maniphest-status';
  const TYPE_MANIPHEST_OWNER          = 'maniphest-owner';
  const TYPE_MANIPHEST_PRIORITY       = 'maniphest-priority';
  const TYPE_MANIPHEST_CC             = 'maniphest-cc';
  const TYPE_MANIPHEST_PROJECTS       = 'maniphest-projects';
  const TYPE_MANIPHEST_COMMENT        = 'maniphest-comment';
  const TYPE_MANIPHEST_OTHER          = 'maniphest-other';

}
