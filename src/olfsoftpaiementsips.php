<?php
	// Module Prestashop de paiement sécurisé par carte bancaire via SIPS
	// préconfiguré pour un hébergement en serveur dédié sous release Olf Software
	//
	// (c) Patrick Prémartin 21-22/03/2009
	// (c) Olf Software 2009 pour Kipoos

	class olfsoftpaiementsips extends PaymentModule
	{
		private	$_html = '';
		private $_postErrors = array();

		public function __construct()
		{
			$this->name = 'olfsoftpaiementsips';
			$this->tab = 'Payment';
			$this->version = '1.0';
			
			$this->currencies = true;
			$this->currencies_mode = 'radio';

			parent::__construct();

			$this->page = basename(__FILE__, '.php');
			$this->displayName = 'Paiement CB pour LCL';
			$this->description = 'Paiement en ligne par carte bancaire via SIPS chez LCL. (c)&nbsp;Olf&nbsp;Software&nbsp;21/03/2009&nbsp;pour&nbsp;Kipoos';
		}

		public function install()
		{
			if (!parent::install()
				OR !Configuration::updateValue('OSLCL_MERCHANT_ID', '014213245611111')
				OR !Configuration::updateValue('OSLCL_FOLDER', '/home/sogenactif/')
				OR !Configuration::updateValue('OSLCL_SIPS_RELEASE', 5)
				OR !$this->registerHook('payment'))
				return false;
			return true;
		}

		public function uninstall()
		{
			if (!Configuration::deleteByName('OSLCL_MERCHANT_ID') OR !Configuration::deleteByName('OSLCL_FOLDER') OR !Configuration::deleteByName('OSLCL_SIPS_RELEASE')
				OR !parent::uninstall())
				return false;
			return true;
		}

		public function getContent()
		{
			$this->_html = '<h2>Paiement CB pour LCL</h2>';
			if (isset($_POST['submitSips']))
			{
				if (empty($_POST['merchant_id']))
					$_POST['merchant_id'] = '014213245611111';
				if (empty($_POST['folder']))
					$_POST['folder'] = '/home/sogenactif/';
				if (!isset($_POST['sips_release']))
					$_POST['sips_release'] = 5;
				if (!sizeof($this->_postErrors))
				{
					Configuration::updateValue('OSLCL_MERCHANT_ID', $_POST['merchant_id']);
					Configuration::updateValue('OSLCL_FOLDER', $_POST['folder']);
					Configuration::updateValue('OSLCL_SIPS_RELEASE', intval($_POST['sips_release']));
					$this->displayConf();
				}
				else
					$this->displayErrors();
			}

			$this->displayFormSettings();
			return $this->_html;
		}

		public function displayConf()
		{
			$this->_html .= '
			<div class="conf confirm">
				<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
				Donn&eacute;es enregistr&eacute;es
			</div>';
		}

		public function displayErrors()
		{
			$nbErrors = sizeof($this->_postErrors);
			$this->_html .= '
			<div class="alert error">
				<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
				<ol>';
			foreach ($this->_postErrors AS $error)
				$this->_html .= '<li>'.$error.'</li>';
			$this->_html .= '
				</ol>
			</div>';
		}
		
		
		public function displayFormSettings()
		{
			$conf = Configuration::getMultiple(array('OSLCL_MERCHANT_ID', 'OSLCL_FOLDER', 'OSLCL_SIPS_RELEASE'));
			$merchant_id = array_key_exists('merchant_id', $_POST) ? $_POST['merchant_id'] : (array_key_exists('OSLCL_MERCHANT_ID', $conf) ? $conf['OSLCL_MERCHANT_ID'] : '');
			$folder = array_key_exists('folder', $_POST) ? $_POST['folder'] : (array_key_exists('OSLCL_FOLDER', $conf) ? $conf['OSLCL_FOLDER'] : '');
			$sips_release = array_key_exists('sips_release', $_POST) ? $_POST['sips_release'] : (array_key_exists('OSLCL_SIPS_RELEASE', $conf) ? $conf['OSLCL_SIPS_RELEASE'] : '');

			$this->_html .= '
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<fieldset>
				<legend><img src="../img/admin/contact.gif" />Configuration</legend>
				<label>Num&eacute;ro de commer&ccedil;ant</label>
				<div class="margin-form"><input type="text" size="20" name="merchant_id" value="'.htmlentities($merchant_id, ENT_COMPAT, 'UTF-8').'" /></div>
				<label>Dossier du CGI SIPS</label>
				<div class="margin-form"><input type="text" size="60" name="folder" value="'.htmlentities($folder, ENT_COMPAT, 'UTF-8').'" /></div>
				<label>Version de l\'interface SIPS</label>
				<div class="margin-form">
					<input type="radio" name="sips_release" value="5" '.((5 == $sips_release) ? 'checked="checked"' : '').' /> 5.x
					<input type="radio" name="sips_release" value="6" '.((6 == $sips_release) ? 'checked="checked"' : '').' /> 6.x
				</div>
				<br /><center><input type="submit" name="submitSips" value="Enregistrer" class="button" /></center>
			</fieldset>
			</form><br /><br />
			<fieldset class="width3">
				<legend><img src="../img/admin/warning.gif" />'.$this->l('Information').'</legend>
				Pour tester le syst&egrave;me, utiliser le certificat commer&ccedil;ant <b>014213245611111</b>.<br />
				En mode test :<br />
				- la carte bancaire suivante permet de payer toutes les commandes : <b>4974934125497800</b> expiration dans le futur, CVV &agrave; <b>000</b>,<br />
				- la carte bancaire suivante permet de refuser les paiements : <b>4972187615205</b>, expiration et CVV important peu.<br />
			</fieldset>';
		}

		public function hookPayment($params)
		{
			global $smarty;

			$address = new Address(intval($params['cart']->id_address_invoice));
			$customer = new Customer(intval($params['cart']->id_customer));
			$currency = $this->getCurrency();

			if (!Validate::isLoadedObject($address) OR !Validate::isLoadedObject($customer) OR !Validate::isLoadedObject($currency))
				return $this->l('Error: (invalid address or customer)');
				
			$montant = number_format(Tools::convertPrice($params['cart']->getOrderTotal(true, 3), $currency), 2, '.', '');

			$parm="merchant_id=".Configuration::get('OSLCL_MERCHANT_ID');
			$parm=$parm." pathfile=".Configuration::get('OSLCL_FOLDER')."pathfile";
			$parm=$parm." merchant_country=fr";
			$parm=$parm." language=fr";
			//pprem,04/06/2009 $parm=$parm." amount=".sprintf ("%0d", $montant*100);
			$parm=$parm." amount=".sprintf ("%0d", round($montant*100)); //pprem,04/06/2009
			$parm=$parm." currency_code=978";
			$taille = 6;
			$id = "";
			for ($j = 0; $j < $taille/5; $j++)
			{
				$num = mt_rand (0,99999);
				for ($i = 0; $i < 5; $i++)
				{
					$id = ($num % 10).$id;
					$num = floor ($num / 10);
				}
			}
			$parm=$parm." transaction_id=".substr ($id, 0, $taille);
			$parm=$parm." customer_ip_address=".$_SERVER["REMOTE_ADDR"];
			$parm=$parm." automatic_response_url=http://".$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$this->name.'/validation.php';
			$parm=$parm." cancel_return_url=http://".$_SERVER['HTTP_HOST'].__PS_BASE_URI__."order.php";
			$parm=$parm." normal_return_url=http://".$_SERVER['HTTP_HOST'].__PS_BASE_URI__."history.php";
			$parm=$parm." payment_means=CB,1,VISA,1,MASTERCARD,1";
			$parm=$parm." caddie=".intval($params['cart']->id);
			$parm=$parm." order_id=".intval($params['cart']->id);
			$path_bin = Configuration::get('OSLCL_FOLDER')."bin/request";
	//		return $path_bin." ".$parm;
			$result=exec($path_bin." ".$parm);
			$tableau = explode ("!", $result);
			$code = $tableau[1];
			$error = $tableau[2];
			$message = $tableau[3];
			if (( $code == "" ) && ($error == "" )) {
				$smarty->assign(array(
					'SIPS' => "<center><b><h2>Erreur appel API de paiement.</h2></b></center><p>executable request non trouve ".$path_bin."</p>"
				));
			} else if ($code != 0) {
				$smarty->assign(array(
					'SIPS' => "<center><b><h2>Erreur appel API de paiement.</h2></b></center><p>message erreur : ".$error."</p>"
				));
			} else {
				$smarty->assign(array(
					'SIPS' => $message
				));
			}
			return $this->display(__FILE__, 'olfsoftpaiementsips.tpl');
		}
	}
?>
