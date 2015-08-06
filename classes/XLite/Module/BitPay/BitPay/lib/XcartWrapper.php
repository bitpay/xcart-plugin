<?php
/**
 * @license Copyright 2015 BitPay Inc., MIT License
 * see https://github.com/bitpay/bitpay-xcart/blob/master/LICENSE
 */

namespace XLite\Module\BitPay\BitPay\lib;

class XcartWrapper {

	public $version = '2.3.0';

	public $model;

	/**
	 * X-Cart Wrapper for BitPay Library
	 * @param \XLite\Model\Payment\Method $method
	 */
	public function __construct($method) {
		$this->method = $method;

		// Load up the BitPay library
		$autoloader = LC_DIR_MODULES . 'BitPay' . LC_DS . 'BitPay' . LC_DS . 'lib' . LC_DS . 'Bitpay' . LC_DS . 'xcartAutoloader.php';
		if (true === file_exists($autoloader) &&
			true === is_readable($autoloader))
		{
			require_once $autoloader;
			\Bitpay\xcartAutoloader::register();
		} else {
            \XLite\Core\TopMessage::getInstance()
                    ->addError('[Error] Unable to load BitPay library');
		}
	}

	/**
	 * Generates a token and returns a link to pair with it on BitPay
	 * @return string BitPay pairing url for connection token
	 */
	public function getPairingUrl() {
		// Sanitize label
		$label      = preg_replace('/[^a-zA-Z0-9 \-\_\.]/', '', 'Store');
		$label      = substr('XCart - ' . $label, 0, 59);

		// Generate a new Client ID each time. Prevents multiple pair, and revoked token before return issues
		$this->generateId();

		$client = $this->getClient();
		$token = $client->createToken(array(
			'facade'	  => 'merchant',
			'label'	   => $label,
			'id'		  => (string)$this->getPublicKey()->getSin(),
		));

		$this->setting('connection', 'connecting');
		$this->setting('token', $token->getToken());

		$network = $this->getNetwork();
		if (443 === $network->getApiPort()) {
			return 'https://' . $network->getApiHost() . '/api-access-request?pairingCode=' . $token->getPairingCode();
		} else {
			return 'http://' . $network->getApiHost() . ':' . $network->getApiPort() . '/api-access-request?pairingCode=' . $token->getPairingCode();
		}
	}

	/**
	 * Checks to see if we've successfully connected to BitPay's API
	 * @return void
	 */
	public function checkConnection() {
		$connection_token = $this->setting('token');

		try {
			$client = $this->getClient();
			$tokens = $client->getTokens();
			foreach ($tokens as $token) {
				if ($token->getToken() === $connection_token) {
					$this->setting('connection', 'connected');
					$this->setting('pairing_code', null);
					$this->setting('pairing_expiration', null);
					return;
				}
			}
		} catch (\UnexpectedValueException $e) {
			$this->log('error', 'A token was found, but the key for it is invalid');
		} catch (\Exception $e) {
			// An exception is raised when no tokens can be found
			// This is fine and expected
		}

		// Disconnect if token doesn't exist anymore
		if ($this->setting('connection') === 'connected') {
			$this->setting('connection', 'disconnected');
			$this->setting('token', null);
			$this->setting('status', '0');
		}
	}

	/**
	 * Generates a new set of keys to interact with BitPay's API
	 * @return PrivateKey
	 */
	public function generateId() {
		// Generate new keys
		$private_key = new \Bitpay\PrivateKey();
		$private_key->generate();
		$public_key = $private_key->getPublicKey();

		// Persist the keys to the database
		$this->setting('private_key', $this->encrypt(serialize($private_key)));
		$this->setting('public_key', $this->encrypt(serialize($public_key)));
		$this->setting('connection', 'disconnected');
		$this->setting('token', null);

		return $private_key;
	}

