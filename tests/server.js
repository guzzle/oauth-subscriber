/**
 * Local test server that returns queued responses to HTTP requests and exposes
 * a small REST API for enqueueing responses and retrieving received requests.
 */

var http = require('http');
var url = require('url');

var GuzzleServer = function(port, log) {
  this.port = port;
  this.log = log;
  this.responses = [];
  this.requests = [];
  var that = this;

  var controlRequest = function(request, req, res) {
    if (req.url == '/guzzle-server/perf') {
      res.writeHead(200, 'OK', {'Content-Length': 16});
      res.end('Body of response');
    } else if (req.method == 'DELETE') {
      if (req.url == '/guzzle-server/requests') {
        that.requests = [];
        res.writeHead(200, 'OK', {'Content-Length': 0});
        res.end();
      } else if (req.url == '/guzzle-server') {
        res.writeHead(200, 'OK', {'Content-Length': 0, 'Connection': 'close'});
        res.end();
        that.server.close();
      }
    } else if (req.method == 'GET' && req.url == '/guzzle-server/requests') {
      var body = JSON.stringify(that.requests);
      res.writeHead(200, 'OK', {'Content-Length': body.length});
      res.end(body);
    } else if (req.method == 'PUT' && req.url == '/guzzle-server/responses') {
      if (!request.body) {
        res.writeHead(400, 'NO RESPONSES IN REQUEST', {'Content-Length': 0});
      } else {
        that.responses = JSON.parse(request.body);
        for (var i = 0; i < that.responses.length; i++) {
          if (that.responses[i].body) {
            that.responses[i].body = Buffer.from(that.responses[i].body, 'base64');
          }
        }
        res.writeHead(200, 'OK', {'Content-Length': 0});
      }
      res.end();
    }
  };

  var receivedRequest = function(request, req, res) {
    if (req.url.indexOf('/guzzle-server') === 0) {
      controlRequest(request, req, res);
    } else if (req.url.indexOf('/guzzle-server') == -1 && !that.responses.length) {
      res.writeHead(500);
      res.end('No responses in queue');
    } else {
      that.requests.push(request);
      var response = that.responses.shift();
      res.writeHead(response.status, response.reason, response.headers);
      res.end(response.body);
    }
  };

  this.start = function() {
    that.server = http.createServer(function(req, res) {
      var parts = url.parse(req.url, false);
      var request = {
        http_method: req.method,
        scheme: parts.scheme,
        uri: parts.pathname,
        query_string: parts.query,
        headers: req.headers,
        version: req.httpVersion,
        body: ''
      };

      req.addListener('data', function(chunk) {
        request.body += chunk;
      });

      req.addListener('end', function() {
        receivedRequest(request, req, res);
      });
    });

    that.server.listen(this.port, '127.0.0.1');

    if (this.log) {
      console.log('Server running at http://127.0.0.1:' + this.port + '/');
    }
  };
};

port = process.argv.length >= 3 ? process.argv[2] : 8126;
log = process.argv.length >= 4 ? process.argv[3] : false;

server = new GuzzleServer(port, log);
server.start();
