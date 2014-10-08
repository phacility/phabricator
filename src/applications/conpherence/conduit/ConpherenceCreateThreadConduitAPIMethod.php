<?php

final class ConpherenceCreateThreadConduitAPIMethod
  extends ConpherenceConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conpherence.createthread';
  }

  public function getMethodDescription() {
    return pht('Create a new conpherence thread.');
  }

  public function defineParamTypes() {
    return array(
      'title' => 'optional string',
      'message' => 'required string',
      'participantPHIDs' => 'required list<phids>',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_EMPTY_PARTICIPANT_PHIDS' => pht(
        'You must specify participant phids.'),
      'ERR_EMPTY_MESSAGE' => pht(
        'You must specify a message.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $participant_phids = $request->getValue('participantPHIDs', array());
    $message = $request->getValue('message');
    $title = $request->getValue('title');

    list($errors, $conpherence) = ConpherenceEditor::createConpherence(
      $request->getUser(),
      $participant_phids,
      $title,
      $message,
      PhabricatorContentSource::newFromConduitRequest($request));

    if ($errors) {
      foreach ($errors as $error_code) {
        switch ($error_code) {
          case ConpherenceEditor::ERROR_EMPTY_MESSAGE:
            throw new ConduitException('ERR_EMPTY_MESSAGE');
            break;
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
