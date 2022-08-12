<?php
	// Module Prestashop de paiement sécurisé par carte bancaire via SIPS
	// préconfiguré pour un hébergement en serveur dédié sous release Olf Software
	//
	// (c) Patrick Prémartin 21-22/03/2009
	// (c) Olf Software 2009 pour Kipoos

	include(dirname(__FILE__).'/../../config/config.inc.php');
	include(dirname(__FILE__).'/../../config/settings.inc.php');
	$link = mysql_connect(_DB_SERVER_, _DB_USER_, _DB_PASSWD_);
	if(!$link)
	{
		header("Location: http://".$_SERVER['HTTP_HOST'].__PS_BASE_URI__."index.php");
		exit;
	}
	@mysql_select_db(_DB_NAME_, $link);
	include(dirname(__FILE__).'/olfsoftpaiementsips.php');
	$olfsoftpaiementsips = new olfsoftpaiementsips();
	$conf = Configuration::getMultiple(array('OSLCL_MERCHANT_ID', 'OSLCL_FOLDER', 'OSLCL_SIPS_RELEASE'));

	$fp=fopen(dirname(__FILE__)."/../../temp/logs_commandes-".date("Ymd").".txt", "a");
	fwrite ($fp, "log du ".date("YmdHis")." - paiement par carte bancaire\n");

	$erreur = false;
	$tableau = explode ("!", exec($conf['OSLCL_FOLDER']."bin/response pathfile=".$conf['OSLCL_FOLDER']."pathfile message=".$_POST["DATA"]));
	$infos_paiement_cb["code"] = $tableau[1];
	$infos_paiement_cb["error"] = $tableau[2];
	$infos_paiement_cb["merchant_id"] = $tableau[3];
	$infos_paiement_cb["merchant_country"] = $tableau[4];
	$infos_paiement_cb["amount"] = $tableau[5];
	$infos_paiement_cb["transaction_id"] = $tableau[6];
	$infos_paiement_cb["payment_means"] = $tableau[7];
	$infos_paiement_cb["transmission_date"] = $tableau[8];
	$infos_paiement_cb["payment_time"] = $tableau[9];
	$infos_paiement_cb["payment_date"] = $tableau[10];
	$infos_paiement_cb["response_code"] = $tableau[11];
	$infos_paiement_cb["payment_certificate"] = $tableau[12];
	$infos_paiement_cb["authorisation_id"] = $tableau[13];
	$infos_paiement_cb["currency_code"] = $tableau[14];
	$infos_paiement_cb["card_number"] = $tableau[15];
	$infos_paiement_cb["cvv_flag"] = $tableau[16];
	$infos_paiement_cb["cvv_response_code"] = $tableau[17];
	$infos_paiement_cb["bank_response_code"] = $tableau[18];
	$infos_paiement_cb["complementary_code"] = $tableau[19];
	if (5 == $conf['OSLCL_SIPS_RELEASE'])
	{
		$infos_paiement_cb["return_context"] = $tableau[20];
		$infos_paiement_cb["caddie"] = $tableau[21]; // contient id_cart
		$infos_paiement_cb["receipt_complement"] = $tableau[22];
		$infos_paiement_cb["merchant_language"] = $tableau[23];
		$infos_paiement_cb["language"] = $tableau[24];
		$infos_paiement_cb["customer_id"] = $tableau[25];
		$infos_paiement_cb["order_id"] = $tableau[26]; // contient id_cart
		$infos_paiement_cb["customer_email"] = $tableau[27];
		$infos_paiement_cb["customer_ip_address"] = $tableau[28];
		$infos_paiement_cb["capture_day"] = $tableau[29];
		$infos_paiement_cb["capture_mode"] = $tableau[30];
		$infos_paiement_cb["data"] = $tableau[31];
		fwrite ($fp, "Version -".$conf['OSLCL_SIPS_RELEASE']."- de SIPS prise en charge.\n");
	}
	else if (6 == $conf['OSLCL_SIPS_RELEASE'])
	{
		$infos_paiement_cb["complementary_info"] = $tableau[20]; // ajout à la version 600 de l'API SIPS
		$infos_paiement_cb["return_context"] = $tableau[21];
		$infos_paiement_cb["caddie"] = $tableau[22]; // contient id_cart
		$infos_paiement_cb["receipt_complement"] = $tableau[23];
		$infos_paiement_cb["merchant_language"] = $tableau[24];
		$infos_paiement_cb["language"] = $tableau[25];
		$infos_paiement_cb["customer_id"] = $tableau[26];
		$infos_paiement_cb["order_id"] = $tableau[27]; // contient id_cart
		$infos_paiement_cb["customer_email"] = $tableau[28];
		$infos_paiement_cb["customer_ip_address"] = $tableau[29];
		$infos_paiement_cb["capture_day"] = $tableau[30];
		$infos_paiement_cb["capture_mode"] = $tableau[31];
		$infos_paiement_cb["data"] = $tableau[32];
		fwrite ($fp, "Version -".$conf['OSLCL_SIPS_RELEASE']."- de SIPS prise en charge.\n");
	}
	else
	{
		$erreur = true;
		fwrite ($fp, "Version -".$conf['OSLCL_SIPS_RELEASE']."- de SIPS non gérée.\n");
	}
	
	if (!$erreur)
	{
		$cart = new Cart(intval($infos_paiement_cb["order_id"]));
		if (($infos_paiement_cb["code"] == "") && ($infos_paiement_cb["error"] == ""))
		{
			fwrite ($fp, "erreur appel response\nexecutable response non trouve dans ".$conf['OSLCL_FOLDER']."bin/response\n");
		}
		else if ($infos_paiement_cb["code"] != "0")
		{
			fwrite ($fp, "Erreur appel API de paiement.\nmessage erreur : ".$infos_paiement_cb["error"]."\n");
			$olfsoftpaiementsips->validateOrder($infos_paiement_cb["order_id"],_PS_OS_ERROR_,0, "Paiement CB",'Transaction CB LCL : '.$infos_paiement_cb["error"]);
		}
		else if ($infos_paiement_cb["bank_response_code"]=='05')
		{
			fwrite ($fp, "Erreur appel API de paiement.\nmessage erreur : ".$infos_paiement_cb["error"]."\npanier : ".$infos_paiement_cb["order_id"]."\n");
			$olfsoftpaiementsips->validateOrder($infos_paiement_cb["order_id"],_PS_OS_ERROR_,0, "Paiement CB",'Transaction CB LCL : '.$infos_paiement_cb["error"]);
		}
		else if ($infos_paiement_cb["bank_response_code"]=='00')
		{
			if (Validate::isLoadedObject($cart))
			{
				if ($cart->OrderExists() == 0)
				{
					$montant = number_format(intval($infos_paiement_cb["amount"])/100, 2, '.', '');
					$olfsoftpaiementsips->validateOrder($infos_paiement_cb["order_id"],_PS_OS_PAYMENT_,$montant,"Paiement CB",'Transaction CB LCL : transaction='.$infos_paiement_cb["transaction_id"].' autorisation='.$infos_paiement_cb["authorisation_id"]);
				}
				else
				{
					fwrite( $fp, "erreur sur cart->OrderExists()\n");
				}
			}
			else
			{
				fwrite( $fp, "erreur sur Validate::isLoadedObject(cart)\n");
			}
			fwrite( $fp, "infos_paiement_cb=\n".serialize ($infos_paiement_cb)."\n");
		}
	}
	// header("Location: http://".$_SERVER['HTTP_HOST'].__PS_BASE_URI__."my-account.php");
	fwrite ($fp, "-------------------------------------------\n");
	fclose ($fp);
?>