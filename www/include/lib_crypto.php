<?php
	# https://paragonie.com/blog/2015/05/using-encryption-and-authentication-correctly
	# https://paragonie.com/blog/2015/05/if-you-re-typing-word-mcrypt-into-your-code-you-re-doing-it-wrong
	# https://paragonie.com/blog/2017/02/cryptographically-secure-php-development

	# https://paragonie.com/blog/2017/06/libsodium-quick-reference-quick-comparison-similar-functions-and-which-one-use
	# https://paragonie.com/book/pecl-libsodium/read/09-recipes.md#encrypted-cookies
	# https://github.com/defuse/php-encryption/blob/master/docs/Tutorial.md

	# hey look! running code!!

	#################################################################

	switch ($GLOBALS["cfg"]["crypto_use_module"]){

		case "defuse":
			loadlib("crypto_defuse");
			break;
		case "libsodium":
			loadlib("crypto_libsodium");
			break;
		case "mcrypt":
			loadlib("crypto_mcrypt");
			break;
		default:
			die("You must specify a crypto module in cfg.crypto_module");
	}

	#################################################################

	# the end
