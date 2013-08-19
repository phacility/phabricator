<?php

/**
 * @group pholio
 */
final class PholioTransactionType extends PholioConstants {

  /* edits to the high level mock */
  const TYPE_NAME         = 'name';
  const TYPE_DESCRIPTION  = 'description';

  /* edits to images within the mock */
  const TYPE_IMAGE_FILE = 'image-file';
  const TYPE_IMAGE_NAME= 'image-name';
  const TYPE_IMAGE_DESCRIPTION = 'image-description';
  const TYPE_IMAGE_REPLACE = 'image-replace';
  const TYPE_IMAGE_SEQUENCE = 'image-sequence';

  /* your witty commentary at the mock : image : x,y level */
  const TYPE_INLINE  = 'inline';
}
