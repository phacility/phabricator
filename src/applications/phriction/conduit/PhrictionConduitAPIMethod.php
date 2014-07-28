<?php

abstract class PhrictionConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorPhrictionApplication');
  }

  final protected function buildDocumentInfoDictionary(PhrictionDocument $doc) {
    $content = $doc->getContent();
    return $this->buildDocumentContentDictionary($doc, $content);
  }

  final protected function buildDocumentContentDictionary(
    PhrictionDocument $doc,
    PhrictionContent $content) {

    $uri = PhrictionDocument::getSlugURI($content->getSlug());
    $uri = PhabricatorEnv::getProductionURI($uri);

    $doc_status = $doc->getStatus();

    return array(
      'phid'        => $doc->getPHID(),
      'uri'         => $uri,
      'slug'        => $content->getSlug(),
      'version'     => $content->getVersion(),
      'authorPHID'  => $content->getAuthorPHID(),
      'title'       => $content->getTitle(),
      'content'     => $content->getContent(),
      'status'      => PhrictionDocumentStatus::getConduitConstant($doc_status),
      'description' => $content->getDescription(),
      'dateCreated' => $content->getDateCreated(),
    );
  }

}
