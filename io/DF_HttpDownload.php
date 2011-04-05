<?php
/*  Copyright 2009, ontoprise GmbH
 *
 *   The deployment tool is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   The deployment tool is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('PROGRESS_BAR_LENGTH', 40);

/**
 * @file
 * @ingroup DFIO
 *
 * HTTP Downloader implementation.
 *
 * @author Kai K�hn / Ontoprise / 2009
 *
 */
class HttpDownload {
	private $header;

	private $proxy_addr="";
	private $proxy_port="8080"; //use Port 8080 as default for proxy servers

	public function __construct() {
		$this->setProxy();
	}

	/**
	 * Downloads a resource via HTTP protocol and stores it into a file.
	 *
	 * @param URL $url
	 * @param string $filename
	 * @param object $callback: An object with 2 methods:
	 *                     downloadProgress($percentage).
	 *                     downloadFinished($filename)
	 */
	public function downloadAsFileByURL($url, $filename, $credentials = "", $callback = NULL) {
		$partsOfURL = parse_url($url);

		$path = $partsOfURL['path'];
		$host = $partsOfURL['host'];
		$port = array_key_exists("port", $partsOfURL) ? $partsOfURL['port'] : 80;
		$this->downloadAsFile($path, $port, $host, $filename, $credentials, $callback);
	}

	/**
	 * Downloads a resource via HTTP protocol and stores it into a file.
	 *
	 * @param string $path
	 * @param int $port
	 * @param string $host
	 * @param string $filename (may contain path)
	 * @param object $callback: An object with 2 methods:
	 *                     downloadProgress($percentage).
	 *                     downloadFinished($filename)
	 *      If null, an internal rendering method uses the console to show a progres bar and a finish message.
	 */
	public function downloadAsFile($path, $port, $host, $filename, $credentials = "", $callback = NULL) {

		$credentials = trim($credentials);
		if ($credentials == ':') $credentials = ''; // make sure the credentials are not empty by accident
		$address = gethostbyname($host);
		$handle = fopen($filename, "wb");
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($socket === false) throw new Exception("Could not connect to $host");
		$this->useProxy($path, $port, $address);
		$connect_status = socket_connect($socket, $address, $port);
		if ($connect_status === false) throw new Exception("Could not connect to $host");
		$in = "GET $path HTTP/1.0\r\n";
		$in .= "Host: $host\r\n";
		if ($credentials != '') $in .= "Authorization: Basic ".base64_encode(trim($credentials))."\r\n";
		$in .= "\r\n";
		socket_write($socket, $in, strlen($in));
		$this->headerFound = false;
		$this->header = "";
		$length = 0;
		$cb = is_null($callback) ? $this : $callback;
		call_user_func(array($cb,"downloadStart"), basename(dirname($path))."/".basename($path));
		$out = socket_read($socket, 2048);

		do {

			if (! $this->headerFound) {
				$this->header .= $out;
				$index = strpos($this->header, "\r\n\r\n");

				if ($index !== false) {

					$out = substr($this->header, $index+4);
					$length = strlen($out);
					$this->header = substr($this->header, 0, $index);
					$this->checkHeader($path);
					$contentLength = $this->getContentLength();
					$this->headerFound = true;
				} else {

					continue;
				}
			}
			$length += strlen($out);
			fwrite($handle, $out);

			call_user_func(array($cb,"downloadProgress"), $length,$contentLength);

		}  while ($out = socket_read($socket, 2048));

		call_user_func(array($cb,"downloadProgress"), 100,100);

		call_user_func(array($cb,"downloadFinished"), $filename);
		fclose($handle);
		socket_close($socket);
	}

	/**
	 * Downloads a resource via HTTP protocol returns the content as string.
	 *
	 * @param string $path
	 * @param int $port
	 * @param string $host
	 * @param object $callback: An object with 2 methods:
	 *                     downloadProgres($percentage).
	 *                     downloadFinished($filename)
	 *      If null, an internal rendering method uses the console to show a progres bar and a finish message.
	 * @return string
	 */
	public function downloadAsString($path, $port, $host, $credentials = "", $callback = NULL) {

		$credentials = trim($credentials);
		if ($credentials == ':') $credentials = ''; // make sure the credentials are not empty by accident
		$address = gethostbyname($host);
		$res = "";
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($socket === false) throw new Exception("Could not connect to $host");
		$this->useProxy($path, $port, $address);
		$connect_status = socket_connect($socket, $address, $port);
		if ($connect_status === false) throw new Exception("Could not connect to $host");
		$in = "GET $path HTTP/1.0\r\n";
		$in .= "Host: $host\r\n";
		if ($credentials != '') $in .= "Authorization: Basic ".base64_encode(trim($credentials))."\r\n";
		$in .= "\r\n";
		socket_write($socket, $in, strlen($in));
		$this->headerFound = false;
		$this->header = "";
		$length = 0;
		$cb = is_null($callback) ? $this : $callback;
		call_user_func(array($cb,"downloadStart"), basename(dirname($path))."/".basename($path));
		$out = socket_read($socket, 2048);

		do {
			if (! $this->headerFound) {
				$this->header .= $out;
				$index = strpos($this->header, "\r\n\r\n");

				if ($index !== false) {

					$out = substr($this->header, $index+4);
					$length = strlen($out);
					$this->header = substr($this->header, 0, $index);
					$this->checkHeader($path);
					$contentLength = $this->getContentLength();

					$this->headerFound = true;


				} else {


					continue;
				}
			}
			$length += strlen($out);
			$res .= $out;
			call_user_func(array($cb,'downloadProgress'), $length, $contentLength);

		} while ($out = socket_read($socket, 2048));


		call_user_func(array($cb,"downloadProgress"), 100,100);

		call_user_func(array($cb,"downloadFinished"), NULL);

		socket_close($socket);
		return $res;
	}


