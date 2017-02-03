<?php
/*
 * acme_serverconnectors.inc
 * 
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace pfsense_pkg\acme;

class FTPConnection
{
    private $connection;
    private $sftp;
	private $scheme;

    public function __construct($url)
    {
		$this->scheme = parse_url($url, PHP_URL_SCHEME);
		$host = parse_url($url, PHP_URL_HOST);
		$port = parse_url($url, PHP_URL_PORT);
		if ($port == 0) {
			if ($this->scheme == "ftp") {
				$port = 21;
			} elseif ($this->scheme == "sftp") {
				$port = 22;
			} elseif ($this->scheme == "ftps") {
				$port = 21;
			}
		}
		if ($this->scheme == "ftp") {
			$this->connection = ftp_connect($host, $port);
		} elseif ($this->scheme == "sftp") {
			$this->connection = ssh2_connect($host, $port);
		} elseif ($this->scheme == "ftps") {
			$this->connection = \ftp_ssl_connect($host, $port);
		}
        if (! $this->connection) {
			throw new \Exception("Could not connect with {$this->scheme} to {$host} on port {$port}.");
		}
    }

    public function login($username, $password)
    {
		if ($this->scheme == "ftp" || $this->scheme == "ftps") {
			if(!ftp_login($this->connection, $username, $password)){
				throw new \Exception("Could not authenticate with username {$username} and its password");
			}
		} else {
	        if (!ssh2_auth_password($this->connection, $username, $password)) {
				throw new \Exception("Could not authenticate with username {$username} and its password");
			}
			$this->sftp = ssh2_sftp($this->connection);
			if (! $this->sftp) {
				throw new \Exception("Could not initialize SFTP subsystem.");
			}
		}
		if ($this->scheme == "ftp" || $this->scheme == "ftps") {
			if (!ftp_pasv($this->connection, true)) {//passive connection is usualy desired to pass through firewalls..
				throw new \Exception("Could not switch to PASSIVE ftp for: {$this->scheme} to {$host} on port {$port}.");
			}
		}
    }
	
    public function uploadData($data_to_send, $remote_file) {
		if ($this->scheme == "ftp" || $this->scheme == "ftps") {
			echo "\n upload:{$data_to_send}  tofile: {$remote_file}";
			$tempHandle = fopen('php://temp', 'r+');
			fwrite($tempHandle, $data_to_send);
			rewind($tempHandle);
			if (!ftp_fput($this->connection, $remote_file, $tempHandle, FTP_ASCII)) {
				throw new \Exception("Could not upload file: {$remote_file}");
			}
			fclose($tempHandle);
		} else {
			$sftp = $this->sftp;
			$stream = @fopen("ssh2.sftp://{$sftp}{$remote_file}", 'w');
			if (! $stream) {
				throw new \Exception("Could not open file: {$remote_file}");
			}
			if (fwrite($stream, $data_to_send) === false) {
				throw new \Exception("Could not send data to file: $remote_file.");
			}
			fclose($stream);
		}
	}
	
	public function deleteFile($remote_file) {
		if ($this->scheme == "ftp" || $this->scheme == "ftps") {
			if (!ftp_delete($this->connection , $remote_file)) {
				throw new \Exception("Could not delete file: $remote_file.");
			}
		} else {
			$sftp = $this->sftp;
			ssh2_sftp_unlink($sftp, $remote_file);
		}
	}

	public function mkdir($remote_dir, $recursive = true) {
		if ($this->scheme == "ftp" || $this->scheme == "ftps") {
			if ($remote_dir[0] == "/") {
				$remote_dir = substr($remote_dir, 1);
			}
			if ($remote_dir[strlen($remote_dir)-1] == "/") {
				$remote_dir = substr($remote_dir, 0, strlen($remote_dir)-1);
			}

			$parts = explode('/', $remote_dir);
			foreach($parts as $part){
				if(!@ftp_chdir($this->connection, $part)) {
					ftp_mkdir($this->connection, $part);
					ftp_chdir($this->connection, $part);
					//ftp_chmod($ftpcon, 0777, $part);
				}
		   }
		} else {
			$sftp = $this->sftp;
			ssh2_sftp_mkdir($sftp, $remote_dir, 0777, $recursive);
		}
	}
}
