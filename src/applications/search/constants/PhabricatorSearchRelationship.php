<?php

final class PhabricatorSearchRelationship extends Phobject {

  const RELATIONSHIP_AUTHOR     = 'auth';
  const RELATIONSHIP_BOOK       = 'book';
  const RELATIONSHIP_REVIEWER   = 'revw';
  const RELATIONSHIP_SUBSCRIBER = 'subs';
  const RELATIONSHIP_COMMENTER  = 'comm';
  const RELATIONSHIP_OWNER      = 'ownr';
  const RELATIONSHIP_PROJECT    = 'proj';
  const RELATIONSHIP_REPOSITORY = 'repo';

  const RELATIONSHIP_OPEN       = 'open';
  const RELATIONSHIP_CLOSED     = 'clos';
  const RELATIONSHIP_UNOWNED    = 'unow';

}
