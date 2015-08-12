<?php

final class PhabricatorMetaMTAMailViewController
  extends PhabricatorMetaMTAController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $mail = id(new PhabricatorMetaMTAMailQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$mail) {
      return new Aphront404Response();
    }

    if ($mail->hasSensitiveContent()) {
      $title = pht('Content Redacted');
    } else {
      $title = $mail->getSubject();
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($mail);

    switch ($mail->getStatus()) {
      case PhabricatorMetaMTAMail::STATUS_QUEUE:
        $icon = 'fa-clock-o';
        $color = 'blue';
        $name = pht('Queued');
        break;
      case PhabricatorMetaMTAMail::STATUS_SENT:
        $icon = 'fa-envelope';
        $color = 'green';
        $name = pht('Sent');
        break;
      case PhabricatorMetaMTAMail::STATUS_FAIL:
        $icon = 'fa-envelope';
        $color = 'red';
        $name = pht('Delivery Failed');
        break;
      case PhabricatorMetaMTAMail::STATUS_VOID:
        $icon = 'fa-envelope';
        $color = 'black';
        $name = pht('Voided');
        break;
      default:
        $icon = 'fa-question-circle';
        $color = 'yellow';
        $name = pht('Unknown');
        break;
    }

    $header->setStatus($icon, $color, $name);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Mail %d', $mail->getID()));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($this->buildMessageProperties($mail), pht('Message'))
      ->addPropertyList($this->buildHeaderProperties($mail), pht('Headers'))
      ->addPropertyList($this->buildDeliveryProperties($mail), pht('Delivery'))
      ->addPropertyList($this->buildMetadataProperties($mail), pht('Metadata'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
        'pageObjects' => array($mail->getPHID()),
      ));
  }

  private function buildMessageProperties(PhabricatorMetaMTAMail $mail) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($mail);

    if ($mail->getFrom()) {
      $from_str = $viewer->renderHandle($mail->getFrom());
    } else {
      $from_str = pht('Sent by Phabricator');
    }
    $properties->addProperty(
      pht('From'),
      $from_str);

    if ($mail->getToPHIDs()) {
      $to_list = $viewer->renderHandleList($mail->getToPHIDs());
    } else {
      $to_list = pht('None');
    }
    $properties->addProperty(
      pht('To'),
      $to_list);

    if ($mail->getCcPHIDs()) {
      $cc_list = $viewer->renderHandleList($mail->getCcPHIDs());
    } else {
      $cc_list = pht('None');
    }
    $properties->addProperty(
      pht('Cc'),
      $cc_list);

    $properties->addSectionHeader(
      pht('Message'),
      PHUIPropertyListView::ICON_SUMMARY);

    if ($mail->hasSensitiveContent()) {
      $body = phutil_tag(
        'em',
        array(),
        pht(
          'The content of this mail is sensitive and it can not be '.
          'viewed from the web UI.'));
    } else {
      $body = phutil_tag(
        'div',
        array(
          'style' => 'white-space: pre-wrap',
        ),
        $mail->getBody());
    }

    $properties->addTextContent($body);


    return $properties;
  }

  private function buildHeaderProperties(PhabricatorMetaMTAMail $mail) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setStacked(true);

    $headers = $mail->getDeliveredHeaders();
    if ($headers === null) {
      $headers = $mail->generateHeaders();
    }

    // Sort headers by name.
    $headers = isort($headers, 0);

    foreach ($headers as $header) {
      list($key, $value) = $header;
      $properties->addProperty($key, $value);
    }

    return $properties;
  }

  private function buildDeliveryProperties(PhabricatorMetaMTAMail $mail) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $actors = $mail->getDeliveredActors();
    $reasons = null;
    if (!$actors) {
      // TODO: We can get rid of this special-cased message after these changes
      // have been live for a while, but provide a more tailored message for
      // now so things are a little less confusing for users.
      if ($mail->getStatus() == PhabricatorMetaMTAMail::STATUS_SENT) {
        $delivery = phutil_tag(
          'em',
          array(),
          pht(
            'This is an older message that predates recording delivery '.
            'information, so none is available.'));
      } else {
        $delivery = phutil_tag(
          'em',
          array(),
          pht(
            'This message has not been delivered yet, so delivery information '.
            'is not available.'));
      }
    } else {
      $actor = idx($actors, $viewer->getPHID());
      if (!$actor) {
        $delivery = phutil_tag(
          'em',
          array(),
          pht('This message was not delivered to you.'));
      } else {
        $deliverable = $actor['deliverable'];
        if ($deliverable) {
          $delivery = pht('Delivered');
        } else {
          $delivery = pht('Voided');
        }

        $reasons = id(new PHUIStatusListView());

        $reason_codes = $actor['reasons'];
        if (!$reason_codes) {
          $reason_codes = array(
            PhabricatorMetaMTAActor::REASON_NONE,
          );
        }

        $icon_yes = 'fa-check green';
        $icon_no = 'fa-times red';

        foreach ($reason_codes as $reason) {
          $target = phutil_tag(
            'strong',
            array(),
            PhabricatorMetaMTAActor::getReasonName($reason));

          if (PhabricatorMetaMTAActor::isDeliveryReason($reason)) {
            $icon = $icon_yes;
          } else {
            $icon = $icon_no;
          }

          $item = id(new PHUIStatusItemView())
            ->setIcon($icon)
            ->setTarget($target)
            ->setNote(PhabricatorMetaMTAActor::getReasonDescription($reason));

          $reasons->addItem($item);
        }
      }
    }

    $properties->addProperty(pht('Delivery'), $delivery);
    if ($reasons) {
      $properties->addProperty(pht('Reasons'), $reasons);
    }

    return $properties;
  }

  private function buildMetadataProperties(PhabricatorMetaMTAMail $mail) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $details = $mail->getMessage();
    if (!strlen($details)) {
      $details = phutil_tag('em', array(), pht('None'));
    }
    $properties->addProperty(pht('Status Details'), $details);

    $actor_phid = $mail->getActorPHID();
    if ($actor_phid) {
      $actor_str = $viewer->renderHandle($actor_phid);
    } else {
      $actor_str = pht('Generated by Phabricator');
    }
    $properties->addProperty(pht('Actor'), $actor_str);

    $related_phid = $mail->getRelatedPHID();
    if ($related_phid) {
      $related = $viewer->renderHandle($mail->getRelatedPHID());
    } else {
      $related = phutil_tag('em', array(), pht('None'));
    }
    $properties->addProperty(pht('Related Object'), $related);

    return $properties;
  }

}
