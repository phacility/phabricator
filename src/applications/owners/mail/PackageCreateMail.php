<?php

final class PackageCreateMail extends PackageMail {

  protected function isNewThread() {
    return true;
  }

  protected function getVerb() {
    return 'Created';
  }
}
