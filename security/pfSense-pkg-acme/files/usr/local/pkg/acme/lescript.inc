<?php

/*
Copyright (c) 2015, Stanislav Humplik <sh@analogic.cz>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

source: https://github.com/analogic/lescript
*/

namespace Analogic\ACME;

class Lescript
{
    //public $ca = 'https://acme-v01.api.letsencrypt.org';
    //public $ca = 'https://acme-staging.api.letsencrypt.org'; // testing
    public $license = 'https://letsencrypt.org/documents/LE-SA-v1.0.1-July-27-2015.pdf';
    public $countryCode = 'CZ';
    public $state = "Czech Republic";

    private $certificatesDir;
    private $webRootDir;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    private $client;
    //private $accountKeyPath;
	private $privateKey = "";
	public $callback;

    protected function __construct()
    {
	}

	public static function createWithCustomEvents($ca, $logger = null) {
		$instance = new static();
		$instance->logger = $logger;
		//echo "CA: $ca";
        $instance->client = new Client($ca);
		return $instance;
	}
	
	public function createStandalone($certificatesDir, $webRootDir, $logger = null)
    {
        $this->certificatesDir = $certificatesDir;
        $this->webRootDir = $webRootDir;
        $this->logger = $logger;
        $this->client = new Client($this->ca);
        $this->accountKeyPath = $certificatesDir.'/_account/private.pem';
    }

    /*public function initAccount()
    {
        if(!is_file($this->accountKeyPath)) {

            // generate and save new private key for account
            // ---------------------------------------------

            $this->log('Starting new account registration');
            $this->generateKey(dirname($this->accountKeyPath));
            $this->postNewReg();
            $this->log('New account certificate registered');

        } else {

            $this->log('Account already registered. Continuing.');

        }
    }*/

