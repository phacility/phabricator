<?php

final class ConpherenceTransactionType extends ConpherenceConstants {

  const TYPE_FILES           = 'files';
  const TYPE_TITLE           = 'title';
  const TYPE_PARTICIPANTS    = 'participants';
  const TYPE_DATE_MARKER     = 'date-marker';

  /* these two are deprecated but keep them around for legacy installs */
  const TYPE_PICTURE         = 'picture';
  const TYPE_PICTURE_CROP    = 'picture-crop';

}