	private function checkHeader($path) {
		preg_match('/HTTP\/\d.\d\s+(\d+)/', $this->header, $matches);
		if (!isset($matches[1])) throw new HttpError("Invalid HTTP header");
		switch($matches[1]) {
			case 200: break; // OK
			case 400: throw new HttpError("Bad request: $path", 400, $this->header);
			case 401: throw new HttpError("Authorization required: $path", 401, $this->header);
			case 403: throw new HttpError("Access denied: $path", 403, $this->header);
			case 404: throw new HttpError("File not found: $path", 404, $this->header);
			default: throw new HttpError("Unknown HTTP error", $matches[1], $this->header);
		}
			

	}


	/**
	 * Checks if a proxy is given and modifies the parameters for the http-request accordingly
	 *
	 * @param string $path
	 * @param string $port
	 * @param string $host
	 */
	private function useProxy(&$path, &$port, &$host){
		//Is the proxy set?
		if($this->proxy_addr != "" && $this->proxy_port != ""){
			//use the full url of the resource for the HTTP GET
			$path = "http://".$host.":".$port.$path;
			//use the proxy address and port for socket connect
			$host = $this->proxy_addr;
			$port = $this->proxy_port;
			return true;
		}
		else {
			//No proxy is set, so don't change path, port and host
			return false;
		}
	}

	/**
	 *
	 */
	private function setProxy(){
		
		if (!class_exists("DF_Config")) return;
		//Read settings for the proxy
		$proxy_url = DF_Config::getValue('df_proxy');
		//don't change anything if no proxy is set in the config
		if($proxy_url=="" || $proxy_url ==null) return;

		//get proxy port and host
		$partsOfURL = parse_url($proxy_url);
		//get proxy ip to connect to
		$proxy_server = gethostbyname($partsOfURL['host']);
		//get port from settings
		$proxy_port = $partsOfURL['port'];
		//set port to default 8080 if not specified
		if( $proxy_port == "" || $proxy_port == null ) $proxy_port = "8080";

		//Set the proxy address and the port for Download
		$this->proxy_addr = $proxy_server;
		$this->proxy_port = $proxy_port;
	}

	/**
	 * Parser content length from HTTP header
	 *
	 * @return int
	 */
	private function getContentLength() {
		preg_match("/Content-Length:\\s*(\\d+)/", $this->header, $matches);
		//if (!isset($matches[1])) throw new HttpError("Content-Length not set", 0, $this->header);
		if (!isset($matches[1])) return 0; //Return 0 if contentlength is not set, e.g. filtert by a proxy
		if (!is_numeric($matches[1])) throw new HttpError("Content-Length not numeric", 0, $this->header);
		return intval($matches[1]);
	}

	/**
	 * Shows a progres bar on the console
	 *
	 * @param float $per
	 */
	public function downloadProgress($length, $contentLength = 0) {
		static $first = true;
		static $lastLength = 0;
		if (!$first) for($i = 0; $i < $lastLength; $i++) echo chr(8);
		$first = false;
		if($contentLength != 0){
			$per = $length / $contentLength;
			$per > 1 ? $per = 1 : $per;
			$prg = intval(round($per,2)*PROGRESS_BAR_LENGTH);
			$done = "";
			$left = "";
			for($i = 0; $i < $prg; $i++) $done .= "=";
			for($i = $prg; $i < PROGRESS_BAR_LENGTH; $i++) $left .= " ";
			$per100 = intval(round($per,2)*100);
			$show = "[$done$left] $per100%";
			echo $show;
			$lastLength = strlen($show);
		} else {
			echo "Downloaded: ".$length;
			$lastLength = strlen("Downloaded: ".$length);
		}

	}

	public function downloadStart($filename) {
		if (!is_null($filename)) echo "\nDownloading $filename...\n";
	}

	public function downloadFinished($filename) {
		//echo "\n";
		//if (!is_null($filename)) echo "\n$filename was downloaded.";
	}
}

class HttpError extends Exception {
	var $errcode;
	var $msg;
	var $httpHeader;

	public function __construct($msg, $errcode, $httpHeader) {
		$this->msg = $msg;
		$this->errcode = $errcode;
		$this->httpHeader = $httpHeader;
	}

	public function getMsg() {
		return $this->msg;
	}

	public function getErrorCode() {
		return $this->errcode;
	}

	public function getHeader() {
		return $this->httpHeader;
	}
}