    public function signDomains(array $domains)
    {
        $this->log('Starting certificate generation process for domains');

        $privateAccountKey = $this->readPrivateKey($this->accountKeyPath);
        $accountKeyDetails = openssl_pkey_get_details($privateAccountKey);

        // start domains authentication
        // ----------------------------

        foreach($domains as $domain) {

            // 1. getting available authentication options
            // -------------------------------------------

            $this->log("Requesting challenge for $domain");

            $response = $this->signedRequest(
                "/acme/new-authz",
                array("resource" => "new-authz", "identifier" => array("type" => "dns", "value" => $domain))
            );

            // choose http-01 challange only
            $challenge = array_reduce($response['challenges'], function($v, $w) { return $v ? $v : ($w['type'] == 'http-01' ? $w : false); });
            if(!$challenge) throw new \RuntimeException("HTTP Challenge for $domain is not available. Whole response: ".json_encode($response));

            $this->log("Got challenge token for $domain");
            $location = $this->client->getLastLocation();


            // 2. saving authentication token for web verification
            // ---------------------------------------------------

            /*$directory = $this->webRootDir.'/.well-known/acme-challenge';
            $tokenPath = $directory.'/'.$challenge['token'];

            if(!file_exists($directory) && !@mkdir($directory, 0755, true)) {
                throw new \RuntimeException("Couldn't create directory to expose challenge: ${tokenPath}");
            }*/

            $header = array(
                // need to be in precise order!
                "e" => Base64UrlSafeEncoder::encode($accountKeyDetails["rsa"]["e"]),
                "kty" => "RSA",
                "n" => Base64UrlSafeEncoder::encode($accountKeyDetails["rsa"]["n"])

            );
            $payload = $challenge['token'] . '.' . Base64UrlSafeEncoder::encode(hash('sha256', json_encode($header), true));

            /*file_put_contents($tokenPath, $payload);
            chmod($tokenPath, 0644);*/
			$this->callback->chalenge_response_put($domain, $challenge['token'], $payload);
			
            // 3. verification process itself
            // -------------------------------

            $uri = "http://${domain}/.well-known/acme-challenge/${challenge['token']}";

            $this->log("Token for $domain saved at $tokenPath and should be available at $uri");

            // simple self check
            if($payload !== trim(@file_get_contents($uri))) {
                throw new \RuntimeException("Please check $uri - token not available");
            }

            $this->log("Sending request to challenge");

            // send request to challenge
            $result = $this->signedRequest(
                $challenge['uri'],
                array(
                    "resource" => "challenge",
                    "type" => "http-01",
                    "keyAuthorization" => $payload,
                    "token" => $challenge['token']
                )
            );

            // waiting loop
            do {
                if(empty($result['status']) || $result['status'] == "invalid") {
                    throw new \RuntimeException("Verification ended with error: ".json_encode($result));
                }
                $ended = !($result['status'] === "pending");

                if(!$ended) {
                    $this->log("Verification pending, sleeping 1s");
                    sleep(1);
                }

                $result = $this->client->get($location);

            } while (!$ended);

            $this->log("Verification ended with status: ${result['status']}");
            //@unlink($tokenPath);
			$this->callback->chalenge_response_cleanup($domain, $challenge['token']);
        }

        // requesting certificate
        // ----------------------
        /*$domainPath = $this->getDomainPath(reset($domains));

        // generate private key for domain if not exist
        if(!is_dir($domainPath) || !is_file($domainPath.'/private.pem')) {
            $this->generateKey($domainPath);
        }

        // load domain key
        $privateDomainKey = $this->readPrivateKey($domainPath.'/private.pem');*/
		$privateDomainKey = $this->readCertificatePSK();		

        $this->client->getLastLinks();

        // request certificates creation
        $result = $this->signedRequest(
            "/acme/new-cert",
            array('resource' => 'new-cert', 'csr' => $this->generateCSR($privateDomainKey, $domains))
        );
        if ($this->client->getLastCode() !== 201) {
            throw new \RuntimeException("Invalid response code: ".$this->client->getLastCode().", ".json_encode($result));
        }
        $location = $this->client->getLastLocation();

        // waiting loop
        $certificates = array();
        while(1) {
            $this->client->getLastLinks();

            $result = $this->client->get($location);

            if($this->client->getLastCode() == 202) {

                $this->log("Certificate generation pending, sleeping 1s");
                sleep(1);

            } else if ($this->client->getLastCode() == 200) {

                $this->log("Got certificate! YAY!");
                $certificates[] = $this->parsePemFromBody($result);


                foreach($this->client->getLastLinks() as $link) {
                    $this->log("Requesting chained cert at $link");
                    $result = $this->client->get($link);
                    $certificates[] = $this->parsePemFromBody($result);
                }

                break;
            } else {

                throw new \RuntimeException("Can't get certificate: HTTP code ".$this->client->getLastCode());

            }
        }

        if(empty($certificates)) throw new \RuntimeException('No certificates generated');

		$this->log("Calling: storeCertificate");
		$this->callback->storeCertificate($certificates);
        /*$this->log("Saving fullchain.pem");
        file_put_contents($domainPath.'/fullchain.pem', implode("\n", $certificates));

        $this->log("Saving cert.pem");
        file_put_contents($domainPath.'/cert.pem', array_shift($certificates));

        $this->log("Saving chain.pem");
        file_put_contents($domainPath."/chain.pem", implode("\n", $certificates));
		 */

        $this->log("Done !!§§!");
    }

	private function readCertificatePSK(){
		$psk = $this->callback->getCertificatePSK();
		if(($key = openssl_pkey_get_private($psk)) === FALSE) {
			throw new \RuntimeException(openssl_error_string());
		}
        return $key;
	}

	private function readPrivateKey($path)
    {
		if (!empty($this->privateKey)) {
			//echo " USING PSK from STRING ".$this->privateKey;
			if(($key = openssl_pkey_get_private($this->privateKey)) === FALSE) {
				throw new \RuntimeException(openssl_error_string());
			}
		} else {
			if(($key = openssl_pkey_get_private('file://'.$path)) === FALSE) {
				throw new \RuntimeException(openssl_error_string());
			}
		}
        return $key;
    }

