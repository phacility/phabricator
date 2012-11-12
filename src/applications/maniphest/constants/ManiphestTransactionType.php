<?php

/**
 * @group maniphest
 */
final class ManiphestTransactionType extends ManiphestConstants {

  const TYPE_NONE         = 'comment';
  const TYPE_STATUS       = 'status';
  const TYPE_OWNER        = 'reassign';
  const TYPE_CCS          = 'ccs';
  const TYPE_PROJECTS     = 'projects';
  const TYPE_PRIORITY     = 'priority';

  const TYPE_ATTACH       = 'attach';
  const TYPE_EDGE         = 'edge';

  const TYPE_TITLE        = 'title';
  const TYPE_DESCRIPTION  = 'description';
  const TYPE_AUXILIARY    = 'aux';

  public static function getTransactionTypeMap() {
    return array(
      self::TYPE_NONE       => 'Comment',
      self::TYPE_STATUS     => 'Close Task',
      self::TYPE_OWNER      => 'Reassign / Claim',
      self::TYPE_CCS        => 'Add CCs',
      self::TYPE_PRIORITY   => 'Change Priority',
      self::TYPE_ATTACH     => 'Upload File',
      self::TYPE_PROJECTS   => 'Associate Projects',
    );
  }

}
