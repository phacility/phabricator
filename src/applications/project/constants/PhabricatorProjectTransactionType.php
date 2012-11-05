<?php

final class PhabricatorProjectTransactionType
  extends PhabricatorProjectConstants {

  const TYPE_NAME       = 'name';
  const TYPE_MEMBERS    = 'members';
  const TYPE_STATUS     = 'status';
  const TYPE_CAN_VIEW   = 'canview';
  const TYPE_CAN_EDIT   = 'canedit';
  const TYPE_CAN_JOIN   = 'canjoin';

}
