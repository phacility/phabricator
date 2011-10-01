#!/usr/bin/php
<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

if ($argc > 1) {
  $_SERVER['PHABRICATOR_ENV'] = $argv[1];
}

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';
require_once $root.'/externals/mimemailparser/MimeMailParser.class.php';

phutil_require_module(
  'phabricator',
  'applications/metamta/storage/receivedmail');
phutil_require_module(
  'phabricator',
  'applications/files/storage/file');

$parser = new MimeMailParser();
$parser->setText(file_get_contents('php://stdin'));

$received = new PhabricatorMetaMTAReceivedMail();
$received->setHeaders($parser->getHeaders());
$received->setBodies(array(
  'text' => $parser->getMessageBody('text'),
  'html' => $parser->getMessageBody('html'),
));

$attachments = array();
foreach ($parser->getAttachments() as $attachment) {
  if (preg_match('@text/(plain|html)@', $attachment->getContentType()) &&
      $attachment->getContentDisposition() == 'inline') {
    // If this is an "inline" attachment with some sort of text content-type,
    // do not treat it as a file for attachment. MimeMailParser already picked
    // it up in the getMessageBody() call above. We still want to treat 'inline'
    // attachments with other content types (e.g., images) as attachments.
    continue;
  }

  $file = PhabricatorFile::newFromFileData(
    $attachment->getContent(),
    array(
      'name' => $attachment->getFilename(),
    ));
  $attachments[] = $file->getPHID();
}
$received->setAttachments($attachments);
$received->save();

$received->processReceivedMail();
