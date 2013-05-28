<?php

/**
 * @group conpherence
 */
final class ConpherenceTransactionType extends ConpherenceConstants {

  const TYPE_FILES           = 'files';
  const TYPE_TITLE           = 'title';
  const TYPE_PARTICIPANTS    = 'participants';

  /* these two are deprecated but keep them around for legacy installs */
  const TYPE_PICTURE         = 'picture';
  const TYPE_PICTURE_CROP    = 'picture-crop';

}