    private function parsePemFromBody($body)
    {
        $pem = chunk_split(base64_encode($body), 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
    }

    private function getDomainPath($domain)
    {
        return $this->certificatesDir.'/'.$domain.'/';
    }

    public function postNewReg()
    {
        $this->log('Sending registration to letsencrypt server');

        return $this->signedRequest(
            '/acme/new-reg',
            array('resource' => 'new-reg', 'agreement' => $this->license)
        );
    }

    private function generateCSR($privateKey, array $domains)
    {
        $domain = reset($domains);
        $san = implode(",", array_map(function ($dns) { return "DNS:" . $dns; }, $domains));
        $tmpConf = tmpfile();
        $tmpConfMeta =  stream_get_meta_data($tmpConf);
        $tmpConfPath = $tmpConfMeta["uri"];

        // workaround to get SAN working
        fwrite($tmpConf,
'HOME = .
RANDFILE = $ENV::HOME/.rnd
[ req ]
default_bits = 2048
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
[ req_distinguished_name ]
countryName = Country Name (2 letter code)
[ v3_req ]
basicConstraints = CA:FALSE
subjectAltName = '.$san.'
keyUsage = nonRepudiation, digitalSignature, keyEncipherment');

        $csr = openssl_csr_new(
            array(
                "CN" => $domain,
                "ST" => $this->state,
                "C" => $this->countryCode,
                "O" => "Unknown",
            ),
            $privateKey,
            array(
                "config" => $tmpConfPath,
                "digest_alg" => "sha256"
            )
        );

        if (!$csr) throw new \RuntimeException("CSR couldn't be generated! ".openssl_error_string());

        openssl_csr_export($csr, $csr);
        fclose($tmpConf);

        file_put_contents($this->getDomainPath($domain)."/last.csr", $csr);
        preg_match('~REQUEST-----(.*)-----END~s', $csr, $matches);

        return trim(Base64UrlSafeEncoder::encode(base64_decode($matches[1])));
    }

    private function generateKey($outputDirectory)
    {
        $res = openssl_pkey_new(array(
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
            "private_key_bits" => 4096,
        ));

        if(!openssl_pkey_export($res, $privateKey)) {
            throw new \RuntimeException("Key export failed!");
        }

        $details = openssl_pkey_get_details($res);

        if(!is_dir($outputDirectory)) @mkdir($outputDirectory, 0700, true);
        if(!is_dir($outputDirectory)) throw new \RuntimeException("Cant't create directory $outputDirectory");

        file_put_contents($outputDirectory.'/private.pem', $privateKey);
        file_put_contents($outputDirectory.'/public.pem', $details['key']);
    }

	public function setPrivateKey($psk) {
		$this->privateKey = $psk;
		
	}
    private function signedRequest($uri, array $payload)
    {
        $privateKey = $this->readPrivateKey($this->accountKeyPath);
        $details = openssl_pkey_get_details($privateKey);

        $header = array(
            "alg" => "RS256",
            "jwk" => array(
                "kty" => "RSA",
                "n" => Base64UrlSafeEncoder::encode($details["rsa"]["n"]),
                "e" => Base64UrlSafeEncoder::encode($details["rsa"]["e"]),
            )
        );

        $protected = $header;
        $protected["nonce"] = $this->client->getLastNonce();


        $payload64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload)));
        $protected64 = Base64UrlSafeEncoder::encode(json_encode($protected));

        openssl_sign($protected64.'.'.$payload64, $signed, $privateKey, "SHA256");

        $signed64 = Base64UrlSafeEncoder::encode($signed);

        $data = array(
            'header' => $header,
            'protected' => $protected64,
            'payload' => $payload64,
            'signature' => $signed64
        );

        $this->log("Sending signed request to $uri");

        return $this->client->post($uri, json_encode($data));
    }

    protected function log($message)
    {
        if($this->logger) {
            $this->logger->info($message);
        } else {
            echo $message."\n";
        }
    }
}

class Client
{
    private $lastCode;
    private $lastHeader;

    private $base;

    public function __construct($base)
    {
        $this->base = $base;
    }

    private function curl($method, $url, $data = null)
    {
		//echo "$method, $url, {$this->base}";
        $headers = array('Accept: application/json', 'Content-Type: application/json');
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, preg_match('~^http~', $url) ? $url : $this->base.$url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);

        // DO NOT DO THAT!
        // curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        // curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
                break;
        }
        $response = curl_exec($handle);

        if(curl_errno($handle)) {
            throw new \RuntimeException('Curl: '.curl_error($handle));
        }

        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $this->lastHeader = $header;
        $this->lastCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $data = json_decode($body, true);
        return $data === null ? $body : $data;
    }

    public function post($url, $data)
    {
        return $this->curl('POST', $url, $data);
    }

    public function get($url)
    {
        return $this->curl('GET', $url);
    }

    public function getLastNonce()
    {
        if(preg_match('~Replay\-Nonce: (.+)~i', $this->lastHeader, $matches)) {
            return trim($matches[1]);
        }

        $this->curl('GET', '/directory');
        return $this->getLastNonce();
    }

    public function getLastLocation()
    {
        if(preg_match('~Location: (.+)~i', $this->lastHeader, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    public function getLastCode()
    {
        return $this->lastCode;
    }

    public function getLastLinks()
    {
        preg_match_all('~Link: <(.+)>;rel="up"~', $this->lastHeader, $matches);
        return $matches[1];
    }
}

class Base64UrlSafeEncoder
{
    public static function encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    public static function decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
