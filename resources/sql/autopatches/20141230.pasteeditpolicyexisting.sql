UPDATE `{$NAMESPACE}_pastebin`.`pastebin_paste` SET editPolicy = authorPHID
  WHERE editPolicy = '';
