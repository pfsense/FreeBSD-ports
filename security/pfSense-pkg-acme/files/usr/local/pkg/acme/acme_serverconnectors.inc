<?php
/*
    acme_serverconnectors.inc
	Copyright (C) 2016 PiBa-NL
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
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
	
    /*public function uploadFile($local_file, $remote_file)
    {
        $data_to_send = file_get_contents($local_file);
        if ($data_to_send === false) {
            throw new \Exception("Could not open local file: {$local_file}.");
		}
		uploadData($data_to_send, $remote_file);
    }*/

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

			$parts = explode('/',$remote_dir); // 2013/06/11/username
			foreach($parts as $part){
				if(!@ftp_chdir($this->connection, $part)){
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
