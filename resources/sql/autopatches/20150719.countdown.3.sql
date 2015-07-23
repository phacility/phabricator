UPDATE {$NAMESPACE}_countdown.countdown
  SET editPolicy = authorPHID WHERE editPolicy = '';
