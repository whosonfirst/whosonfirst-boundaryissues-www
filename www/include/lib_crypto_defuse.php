<?php

	# https://github.com/defuse/php-encryption

	require_once("defuse-crypto/defuse-crypto.phar");

	use Defuse\Crypto\Crypto;
	use Defuse\Crypto\Key;

	#################################################################

	function crypto_encrypt($data, $secret){

		try {
			$key = Key::loadFromAsciiSafeString($secret);
			return Crypto::encrypt($data, $key);
		} catch (Exception $e) {
		  	error_log("crypto_encrypt: " . $e->getMessage());
			return null;
		}
	}

	#################################################################

	function crypto_decrypt($ciphertext, $secret){

		try {
			$key = Key::loadFromAsciiSafeString($secret);
			return Crypto::decrypt($ciphertext, $key);
		} catch (Exception $e){
			return null;
		}
	}

	#################################################################

	function crypto_generate_key(){

		$key = Key::createNewRandomKey();
		return $key->saveToAsciiSafeString();
	}

	#################################################################
	# the end 
