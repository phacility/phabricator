<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_paste_Method extends ConduitAPIMethod {

  protected function buildPasteInfoDictionary(PhabricatorPaste $paste) {
    return array(
      'id'          => $paste->getID(),
      'objectName'  => 'P'.$paste->getID(),
      'phid'        => $paste->getPHID(),
      'authorPHID'  => $paste->getAuthorPHID(),
      'filePHID'    => $paste->getFilePHID(),
      'title'       => $paste->getTitle(),
      'dateCreated' => $paste->getDateCreated(),
      'language'    => $paste->getLanguage(),
      'uri'         => PhabricatorEnv::getProductionURI('/P'.$paste->getID()),
      'parentPHID'  => $paste->getParentPHID(),
      'content'     => $paste->getContent(),
    );
  }

}