	/**
	 * Retrieves a client to interact with BitPay's API
	 * @param string $network Optional network identifier
	 * @return Client
	 */
	public function getClient($network = null) {
		$network = $this->getNetwork($network);

		$curl_options = array();
		if ($network instanceof \Bitpay\Network\Customnet) {

			//Customize the curl options
			$curl_options = array(
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
			);

		}
		$adapter = new \Bitpay\Client\Adapter\CurlAdapter($curl_options);

		$private_key = $this->getPrivateKey();
		$public_key = $this->getPublicKey();

		$client = new \Bitpay\Client\Client();
		$client->setPrivateKey($private_key);
		$client->setPublicKey($public_key);
		$client->setNetwork($network);
		$client->setAdapter($adapter);

		return $client;
	}

	/**
	 * Validates and sets the network setting
	 * @param string|array $network Network to connect to or custom Network parameter array
	 * @return Network
	 */
	public function setNetwork($network) {
		if ($network === 'livenet') {
			$this->setting('network', 'livenet');
			return 'Livenet';
		} elseif ($network === 'testnet') {
			$this->setting('network', 'testnet');
			return 'Testnet';
		} elseif (is_array($network)) {
			if (isset($network['url']) && isset($network['port'])) {
				$port_required = (isset($network['port_required'])) ? true : false;
				$network_params = array(
					'url' => $network['url'],
					'port' => $network['port'],
					'port_required' => $port_required
				);
				$this->setting('network', $network_params);
				return 'Customnet';
			}
		}
		$this->setting('network', 'livenet');
		return 'Livenet';
	}

	/**
	 * Retrieves a BitPay's API Network object
	 * @param string $network Optional network identifier
	 * @return Network
	 */
	public function getNetwork($network = null)	{
		$network = (empty($network)) ? $this->setting('network') : $network;
		$this->log('trace', 'Getting Network: ' . var_export($network, true));
		if ($network === 'livenet') {
			return new \Bitpay\Network\Livenet();
		} elseif ($network === 'testnet') {
			return new \Bitpay\Network\Testnet();
		} elseif (is_array($network)) {
			if (isset($network['url']) && isset($network['port']) && isset($network['port_required'])) {
				return new \Bitpay\Network\Customnet($network['url'], (int)$network['port'], (boolean)$network['port_required']);
			}
		}
		$this->setting('network', 'livenet');
		return new \Bitpay\Network\Livenet();
	}

	/**
	 * Retrieves the private key used to interact with BitPay's API
	 * @throws UnexpectedValueException If the key cannot be decrypted and unserialized to a PrivateKey object
	 * @return PrivateKey
	 */
	private function getPrivateKey() {
		$private_key = $this->setting('private_key');

		// Null check the private key, and generate it should it not exist
		if (is_null($private_key)) {
			$this->log('info', $this->localize('log_no_private_key'));
			return $this->generateId();
		}

		$this->log('debug', $this->localize('log_private_key_found'));
		$private_key = @unserialize($this->decrypt($private_key));

		// Check for key integrity
		if (!($private_key instanceof \Bitpay\PrivateKey)) {
			$this->log('error', sprintf($this->localize('log_private_key_wrong_type'), gettype($private_key)));
			$this->log('trace', sprintf($this->localize('log_encrypted_key'), $private_key));
			$this->log('trace', sprintf($this->localize('log_decrypted_key'), $this->decrypt($private_key)));
			throw new UnexpectedValueException();
		}

		return $private_key;
	}

