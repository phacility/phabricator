<?php

final class ConpherenceCreateThreadConduitAPIMethod
  extends ConpherenceConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conpherence.createthread';
  }

  public function getMethodDescription() {
    return pht('Create a new conpherence thread.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "conpherence.edit" instead.');
  }

  protected function defineParamTypes() {
    return array(
      'title' => 'required string',
      'topic' => 'optional string',
      'message' => 'optional string',
      'participantPHIDs' => 'required list<phids>',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_EMPTY_PARTICIPANT_PHIDS' => pht(
        'You must specify participant phids.'),
      'ERR_EMPTY_TITLE' => pht(
        'You must specify a title.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $participant_phids = $request->getValue('participantPHIDs', array());
    $message = $request->getValue('message');
    $title = $request->getValue('title');
    $topic = $request->getValue('topic');

    list($errors, $conpherence) = ConpherenceEditor::createThread(
      $request->getUser(),
      $participant_phids,
      $title,
      $message,
      $request->newContentSource(),
      $topic);

    if ($errors) {
      foreach ($errors as $error_code) {
        switch ($error_code) {
          case ConpherenceEditor::ERROR_EMPTY_PARTICIPANTS:
            throw new ConduitException('ERR_EMPTY_PARTICIPANT_PHIDS');
            break;
        }
      }
    }

    return array(
      'conpherenceID' => $conpherence->getID(),
      'conpherencePHID' => $conpherence->getPHID(),
      'conpherenceURI' => $this->getConpherenceURI($conpherence),
    );
  }

}
