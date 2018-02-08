<?php

final class PhabricatorMailTarget extends Phobject {

  private $viewer;
  private $replyTo;
  private $toMap = array();
  private $ccMap = array();
  private $rawToPHIDs;
  private $rawCCPHIDs;

  public function setRawToPHIDs(array $to_phids) {
    $this->rawToPHIDs = $to_phids;
    return $this;
  }

  public function setRawCCPHIDs(array $cc_phids) {
    $this->rawCCPHIDs = $cc_phids;
    return $this;
  }

  public function setCCMap(array $cc_map) {
    $this->ccMap = $cc_map;
    return $this;
  }

  public function getCCMap() {
    return $this->ccMap;
  }

  public function setToMap(array $to_map) {
    $this->toMap = $to_map;
    return $this;
  }

  public function getToMap() {
    return $this->toMap;
  }

  public function setReplyTo($reply_to) {
    $this->replyTo = $reply_to;
    return $this;
  }

  public function getReplyTo() {
    return $this->replyTo;
  }

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function willSendMail(PhabricatorMetaMTAMail $mail) {
    $viewer = $this->getViewer();

    $show_stamps = $mail->shouldRenderMailStampsInBody($viewer);

    $body = $mail->getBody();
    $html_body = $mail->getHTMLBody();
    $has_html = (strlen($html_body) > 0);

    if ($show_stamps) {
      $stamps = $mail->getMailStamps();
      if ($stamps) {
        $body .= "\n";
        $body .= pht('STAMPS');
        $body .= "\n";
        $body .= implode(' ', $stamps);
        $body .= "\n";

        if ($has_html) {
          $html = array();
          $html[] = phutil_tag('strong', array(), pht('STAMPS'));
          $html[] = phutil_tag('br');
          $html[] = phutil_tag(
            'span',
            array(
              'style' => 'font-size: smaller; color: #92969D',
            ),
            phutil_implode_html(' ', $stamps));
          $html[] = phutil_tag('br');
          $html[] = phutil_tag('br');
          $html = phutil_tag('div', array(), $html);
          $html_body .= hsprintf('%s', $html);
        }
      }
    }

    $mail->addPHIDHeaders('X-Phabricator-To', $this->rawToPHIDs);
    $mail->addPHIDHeaders('X-Phabricator-Cc', $this->rawCCPHIDs);

    $to_handles = $viewer->loadHandles($this->rawToPHIDs);
    $cc_handles = $viewer->loadHandles($this->rawCCPHIDs);

    $body .= "\n";
    $body .= $this->getRecipientsSummary($to_handles, $cc_handles);

    if ($has_html) {
      $html_body .= hsprintf(
        '%s',
        $this->getRecipientsSummaryHTML($to_handles, $cc_handles));
    }

    $mail->setBody($body);
    $mail->setHTMLBody($html_body);

    $reply_to = $this->getReplyTo();
    if ($reply_to) {
      $mail->setReplyTo($reply_to);
    }

    $to = array_keys($this->getToMap());
    if ($to) {
      $mail->addTos($to);
    }

    $cc = array_keys($this->getCCMap());
    if ($cc) {
      $mail->addCCs($cc);
    }

    return $mail;
  }

  private function getRecipientsSummary(
    PhabricatorHandleList $to_handles,
    PhabricatorHandleList $cc_handles) {

    if (!PhabricatorEnv::getEnvConfig('metamta.recipients.show-hints')) {
      return '';
    }

    $to_handles = iterator_to_array($to_handles);
    $cc_handles = iterator_to_array($cc_handles);

    $body = '';

    if ($to_handles) {
      $to_names = mpull($to_handles, 'getCommandLineObjectName');
      $body .= "To: ".implode(', ', $to_names)."\n";
    }

    if ($cc_handles) {
      $cc_names = mpull($cc_handles, 'getCommandLineObjectName');
      $body .= "Cc: ".implode(', ', $cc_names)."\n";
    }

    return $body;
  }

  private function getRecipientsSummaryHTML(
    PhabricatorHandleList $to_handles,
    PhabricatorHandleList $cc_handles) {

    if (!PhabricatorEnv::getEnvConfig('metamta.recipients.show-hints')) {
      return '';
    }

    $to_handles = iterator_to_array($to_handles);
    $cc_handles = iterator_to_array($cc_handles);

    $body = array();
    if ($to_handles) {
      $body[] = phutil_tag('strong', array(), 'To: ');
      $body[] = phutil_implode_html(', ', mpull($to_handles, 'getName'));
      $body[] = phutil_tag('br');
    }
    if ($cc_handles) {
      $body[] = phutil_tag('strong', array(), 'Cc: ');
      $body[] = phutil_implode_html(', ', mpull($cc_handles, 'getName'));
      $body[] = phutil_tag('br');
    }
    return phutil_tag('div', array(), $body);
  }


}
