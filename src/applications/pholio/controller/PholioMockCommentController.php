<?php

/*
 * Copyright 2012 Facebook, Inc.
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

/**
 * @group pholio
 */
final class PholioMockCommentController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $mock_uri = '/M'.$mock->getID();

    $comment = $request->getStr('comment');
    if (!strlen($comment)) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Empty Comment'))
        ->appendChild('You did not provide a comment!')
        ->addCancelButton($mock_uri);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }


    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    // TODO: Move this to an Editor.

    $xaction = id(new PholioTransaction())
      // TODO: Formalize transaction types.
      ->setTransactionType('none')
      ->setAuthorPHID($user->getPHID())
      ->setComment($comment)
      ->setContentSource($content_source)
      ->setMockID($mock->getID());

    $xaction->save();

    return id(new AphrontRedirectResponse())->setURI($mock_uri);
  }

}
