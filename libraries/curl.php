<?php
class Curl
{
  protected $_tmpDir = '';
  protected $_curlSession;
  protected $_cookieJar = '';

  public function __construct($tmpDir = '/tmp')
  {
    $this->_tmpDir = $tmpDir;
  }

  public function initCurl()
  {
    $this->_curlSession = curl_init();
  }

  public function execCurl()
  {
    return curl_exec($this->_curlSession);
  }

  public function getCurlCode()
  {
    return curl_getinfo($this->_curlSession, CURLINFO_HTTP_CODE);
  }

  public function closeCurl()
  {
    curl_close($this->_curlSession);
    unset($this->_curlSession);
  }

  public function setCookieJar($filename = null)
  {
    if ($filename !== null && is_writeable($this->_tmpDir . DIRECTORY_SEPARATOR . 'cookie' . $filename)) {
      $this->_cookieJar = $this->_tmpDir . DIRECTORY_SEPARATOR . 'cookie' . $filename;
    } else {
      $this->_cookieJar = tempnam($this->_tmpDir, 'cookie');
    }
    return substr(basename($this->_cookieJar), 6);
  }

  public function removeCookieJar($filename = null)
  {
    if ($filename !== null) {
      @unlink($this->_tmpDir . DIRECTORY_SEPARATOR . 'cookie' . $filename);
    } else {
      @unlink($this->_cookieJar);
    }
  }

  public function setCurlOption($opt)
  {
    if (isset($opt['useragent'])) {
      curl_setopt($this->_curlSession, CURLOPT_USERAGENT, $opt['useragent']);
    }
    if (isset($opt['header'])) {
      curl_setopt($this->_curlSession, CURLOPT_HEADER, $opt['header']);
    }
    if (isset($opt['nobody'])) {
      curl_setopt($this->_curlSession, CURLOPT_NOBODY, $opt['nobody']);
    }
    if (isset($opt['url'])) {
      curl_setopt($this->_curlSession, CURLOPT_URL, $opt['url']);
    }
    if (isset($opt['referer'])) {
      curl_setopt($this->_curlSession, CURLOPT_REFERER, $opt['referer']);
    }
    if (isset($opt['fields'])) {
      if (is_array($opt['fields'])) {
        $fields = '';
        foreach ($opt['fields'] as $key => &$val) {
          $fields .= '&' . urlencode($key) . '=' . urldecode($val);
        }
        $fields = substr($fields, 1);
      } else {
        $fields = $opt['fields'];
      }
      curl_setopt($this->_curlSession, CURLOPT_POSTFIELDS, $fields);
    }
    if (!empty($this->_cookieJar)) {
      curl_setopt($this->_curlSession, CURLOPT_COOKIEJAR, $this->_cookieJar);
      curl_setopt($this->_curlSession, CURLOPT_COOKIEFILE, basename($this->_cookieJar));
      curl_setopt($this->_curlSession, CURLOPT_COOKIE, 1);
    } else {
      curl_setopt($this->_curlSession, CURLOPT_COOKIE, 0);
    }
    curl_setopt($this->_curlSession, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->_curlSession, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($this->_curlSession, CURLOPT_SSL_VERIFYPEER, 0);
  }
}
