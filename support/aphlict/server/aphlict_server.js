var net = require('net');

function getFlashPolicy() {
  return [
    '<?xml version="1.0"?>',
    '<!DOCTYPE cross-domain-policy SYSTEM ' +
      '"http://www.macromedia.com/xml/dtds/cross-domain-policy.dtd">',
    '<cross-domain-policy>',
    '<allow-access-from domain="*" to-ports="2600"/>',
    '</cross-domain-policy>'
  ].join("\n");
}

net.createServer(function(socket) {
  socket.on('data', function() {
    socket.write(getFlashPolicy() + '\0');
  });
}).listen(843);

var sp_server = net.createServer(function(socket) {
  function xwrite() {
    var data = {hi: "hello"};
    var serial = JSON.stringify(data);

    var length = Buffer.byteLength(serial, 'utf8');
    length = length.toString();
    while (length.length < 8) {
      length = "0" + length;
    }

    socket.write(length + serial);

    console.log('write : ' + length + serial);
  }

  socket.on('connect', function() {

    xwrite();
    setInterval(xwrite, 1000);

  });
}).listen(2600);
