<?php
// Most of this code originates from http://www.gen-x-design.com/archives/create-a-rest-api-with-php/

// get our verb
$request_method = strtolower($_SERVER['REQUEST_METHOD']);
$_DATA = new RestRequest();
// we'll store our data here
$data = array();

switch ($request_method) {
  // gets are easy...
  case 'get':
    $data = $_GET;
    break;
  // so are posts
  case 'post':
    $data = $_POST;
    if(isset($_FILES) and is_array($_FILES)) {
      $data['files'] = $_FILES;
      foreach($_FILES as $field=>$file) {
        $data['files'][$field]['file_contents']=implode('', file($file['tmp_name']));
      }
    }
    break;
  // here's the tricky bit...
  case 'put':
  // basically, we read a string from PHP's special input location,
  // and then parse it out into an array via parse_str... per the PHP docs:
  // Parses str  as if it were the query string passed via a URL and sets
  // variables in the current scope.
    parse_str(file_get_contents('php://input'), $put_vars);
    $data = $put_vars;
    break;
}

// store the method
$_DATA->setMethod($request_method);

// set the raw data, so we can access it if needed (there may be
// other pieces to your requests)
$_DATA->setRequestVars($data);

if(isset($data['data'])) {
  // translate the JSON to an Object for use however you want
  $_DATA->setData(json_decode($data['data']));
}
$_DATA;

function sendArray($data = array(), $status = 200, $_self = null) {
  if(is_object($_self)) {
    echo $_self->getHttpAccept();
    if($_self->getHttpAccept() == 'json') {
      sendHttpResponse($status, json_encode($data), 'application/json');
    } elseif ($_self->getHttpAccept() == 'xml') {
      // using the XML_SERIALIZER Pear Package
      $options = array(
        'indent' => '     ',
        'addDecl' => false,
        'rootName' => 'RootNode',
        XML_SERIALIZER_OPTION_RETURN_RESULT => true
      );
      $serializer = new XML_Serializer($options);

      sendHttpResponse($status, $serializer->serialize($data), 'application/xml');
    }
  }
}

function sendHttpResponse($status = 200, $body = NULL, $content_type = 'text/html') {
  $status_header = 'HTTP/1.1 ' . $status . ' ' . getHttpStatusCodeMessage($status);

  if($body != '') {
    header($status_header);
    header('Content-type: ' . $content_type);
    // send the body
    echo $body;
    exit;
  } else {
    // create some body messages
    $message = '';

    // this is purely optional, but makes the pages a little nicer to read
    // for your users.  Since you won't likely send a lot of different status codes,
    // this also shouldn't be too ponderous to maintain
    switch($status) {
      case 401:
        header('WWW-Authenticate: Basic realm="Authentication Required"');
        $message = 'You must be authorized to view this page.';
        break;
      case 404:
        $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
        break;
      case 500:
        $message = 'The server encountered an error processing your request.';
        break;
      case 501:
        $message = 'The requested method is not implemented.';
        break;
    }

    header($status_header);
    header('Content-type: ' . $content_type);

    // this should be templatized in a real-world solution
    $body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <title>' . $status . ' ' . getHttpStatusCodeMessage($status) . '</title>
</head>
<body>
  <h1>' . getHttpStatusCodeMessage($status) . '</h1>
  <p>' . $message . '</p>
</body>
</html>';

    echo $body;
    exit;
  }
}

function getHttpStatusCodeMessage($status) {
  $codes = Array(
                 100 => 'Continue',
                 101 => 'Switching Protocols',
                 200 => 'OK',
                 201 => 'Created',
                 202 => 'Accepted',
                 203 => 'Non-Authoritative Information',
                 204 => 'No Content',
                 205 => 'Reset Content',
                 206 => 'Partial Content',
                 300 => 'Multiple Choices',
                 301 => 'Moved Permanently',
                 302 => 'Found',
                 303 => 'See Other',
                 304 => 'Not Modified',
                 305 => 'Use Proxy',
                 306 => '(Unused)',
                 307 => 'Temporary Redirect',
                 400 => 'Bad Request',
                 401 => 'Unauthorized',
                 402 => 'Payment Required',
                 403 => 'Forbidden',
                 404 => 'Not Found',
                 405 => 'Method Not Allowed',
                 406 => 'Not Acceptable',
                 407 => 'Proxy Authentication Required',
                 408 => 'Request Timeout',
                 409 => 'Conflict',
                 410 => 'Gone',
                 411 => 'Length Required',
                 412 => 'Precondition Failed',
                 413 => 'Request Entity Too Large',
                 414 => 'Request-URI Too Long',
                 415 => 'Unsupported Media Type',
                 416 => 'Requested Range Not Satisfiable',
                 417 => 'Expectation Failed',
                 500 => 'Internal Server Error',
                 501 => 'Not Implemented',
                 502 => 'Bad Gateway',
                 503 => 'Service Unavailable',
                 504 => 'Gateway Timeout',
                 505 => 'HTTP Version Not Supported'
  );
  return (isset($codes[$status])) ? $codes[$status] : '';
}

class RestRequest {
  private $request_vars = array();
  private $data = '';
  private $http_accept = '';
  private $method = '';
  private $request_path = array();
  private $auth_user = FALSE;
  private $auth_pass = FALSE;

  public function __construct() {
    $this->request_vars = array();
    $this->data = '';
    $this->http_accept = 'json';
    $this->method = 'get';

    // Break apart the PATH_INFO value and check incase we want to do something special!

    $request_path = explode('/', CampUtils::arrayGet($_SERVER, 'PATH_INFO', ''));
    $counter=0;
    foreach($request_path as $path_item) {
      $counter++;
      if(strlen($path_item) > 0) {
        if($counter == count($request_path)) {
          $parts = explode('.', $path_item);
          if(count($parts) > 1 AND CampUtils::arrayGet($parts, 1, FALSE, array('json', 'xml'))) {
            $this->request_path[] = $parts[0];
            $this->http_accept = $parts[1];
          } else {
            $this->request_path[] = $path_item;
          }
        } else {
          $this->request_path[] = $path_item;
        }
      }
    }

    // Now let's look for authentication - this code slightly mangled from the PHP manual site
    // http://php.net/manual/en/features.http-auth.php

    if(isset($_SERVER['HTTP_AUTHORIZATION'])) {
      $auth_params = explode(":" , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
      $this->auth_user = $auth_params[0];
      unset($auth_params[0]);
      $this->auth_pass = implode('',$auth_params);
    } elseif(isset($_SERVER['PHP_AUTH_USER'])) {
      $this->auth_user = $_SERVER['PHP_AUTH_USER'];
      $this->auth_pass = $_SERVER['PHP_AUTH_PW'];
    }
  }

  public function setData($data) {
    $this->data = $data;
  }

  public function setMethod($method) {
    $this->method = $method;
  }

  public function setRequestVars($request_vars) {
    $this->request_vars = $request_vars;
  }

  public function getAuth() {
    return array('user'=>$this->auth_user, 'pass'=>$this->auth_pass);
  }

  public function getData() {
    return $this->data;
  }

  public function getMethod() {
    return $this->method;
  }

  public function getHttpAccept() {
    return $this->http_accept;
  }

  public function getRequestVars() {
    return $this->request_vars;
  }

  public function getRequestPath() {
    return $this->request_path;
  }
}

// If your function requires authentication, this will help
function requireAuth($parsed_data) {
  if(!CampUtils::arrayGet($parsed_data->getAuth(), 'user', FALSE)) {
    sendHttpResponse(401);
  }
}