	/**
	 * Retrieves the public key used to interact with BitPay's API
	 * @return PublicKey
	 */
	public function getPublicKey() {
		$public_key = $this->setting('public_key');

		// Null check the private key, and generate it should it not exist
		if (is_null($public_key)) {
			$this->log('info', $this->localize('log_no_public_key'));
			$private_key = $this->getPrivateKey();
			return $private_key->getPublicKey();
		}

		$this->log('debug', $this->localize('log_public_key_found'));
		$public_key = @unserialize($this->decrypt($public_key));

		// Check for key integrity
		if (!($public_key instanceof \Bitpay\PublicKey)) {
			$this->log('error', sprintf($this->localize('log_public_key_wrong_type'), gettype($public_key)));
			$this->log('trace', sprintf($this->localize('log_encrypted_key'), $public_key));
			$this->log('trace', sprintf($this->localize('log_decrypted_key'), $this->decrypt($public_key)));

			// Try to fix the problem by regenerating it
			$this->log('debug', $this->localize('log_public_key_regenerate'));
			$private_key = $this->getPrivateKey();
			$public_key = $private_key->getPublicKey();
			$this->log('warn', $this->localize('log_regenerate_success'));
			$this->setting('public_key', $this->encrypt(serialize($public_key)));
		}

		return $public_key;
	}

	/**
	 * Constructs some helpful diagnostic info.
	 * @return string
	 */
	public function getServerInfo() {
		$gmp	= extension_loaded('gmp') ? 'enabled' : 'missing';
		$bcmath = extension_loaded('bcmath') ? 'enabled' : 'missing';
		$info   = "<pre><strong>Server Information:</strong>\n" .
					"PHP: " . phpversion() . "\n" .
					"PHP-GMP: " . $gmp . "\n" .
					"PHP-BCMATH: " . $bcmath . "\n" .
					"OpenCart: " . VERSION . "\n" .
					"BitPay Plugin: " . $this->version . "\n" .
					"BitPay Lib: {{bitpay_lib_version}}\n";
		return $info;
	}

	/**
	 * Sends a support request to BitPay.
	 * @return void
	 */
	public function sendSupportRequest() {
		$this->load->library('mail');

		$mail = new Mail(array(
			'protocol' => $this->config->get('config_mail')['protocol'],
			'parameter' => $this->config->get('config_mail')['parameter'],
			'hostname' => $this->config->get('config_mail')['smtp_hostname'],
			'username' => $this->config->get('config_mail')['smtp_username'],
			'password' => $this->config->get('config_mail')['smtp_password'],
			'port' => $this->config->get('config_mail')['smtp_port'],
			'timeout' => $this->config->get('config_mail')['smtp_timeout']
		));

		$mail->setTo('support@bitpay.com');
		$mail->setFrom($this->request->post['request_email_address']);
		$mail->setSender($this->request->post['request_name']);
		$mail->setSubject($this->request->post['request_subject']);

		$description = $this->request->post['request_description'];

		// Include server info?
		if ($this->request->post['request_send_server_info'] === "1") {
			$description .= "\n\n" . $this->getServerInfo();
		}
		$mail->setHtml($description);

		// Include BitPay logs?
		if ($this->request->post['request_send_logs'] === "1") {
			$mail->addAttachment(DIR_LOGS . 'bitpay.log');
		}

		$mail->send();
	}

	/**
	 *  Creates the BitPay Invoice from a prepared Invoice
	 *  @param Invoice $invoice Prepared invoice to send to BitPay
	 *  @return Invoice complete invoice returned by BitPay
	 */
	public function createInvoice($invoice) {
		$this->log('info', 'Attempting to generate invoice for ' . $invoice->getOrderId() . '...');

		$token = new \Bitpay\Token();
		$token->setToken($this->setting('token'));

		$client = $this->getClient();
		$client->setToken($token);

		return $client->createInvoice($invoice);
	}

