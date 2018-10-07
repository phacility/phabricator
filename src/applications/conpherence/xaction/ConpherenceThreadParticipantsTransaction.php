<?php

final class ConpherenceThreadParticipantsTransaction
  extends ConpherenceThreadTransactionType {

  const TRANSACTIONTYPE = 'participants';

  public function generateOldValue($object) {
    return $object->getParticipantPHIDs();
  }

  public function generateNewValue($object, $value) {
    $old = $this->generateOldValue($object);
    return $this->getPHIDList($old, $value);
  }

  public function applyExternalEffects($object, $value) {
    $participants = $object->getParticipants();

    $old = array_keys($participants);
    $new = $value;

    $add_map = array_fuse(array_diff($new, $old));
    $rem_map = array_fuse(array_diff($old, $new));

    foreach ($rem_map as $phid) {
      $remove_participant = $participants[$phid];
      $remove_participant->delete();
      unset($participants[$phid]);
    }

    foreach ($add_map as $phid) {
      if (isset($participants[$phid])) {
        continue;
      }

      $participants[$phid] = id(new ConpherenceParticipant())
        ->setConpherencePHID($object->getPHID())
        ->setParticipantPHID($phid)
        ->setSeenMessageCount(0)
        ->save();
    }

    $object->attachParticipants($participants);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    $author_phid = $this->getAuthorPHID();

    if ($add && $rem) {
      return pht(
        '%s edited participant(s), added %d: %s; removed %d: %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderHandleList($add),
        count($rem),
        $this->renderHandleList($rem));
    } else if ((in_array($author_phid, $add)) && (count($add) == 1)) {
      return pht(
        '%s joined the room.',
        $this->renderAuthor());
    } else if ((in_array($author_phid, $rem)) && (count($rem) == 1)) {
      return pht(
        '%s left the room.',
        $this->renderAuthor());
    } else if ($add) {
      return pht(
        '%s added %d participant(s): %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderHandleList($add));
    } else {
      return pht(
        '%s removed %d participant(s): %s.',
        $this->renderAuthor(),
        count($rem),
        $this->renderHandleList($rem));
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $old = $object->getParticipantPHIDs();

      $new = $xaction->getNewValue();
      $new = $this->getPHIDList($old, $new);

      $add_map = array_fuse(array_diff($new, $old));
      $rem_map = array_fuse(array_diff($old, $new));

      foreach ($add_map as $user_phid) {
        $user = id(new PhabricatorPeopleQuery())
          ->setViewer($this->getActor())
          ->withPHIDs(array($user_phid))
          ->executeOne();
        if (!$user) {
          $errors[] = $this->newInvalidError(
            pht(
              'Participant PHID "%s" is not a valid user PHID.',
              $user_phid));
          continue;
        }
      }
    }

    return $errors;
  }

  public function getRequiredCapabilities(
    $object,
    PhabricatorApplicationTransaction $xaction) {

    $old_map = array_fuse($xaction->getOldValue());
    $new_map = array_fuse($xaction->getNewValue());

    $add = array_keys(array_diff_key($new_map, $old_map));
    $rem = array_keys(array_diff_key($old_map, $new_map));

    $actor_phid = $this->getActingAsPHID();

    $is_join = (($add === array($actor_phid)) && !$rem);
    $is_leave = (($rem === array($actor_phid)) && !$add);

    if ($is_join) {
      // Anyone can join a thread they can see.
      return null;
    }

    if ($is_leave) {
      // Anyone can leave a thread.
      return null;
    }

    // You need CAN_EDIT to add or remove participants. For additional
    // discussion, see D17696 and T4411.
    return PhabricatorPolicyCapability::CAN_EDIT;
  }

}