	public function buildInvoice($params) {

		$invoice = new \Bitpay\Invoice();
		$buyer = new \Bitpay\Buyer();
		$item = new \Bitpay\Item();
		$currency = new \Bitpay\Currency();

		$buyer->setFirstName($params['BillingNameFirst']);
		$buyer->setLastName($params['BillingNameLast']);
	    $buyer->setAddress(
            array(
                $params['BillingAddress']
            )
        );

	    $buyer->setCity($params['BillingCity']);
        $buyer->setState($params['BillingState']);
        $buyer->setCountry($params['BillingCountry']);
        $buyer->setZip($params['BillingZipCode']);
        $buyer->setEmail($params['EmailAddress']);
        $buyer->setPhone($params['PhoneNumber']);

        $item->setDescription($params['ProductDescription'])
             ->setPrice(floatval($params['price']));
             
		$currency->setCode($params['currencyCode']);

		$invoice->setTransactionSpeed($this->setting('riskSpeed'));

		if ($this->setting('redirectURL') != NULL) {
			$invoice->setRedirectUrl($params['redirectURL']);
		} else {
			$invoice->setRedirectUrl($params['returnURL']);
		}

        $invoice->setPosData($params['transactionID']);
        $invoice->setFullNotifications(true);

		$invoice->setOrderId($params['orderId'])
                ->setNotificationUrl($params['PostBackURL']);
		$invoice->setCurrency($currency);
        $invoice->setBuyer($buyer);
        $invoice->setItem($item);

        return $invoice;
	}

	/**
	 *  Retrieves a BitPay Invoice by ID
	 *  @param string $invoice_id
	 *  @param string $network Optional network identifier
	 *  @return Invoice
	 */
	public function getInvoice($invoice_id, $network = null) {
		$this->log('info', 'Attempting to retrieve invoice for ' . $invoice_id . '...');

		$token = new \Bitpay\Token();
		$token->setToken($this->setting('token'));

		$client = $this->getClient($network);
		$client->setToken($token);

		return $client->getInvoice($invoice_id);
	}

	/**
	 * Logs with an arbitrary level.
	 * @param string $level The type of log.
	 *						Should be 'error', 'warn', 'info', 'debug', 'trace'
	 *						In normal mode, 'error' and 'warn' are logged
	 *						In debug mode, all are logged
	 * @param string $message The message of the log
	 * @param int $depth How deep to go to find the calling function
	 * @return void
	 */
	public function log($level, $message, $depth = 0) {
		$level = strtoupper($level);
		$prefix = '[' . $level . ']';

		// Debug formatting
		if ($this->setting('debug') === '1') {
			$depth += 1;
			$prefix .= '{';
			$backtrace = debug_backtrace();
			if (isset($backtrace[$depth]['class'])) {
				$class = preg_replace('/[a-z]/', '', $backtrace[$depth]['class']);
				$prefix .= $class . $backtrace[$depth]['type'];
			}
			if (isset($backtrace[$depth]['function'])) {
				$prefix .= $backtrace[$depth]['function'];
			}
			$prefix .= '}';
		}

		if ('ERROR' === $level || 'WARN' === $level || $this->setting('debug') === '1') {
            \XLite\Logger::logCustom('bitpay', $prefix . ' ' . $message, false);
		}
	}

	/**
	 * Better setting method for bitpay settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes bitpay_ prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	public function setting($key, $value = null) {

		// Set the setting
		if (func_num_args() === 2) {
			$this->method->setSetting($key, $value);
			\XLite\Core\Database::getRepo('\XLite\Model\Payment\Method')->update($this->method);
		}

		// Get the setting
		return $this->method->getSetting($key);
	}

	public function encrypt($data) {
		$mcrypt_ext = new \Bitpay\Crypto\McryptExtension();
		$fingerprint = sha1(sha1(LC_DIR_MODULES . 'BitPay' . LC_DS . 'BitPay' . LC_DS . 'lib' . LC_DS));
        $fingerprint = substr($fingerprint, 0, 24);

		return $mcrypt_ext->encrypt($data, $fingerprint, '00000000');
	}

	public function decrypt($encrypted_data) {
		$mcrypt_ext = new \Bitpay\Crypto\McryptExtension();
		$fingerprint = sha1(sha1(LC_DIR_MODULES . 'BitPay' . LC_DS . 'BitPay' . LC_DS . 'lib' . LC_DS));
        $fingerprint = substr($fingerprint, 0, 24);

		return $mcrypt_ext->decrypt($encrypted_data, $fingerprint, '00000000');
	}

	public function localize($string) {
		return $string;
	}
}
