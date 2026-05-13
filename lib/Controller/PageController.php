<?php
namespace OCA\Gestion\Controller;

require_once __DIR__ . '/../../vendor/autoload.php';

use OCP\IRequest;
use OCP\Mail\IMailer;
use OCP\Files\IRootFolder;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCA\Gestion\Db\Bdd;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\ISession;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataDownloadResponse;

use OCP\IUserManager;
use OCP\IUser;
use OCP\Accounts\IAccountManager;

use Dompdf\Dompdf;
use Dompdf\Options;

use Mpdf\Mpdf;

use Atgp\FacturX\Writer as FacturXWriter;

class PageController extends Controller {
	private $myDb;

	private $urlGenerator;
	private $mailer;
	private $config;

	private $myID;

	/** @var  ContentSecurityPolicy */
	private ContentSecurityPolicy $csp;
	
	/** @var ISession */
	private ISession $session;

    /** @var IUserManager */
    private IUserManager $userManager;

    /** @var IAccountManager */
    private IAccountManager $accountManager;

	/** @var IRootStorage */
	private $storage;
	
	/**
	 * Constructor
	 */
	public function __construct($AppName, 
								IRequest $request,
								$UserId, 
								Bdd $myDb, 
								IRootFolder $rootFolder,
								IURLGenerator $urlGenerator,
								IMailer $mailer,
								IConfig $config,
								IUserManager $userManager,
								IAccountManager $accountManager,
								ISession $session,
								ContentSecurityPolicy $csp){
		parent::__construct($AppName, $request);
		$this->myID = $UserId;
		//TODO Envoyer la valeur en cours
		// $this->idNextcloud = ;
		$this->myDb = $myDb;
		$this->urlGenerator = $urlGenerator;
		$this->mailer = $mailer;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->csp = $csp;
		$this->session = $session;



		try{
			$this->storage = $rootFolder->getUserFolder($this->myID);
		}catch(\OC\User\NoUserException $e){

		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function index() {
		$response = new TemplateResponse(	'gestion', 'index', array(	'path' => $this->myID, 
											'url' => $this->getNavigationLink(),
											'CompaniesList' => $this->getCompaniesList(),
											'CurrentCompany' => $this->session->get('CurrentCompany')
												)
											);  // templates/index.php
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function devis() {
		return new TemplateResponse('gestion', 'devis', array(	'path' => $this->myID, 
																'url' => $this->getNavigationLink(),
																'CompaniesList' => $this->getCompaniesList(),
																'CurrentCompany' => $this->session->get('CurrentCompany')
															)
														);  // templates/devis.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function facture() {
		return new TemplateResponse('gestion', 'facture', array(	'path' => $this->myID, 
																	'url' => $this->getNavigationLink(),
																	'CompaniesList' => $this->getCompaniesList(),
																	'CurrentCompany' => $this->session->get('CurrentCompany')
																)
															);  // templates/facture.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function produit() {
		return new TemplateResponse('gestion', 'produit', array(	'path' => $this->myID, 
																	'url' => $this->getNavigationLink(),
																	'CompaniesList' => $this->getCompaniesList(),
																	'CurrentCompany' => $this->session->get('CurrentCompany')
																)
															);  // templates/produit.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function statistique() {
		return new TemplateResponse('gestion', 'statistique', array(	'path' => $this->myID, 
																		'url' => $this->getNavigationLink(),
																		'CompaniesList' => $this->getCompaniesList(),
																		'CurrentCompany' => $this->session->get('CurrentCompany')
																	)
																);  // templates/statistique.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function legalnotice($page) {
		return new TemplateResponse('gestion', 'legalnotice', array(	'page' => 'content/legalnotice', 
																		'path' => $this->myID, 
																		'url' => $this->getNavigationLink(),
																		'CompaniesList' => $this->getCompaniesList(),
																		'CurrentCompany' => $this->session->get('CurrentCompany')
																	)
																);  // templates/legalnotice.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function france() {
		return new TemplateResponse('gestion', 'legalnotice', array('page' => 'legalnotice/france', 
																	'path' => $this->myID, 
																	'url' => $this->getNavigationLink(),
																	'CompaniesList' => $this->getCompaniesList(),
																	'CurrentCompany' => $this->session->get('CurrentCompany')
																)
															);  // templates/legalnotice.php
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @UseSession
	*/
	#[UseSession]
	public function config() {
		$res = $this->myDb->checkConfig($this->session->get('CurrentCompany'), $this->myID);
		if($res < 1 ){
			$this->session->set('CurrentCompany','');
		}
		
		if($this->session->get('CurrentCompany') != ''){
			foreach($this->myDb->getUsersShared($this->session->get('CurrentCompany')) as $user) {
				$shareUsers[] = $this->userManager->get($user['id_nextcloud']);
			}
		}

		$response = new TemplateResponse(	'gestion', 'configuration', array(	'path' => $this->myID, 
											'url' => $this->getNavigationLink(),
											'CompaniesList' => $this->getCompaniesList(),
											'CurrentCompany' => $this->session->get('CurrentCompany'),
											'shareUsers' => $shareUsers,
										)
									);  // templates/configuration.php

		$response->setContentSecurityPolicy($this->csp);
		return $response;
	}
	
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $numdevis
	 * @UseSession
	*/
	#[UseSession]
	public function devisshow($numdevis) {
		$devis = $this->myDb->getOneDevis($numdevis,$this->session->get('CurrentCompany'));
		$produits = $this->myDb->getListProduit($numdevis, $this->session->get('CurrentCompany'));
		return new TemplateResponse('gestion', 'devisshow', array(	'configuration'=> $this->getConfiguration(), 
																	'devis'=>json_decode($devis), 
																	'produit'=>json_decode($produits), 
																	'path' => $this->myID, 
																	'url' => $this->getNavigationLink(),
																	'logo' => $this->getLogo($this->session->get('CurrentCompany').'logo.png'),
																	'logo_header' => $this->getLogo($this->session->get('CurrentCompany').'logo_header.png'),
																	'logo_footer' => $this->getLogo($this->session->get('CurrentCompany').'logo_footer.png'),
																	'CompaniesList' => $this->getCompaniesList(),
																	'CurrentCompany' => $this->session->get('CurrentCompany')
																)
															);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $numfacture
	 * @UseSession
	*/
	#[UseSession]
	public function factureshow($numfacture) {
		$facture = $this->myDb->getOneFacture($numfacture,$this->session->get('CurrentCompany'));
		// $produits = $this->myDb->getListProduit($numdevis);
		return new TemplateResponse('gestion', 'factureshow', array(	'path' => $this->myID, 
																		'configuration'=> $this->getConfiguration(), 
																		'facture'=>json_decode($facture), 
																		'url' => $this->getNavigationLink(),
																		'logo' => $this->getLogo($this->session->get('CurrentCompany').'logo.png'),
																		'logo_header' => $this->getLogo($this->session->get('CurrentCompany').'logo_header.png'),
																		'logo_footer' => $this->getLogo($this->session->get('CurrentCompany').'logo_footer.png'),
																		'CompaniesList' => $this->getCompaniesList(),
																		'CurrentCompany' => $this->session->get('CurrentCompany')
																)
															);
	}															

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function isConfig() {
		

		return $this->myDb->isConfig($this->session->get('CurrentCompany'),$this->myID);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getNavigationLink(){
		return array(	"index" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.index"),
						"devis" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.devis"),
						"facture" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.facture"),
						"produit" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.produit"),
						"config" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.config"),
						"isConfig" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.isConfig"),
						"statistique" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.statistique"),
						"legalnotice" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.legalnotice"),
						"france" => $this->urlGenerator->linkToRouteAbsolute("gestion.page.france"),
					);
	}

	/**
	* @NoAdminRequired
	* @NoCSRFRequired
	* @UseSession
	*/
	#[UseSession]
	private function getCompaniesList() {
	$CompaniesList = $this->myDb->getCompaniesList($this->myID);

	if (
		(empty($this->session->get('CurrentCompany'))
		|| $this->session->get('CurrentCompany') == '')
		&& !empty($CompaniesList)
	) {
		$this->setCurrentCompany($CompaniesList[0]['id']);
	}

	return $CompaniesList;
}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	 * 
	 * @param string $companyID
	 */
	#[UseSession]
	public function setCurrentCompany($companyID){
		$this->session->set('CurrentCompany', $companyID);
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	 */
	#[UseSession]
	public function createCompany(){
		$this->myDb->createCompany($this->myID);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	 */
	#[UseSession]
	public function deleteCompany(){
		if($this->myDb->deleteCompany($this->session->get('CurrentCompany'), $this->myID)){
			$this->session->set('CurrentCompany', '');
			return new DataResponse("", 200, ['Content-Type' => 'application/json']);
		}else{
			return new DataResponse([$this->session->get('CurrentCompany'),$this->myID], 401, ['Content-Type' => 'application/json']);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param string $email
	 * @UseSession
	*/
	#[UseSession]
	public function addShareUser($email){
		$found = false;
		$ownedCompanies = $this->myDb->getCompaniesOwner($this->myID);
		foreach ($ownedCompanies as $company) {
			if ($company['id'] == $this->session->get('CurrentCompany')) {
				$found = true;
			}
		}	

		if($found){
			$users = $this->userManager->search('');
			foreach ($users as $user) {
				if ($user->getEMailAddress() === $email) {
					$this->myDb->addShareUser($this->session->get('CurrentCompany'),$user->getUID());
					return new DataResponse(['status' => 'success', 'data' => $user->getDisplayName()]);
				}
			}
			return new DataResponse(['status' => 'not found','data'=> 'User not found']);
		}

		return new DataResponse(['status' => 'Not owner','data'=> 'You are not the owner of this company']);
	}

	/**
	 * @NoAdminRequired
	 * @param string $uid
	 * @UseSession
	*/
	#[UseSession]
	public function delShareUser($uid){
		$found = false;
		$ownedCompanies = $this->myDb->getCompaniesOwner($this->myID);
		foreach ($ownedCompanies as $company) {
			if ($company['id'] == $this->session->get('CurrentCompany')) {
				$found = true;
			}
		}	

		if($found){
			$this->myDb->delShareUser($this->session->get('CurrentCompany'),$uid);
			return new DataResponse(['status' => 'success', 'data' => 'User deleted']);
		}

		return new DataResponse(['status' => 'Not owner','data'=> 'You are not the owner of this company'], 401);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getClients() {
		return $this->myDb->getClients($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getConfiguration() {
		return $this->myDb->getConfiguration($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getDevis() {
		return $this->myDb->getDevis($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getFactures() {
		
		return $this->myDb->getFactures($this->session->get('CurrentCompany'));
	}
	
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getProduits() {
		
		return $this->myDb->getProduits($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $numdevis
	 * @UseSession
	*/
	#[UseSession]
	public function getProduitsById($numdevis) {
		return $this->myDb->getListProduit($numdevis, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function getClient($id) {
		return $this->myDb->getClient($id, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function getClientbyiddevis($id) {
		return $this->myDb->getClientbyiddevis($id, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getServerFromMail(){
		return new DataResponse(['mail' => $this->config->getSystemValue('mail_from_address').'@'.$this->config->getSystemValue('mail_domain')],200, ['Content-Type' => 'application/json']);
	}
	
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function insertClient() {
		// try {
		// 	return new DataResponse($this->myDb->insertClient($this->session->get('CurrentCompany')), Http::STATUS_OK, ['Content-Type' => 'application/json']);
		// }
		// catch( PDOException $Exception ) {
		// 	return new DataResponse($Exception, 500, ['Content-Type' => 'application/json']);
		// }
		return $this->myDb->insertClient($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function insertDevis(){
		return $this->myDb->insertDevis($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function insertFacture(){
		return $this->myDb->insertFacture($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function insertProduit(){
		return $this->myDb->insertProduit($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function insertProduitDevis($id){
		return $this->myDb->insertProduitDevis($id, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $table
	 * @param string $column
	 * @param string $data
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function update($table, $column, $data, $id) {
		return $this->myDb->gestion_update($table, $column, $data, $id, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $table
	 * @param string $column
	 * @param string $data
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function updateConfiguration($table, $column, $data, $id) {
		return $this->myDb->gestion_updateConfiguration($table, $column, $data, $id);
	}

	/**
	 * @NoAdminRequired
	 * @param string $table
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function duplicate($table, $id) {
		if($this->myDb->gestion_duplicate($table, $id, $this->session->get('CurrentCompany'))){
			return new DataResponse("", 200, ['Content-Type' => 'application/json']);
		}else{
			return new DataResponse("", 500, ['Content-Type' => 'application/json']);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param string $id
	 * @param string $value
	 * @UseSession
	*/
	#[UseSession]
	public function drop($id, $value) {
		return $this->myDb->gestion_drop($id, $value, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @param string $table
	 * @param string $id
	 * @UseSession
	*/
	#[UseSession]
	public function delete($table, $id) {
		return $this->myDb->gestion_delete($table, $id, $this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $content
	 * @param string $name
	 * @param string $subject
	 * @param string $body
	 * @param string $to
	 * @param string $Cc
	 * @UseSession
	*/
	#[UseSession]
	public function sendPDF($content, $name, $subject, $body, $to, $Cc){
		$clean_name = html_entity_decode($name);
		
		try {

			$data = base64_decode($content);
			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setTo((array) json_decode($to));
			$myrrCc = (array) json_decode($Cc);
			if($myrrCc[0] != ""){
				$message->setCc($myrrCc);
			}
			$message->setBody($body, 'text/html');
			$AttachementPDF = $this->mailer->createAttachment($data,$clean_name.".pdf","application/pdf");
			$message->attach($AttachementPDF);
			
			$this->mailer->send($message);
			return new DataResponse("", 200, ['Content-Type' => 'application/json']);
		} catch (Exception $e) {
			return new DataResponse("Is your global mail server configured in Nextcloud ?", 500, ['Content-Type' => 'application/json']);
		}
		
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $content
	 * @param string $folder
	 * @param string $name
	 * @UseSession
	*/
	#[UseSession]
	public function savePDF($content, $folder, $name){

		$clean_folder = html_entity_decode($folder);
		$clean_name = html_entity_decode($name);
			try {
				$this->storage->newFolder($clean_folder);
			} catch(\OCP\Files\NotPermittedException $e) {
        }

		try {
			try {
				$ff = $clean_folder . $clean_name;
				$this->storage->newFile($ff);
				$file = $this->storage->get($ff);
				$data = base64_decode($content);
				$file->putContent($data);
          	} catch(\OCP\Files\NotFoundException $e) {
				
            }

        } catch(\OCP\Files\NotPermittedException $e) {
            
        }

		//work
		// try {
        //     try {
        //         $file = $this->storage->get('/test/myfile2.txt');
        //     } catch(\OCP\Files\NotFoundException $e) {
        //         
        //        	$file = $this->storage->get('/myfile.txt');
        //     }

        //     // the id can be accessed by $file->getId();
        //     $file->putContent('myfile2');

        // } catch(\OCP\Files\NotPermittedException $e) {
        //     // you have to create this exception by yourself ;)
        //     throw new StorageException('Cant write to file');
        // }

		// //
		// $userFolder->touch('/test/myfile2345.txt');
		// $file = $userFolder->get('/test/myfile2345.txt');
		// $file->putContent('test');
		// //$file = $userFolder->get('myfile2.txt');
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * Génère un PDF Factur-X (profil EN 16931 / ZUGFeRD 2.x) à partir
	 * des données de la facture et le sauvegarde dans Nextcloud.
	 *
	 * @param string $html      HTML du rendu facture (sans boutons)
	 * @param string $name      Nom de fichier souhaité (avec .pdf)
	 * @param string $folder    Dossier Nextcloud de destination
	 * @param int    $factureId ID de la facture en base
	 */
	#[UseSession]
	public function generateFacturX(string $html, string $name, string $folder, int $factureId) {
		try {
			// 1. Récupération des données
			$factureRows = json_decode($this->myDb->getOneFacture($factureId, $this->session->get('CurrentCompany')));
			$configRows  = json_decode($this->getConfiguration());

			if (empty($factureRows) || empty($configRows)) {
				return new DataResponse(['status' => 'error', 'message' => 'Facture ou configuration introuvable.'], 404);
			}

			$facture = $factureRows[0];
			$config  = $configRows[0];

			// 2. Génération du PDF via mPDF
			$mpdf = new Mpdf([
				'mode'          => 'utf-8',
				'format'        => 'A4',
				'margin_top'    => 10,
				'margin_bottom' => 10,
				'margin_left'   => 10,
				'margin_right'  => 10,
				'tempDir'       => '/tmp',
			]);

			$css = file_get_contents(__DIR__ . '/../../css/style.css');
			$mpdf->WriteHTML($css,  \Mpdf\HTMLParserMode::HEADER_CSS);
			$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
			$pdfContent = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

			// 3. Calcul des montants depuis les lignes de produits
			$produitsRows = json_decode(
				$this->myDb->getListProduit($facture->id_devis, $this->session->get('CurrentCompany'))
			);

			$vatLines = [];
			$totalHT  = 0.0;

			foreach ($produitsRows as $p) {
				$lineTotal = (float)$p->prix_unitaire * (float)$p->quantite;
				$totalHT  += $lineTotal;
				$vatRate   = isset($p->tva) ? (float)$p->tva : 20.0;
				$key       = number_format($vatRate, 2);
				if (!isset($vatLines[$key])) {
					$vatLines[$key] = ['rate' => $vatRate, 'base' => 0.0, 'amount' => 0.0];
				}
				$vatLines[$key]['base']   += $lineTotal;
				$vatLines[$key]['amount'] += $lineTotal * $vatRate / 100;
			}

			$totalVAT = array_sum(array_column($vatLines, 'amount'));
			$totalTTC = $totalHT + $totalVAT;

			// 4. Construction du XML Factur-X (CrossIndustryInvoice / EN 16931)
			$invoiceDate = new \DateTime($facture->date);
			$dueDate     = new \DateTime($facture->date_paiement);

			// 5. Construction du XML Factur-X (CrossIndustryInvoice / EN 16931)
			$invoiceDateFormatted = $invoiceDate->format('Ymd');
			$dueDateFormatted     = $dueDate->format('Ymd');

			$sellerVatId = '';
			if (!empty($config->siret)) {
				// Numéro de TVA intracommunautaire : FR + clé (2 chiffres) + SIREN (9 chiffres)
				// Si le champ contient déjà "FR", on l'utilise tel quel, sinon on préfixe
				$sellerVatId = (strpos($config->siret, 'FR') === 0)
					? $config->siret
					: 'FR00' . preg_replace('/[^0-9]/', '', $config->siret);
			}

			// Lignes XML des items
			$lineItemsXml = '';
			$lineNum = 1;
			foreach ($produitsRows as $p) {
				$lineTotal  = round((float)$p->prix_unitaire * (float)$p->quantite, 4);
				$vatRate    = isset($p->tva) ? (float)$p->tva : 20.0;
				$designation = htmlspecialchars($p->description ?? $p->reference ?? '', ENT_XML1);
				$lineItemsXml .= <<<XML

	<ram:IncludedSupplyChainTradeLineItem>
		<ram:AssociatedDocumentLineDocument>
			<ram:LineID>{$lineNum}</ram:LineID>
		</ram:AssociatedDocumentLineDocument>
		<ram:SpecifiedTradeProduct>
			<ram:Name>{$designation}</ram:Name>
		</ram:SpecifiedTradeProduct>
		<ram:SpecifiedLineTradeAgreement>
			<ram:NetPriceProductTradePrice>
				<ram:ChargeAmount>{$p->prix_unitaire}</ram:ChargeAmount>
			</ram:NetPriceProductTradePrice>
		</ram:SpecifiedLineTradeAgreement>
		<ram:SpecifiedLineTradeDelivery>
			<ram:BilledQuantity unitCode="C62">{$p->quantite}</ram:BilledQuantity>
		</ram:SpecifiedLineTradeDelivery>
		<ram:SpecifiedLineTradeSettlement>
			<ram:ApplicableTradeTax>
				<ram:TypeCode>VAT</ram:TypeCode>
				<ram:CategoryCode>S</ram:CategoryCode>
				<ram:RateApplicablePercent>{$vatRate}</ram:RateApplicablePercent>
			</ram:ApplicableTradeTax>
			<ram:SpecifiedTradeMonetarySummation>
				<ram:LineTotalAmount>{$lineTotal}</ram:LineTotalAmount>
			</ram:SpecifiedTradeMonetarySummation>
		</ram:SpecifiedLineTradeSettlement>
	</ram:IncludedSupplyChainTradeLineItem>
XML;
				$lineNum++;
			}

			// Blocs TVA
			$taxXml = '';
			foreach ($vatLines as $vl) {
				$taxXml .= <<<XML

	<ram:ApplicableTradeTax>
		<ram:CalculatedAmount>{$vl['amount']}</ram:CalculatedAmount>
		<ram:TypeCode>VAT</ram:TypeCode>
		<ram:BasisAmount>{$vl['base']}</ram:BasisAmount>
		<ram:CategoryCode>S</ram:CategoryCode>
		<ram:RateApplicablePercent>{$vl['rate']}</ram:RateApplicablePercent>
	</ram:ApplicableTradeTax>
XML;
			}

			$sellerName    = htmlspecialchars($config->entreprise ?? '', ENT_XML1);
			$sellerAddress = htmlspecialchars($config->adresse    ?? '', ENT_XML1);
			$sellerCity    = htmlspecialchars($config->ville       ?? '', ENT_XML1);
			$sellerZip     = htmlspecialchars($config->codepostal  ?? '', ENT_XML1);
			$sellerCountry = htmlspecialchars($config->pays        ?? 'FR', ENT_XML1);
			$buyerName     = htmlspecialchars(trim(($facture->prenom ?? '') . ' ' . ($facture->nom ?? '')), ENT_XML1);
			$invoiceNum    = htmlspecialchars($facture->num, ENT_XML1);
			$paymentMeans  = htmlspecialchars($facture->type_paiement ?? '', ENT_XML1);

			$totalHTFmt  = number_format($totalHT,  2, '.', '');
			$totalVATFmt = number_format($totalVAT, 2, '.', '');
			$totalTTCFmt = number_format($totalTTC, 2, '.', '');

			$facturxXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice
	xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
	xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100"
	xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
	xmlns:xs="http://www.w3.org/2001/XMLSchema"
	xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
	<rsm:ExchangedDocumentContext>
		<ram:GuidelineSpecifiedDocumentContextParameter>
			<ram:ID>urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:en16931</ram:ID>
		</ram:GuidelineSpecifiedDocumentContextParameter>
	</rsm:ExchangedDocumentContext>
	<rsm:ExchangedDocument>
		<ram:ID>{$invoiceNum}</ram:ID>
		<ram:TypeCode>380</ram:TypeCode>
		<ram:IssueDateTime>
			<udt:DateTimeString format="102">{$invoiceDateFormatted}</udt:DateTimeString>
		</ram:IssueDateTime>
	</rsm:ExchangedDocument>
	<rsm:SupplyChainTradeTransaction>
		{$lineItemsXml}
		<ram:ApplicableHeaderTradeAgreement>
			<ram:SellerTradeParty>
				<ram:Name>{$sellerName}</ram:Name>
				<ram:PostalTradeAddress>
					<ram:PostcodeCode>{$sellerZip}</ram:PostcodeCode>
					<ram:LineOne>{$sellerAddress}</ram:LineOne>
					<ram:CityName>{$sellerCity}</ram:CityName>
					<ram:CountryID>{$sellerCountry}</ram:CountryID>
				</ram:PostalTradeAddress>
				<ram:SpecifiedTaxRegistration>
					<ram:ID schemeID="VA">{$sellerVatId}</ram:ID>
				</ram:SpecifiedTaxRegistration>
			</ram:SellerTradeParty>
			<ram:BuyerTradeParty>
				<ram:Name>{$buyerName}</ram:Name>
			</ram:BuyerTradeParty>
		</ram:ApplicableHeaderTradeAgreement>
		<ram:ApplicableHeaderTradeDelivery/>
		<ram:ApplicableHeaderTradeSettlement>
			<ram:PaymentReference>{$invoiceNum}</ram:PaymentReference>
			<ram:TaxCurrencyCode>EUR</ram:TaxCurrencyCode>
			<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>
			<ram:SpecifiedTradePaymentTerms>
				<ram:Description>{$paymentMeans}</ram:Description>
				<ram:DueDateDateTime>
					<udt:DateTimeString format="102">{$dueDateFormatted}</udt:DateTimeString>
				</ram:DueDateDateTime>
			</ram:SpecifiedTradePaymentTerms>
			{$taxXml}
			<ram:SpecifiedTradeSettlementHeaderMonetarySummation>
				<ram:LineTotalAmount>{$totalHTFmt}</ram:LineTotalAmount>
				<ram:TaxBasisTotalAmount>{$totalHTFmt}</ram:TaxBasisTotalAmount>
				<ram:TaxTotalAmount currencyID="EUR">{$totalVATFmt}</ram:TaxTotalAmount>
				<ram:GrandTotalAmount>{$totalTTCFmt}</ram:GrandTotalAmount>
				<ram:DuePayableAmount>{$totalTTCFmt}</ram:DuePayableAmount>
			</ram:SpecifiedTradeSettlementHeaderMonetarySummation>
		</ram:ApplicableHeaderTradeSettlement>
	</rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;

			// 6. Embed du XML dans le PDF via notre Writer surchargé
			// (contourne le blocage de simplexml_load_file par Nextcloud)
			$writer            = new GestionFacturXWriter();
			$facturxPdfContent = $writer->generate($pdfContent, $facturxXml, null, false);

			// 6. Sauvegarde dans Nextcloud
			$cleanFolder = html_entity_decode($folder);
			$cleanName   = html_entity_decode($name);

			try { $this->storage->newFolder($cleanFolder); } catch (\OCP\Files\NotPermittedException $e) {}
			try {
				$filePath = $cleanFolder . $cleanName;
				$this->storage->newFile($filePath);
				$file = $this->storage->get($filePath);
				$file->putContent($facturxPdfContent);
			} catch (\OCP\Files\NotFoundException $e) {}

			// 7. Retour du fichier au navigateur pour téléchargement
			return new DataDownloadResponse(
				$facturxPdfContent,
				$cleanName,
				'application/pdf'
			);

		} catch (\Exception $e) {
			return new DataResponse([
				'status'  => 'error',
				'message' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * Génère et retourne uniquement le XML Factur-X (CII EN 16931),
	 * sans PDF. Utile pour dépôt direct sur Chorus Pro ou autre plateforme.
	 *
	 * @param int    $factureId ID de la facture en base
	 * @param string $name      Nom de fichier souhaité (sans extension)
	 * @param string $folder    Dossier Nextcloud de destination
	 */
	#[UseSession]
	public function generateFacturXml(int $factureId, string $name, string $folder) {
		try {
			// 1. Récupération des données
			$factureRows = json_decode($this->myDb->getOneFacture($factureId, $this->session->get('CurrentCompany')));
			$configRows  = json_decode($this->getConfiguration());

			if (empty($factureRows) || empty($configRows)) {
				return new DataResponse(['status' => 'error', 'message' => 'Facture ou configuration introuvable.'], 404);
			}

			$facture = $factureRows[0];
			$config  = $configRows[0];

			// 2. Calcul des montants
			$produitsRows = json_decode(
				$this->myDb->getListProduit($facture->id_devis, $this->session->get('CurrentCompany'))
			);

			$vatLines = [];
			$totalHT  = 0.0;

			foreach ($produitsRows as $p) {
				$lineTotal = (float)$p->prix_unitaire * (float)$p->quantite;
				$totalHT  += $lineTotal;
				$vatRate   = isset($p->tva) ? (float)$p->tva : 20.0;
				$key       = number_format($vatRate, 2);
				if (!isset($vatLines[$key])) {
					$vatLines[$key] = ['rate' => $vatRate, 'base' => 0.0, 'amount' => 0.0];
				}
				$vatLines[$key]['base']   += $lineTotal;
				$vatLines[$key]['amount'] += $lineTotal * $vatRate / 100;
			}

			$totalVAT = array_sum(array_column($vatLines, 'amount'));
			$totalTTC = $totalHT + $totalVAT;

			// 3. Construction du XML CII EN 16931
			$invoiceDate          = new \DateTime($facture->date);
			$dueDate              = new \DateTime($facture->date_paiement);
			$invoiceDateFormatted = $invoiceDate->format('Ymd');
			$dueDateFormatted     = $dueDate->format('Ymd');

			$sellerVatId = '';
			if (!empty($config->siret)) {
				$sellerVatId = (strpos($config->siret, 'FR') === 0)
					? $config->siret
					: 'FR00' . preg_replace('/[^0-9]/', '', $config->siret);
			}

			$lineItemsXml = '';
			$lineNum = 1;
			foreach ($produitsRows as $p) {
				$lineTotal   = round((float)$p->prix_unitaire * (float)$p->quantite, 4);
				$vatRate     = isset($p->tva) ? (float)$p->tva : 20.0;
				$designation = htmlspecialchars($p->description ?? $p->reference ?? '', ENT_XML1);
				$lineItemsXml .= <<<XML

	<ram:IncludedSupplyChainTradeLineItem>
		<ram:AssociatedDocumentLineDocument>
			<ram:LineID>{$lineNum}</ram:LineID>
		</ram:AssociatedDocumentLineDocument>
		<ram:SpecifiedTradeProduct>
			<ram:Name>{$designation}</ram:Name>
		</ram:SpecifiedTradeProduct>
		<ram:SpecifiedLineTradeAgreement>
			<ram:NetPriceProductTradePrice>
				<ram:ChargeAmount>{$p->prix_unitaire}</ram:ChargeAmount>
			</ram:NetPriceProductTradePrice>
		</ram:SpecifiedLineTradeAgreement>
		<ram:SpecifiedLineTradeDelivery>
			<ram:BilledQuantity unitCode="C62">{$p->quantite}</ram:BilledQuantity>
		</ram:SpecifiedLineTradeDelivery>
		<ram:SpecifiedLineTradeSettlement>
			<ram:ApplicableTradeTax>
				<ram:TypeCode>VAT</ram:TypeCode>
				<ram:CategoryCode>S</ram:CategoryCode>
				<ram:RateApplicablePercent>{$vatRate}</ram:RateApplicablePercent>
			</ram:ApplicableTradeTax>
			<ram:SpecifiedTradeMonetarySummation>
				<ram:LineTotalAmount>{$lineTotal}</ram:LineTotalAmount>
			</ram:SpecifiedTradeMonetarySummation>
		</ram:SpecifiedLineTradeSettlement>
	</ram:IncludedSupplyChainTradeLineItem>
XML;
				$lineNum++;
			}

			$taxXml = '';
			foreach ($vatLines as $vl) {
				$taxXml .= <<<XML

	<ram:ApplicableTradeTax>
		<ram:CalculatedAmount>{$vl['amount']}</ram:CalculatedAmount>
		<ram:TypeCode>VAT</ram:TypeCode>
		<ram:BasisAmount>{$vl['base']}</ram:BasisAmount>
		<ram:CategoryCode>S</ram:CategoryCode>
		<ram:RateApplicablePercent>{$vl['rate']}</ram:RateApplicablePercent>
	</ram:ApplicableTradeTax>
XML;
			}

			$sellerName    = htmlspecialchars($config->entreprise ?? '', ENT_XML1);
			$sellerAddress = htmlspecialchars($config->adresse    ?? '', ENT_XML1);
			$sellerCity    = htmlspecialchars($config->ville       ?? '', ENT_XML1);
			$sellerZip     = htmlspecialchars($config->codepostal  ?? '', ENT_XML1);
			$sellerCountry = htmlspecialchars($config->pays        ?? 'FR', ENT_XML1);
			$buyerName     = htmlspecialchars(trim(($facture->prenom ?? '') . ' ' . ($facture->nom ?? '')), ENT_XML1);
			$invoiceNum    = htmlspecialchars($facture->num, ENT_XML1);
			$paymentMeans  = htmlspecialchars($facture->type_paiement ?? '', ENT_XML1);

			$totalHTFmt  = number_format($totalHT,  2, '.', '');
			$totalVATFmt = number_format($totalVAT, 2, '.', '');
			$totalTTCFmt = number_format($totalTTC, 2, '.', '');

			$facturxXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice
	xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
	xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100"
	xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
	xmlns:xs="http://www.w3.org/2001/XMLSchema"
	xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
	<rsm:ExchangedDocumentContext>
		<ram:GuidelineSpecifiedDocumentContextParameter>
			<ram:ID>urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:en16931</ram:ID>
		</ram:GuidelineSpecifiedDocumentContextParameter>
	</rsm:ExchangedDocumentContext>
	<rsm:ExchangedDocument>
		<ram:ID>{$invoiceNum}</ram:ID>
		<ram:TypeCode>380</ram:TypeCode>
		<ram:IssueDateTime>
			<udt:DateTimeString format="102">{$invoiceDateFormatted}</udt:DateTimeString>
		</ram:IssueDateTime>
	</rsm:ExchangedDocument>
	<rsm:SupplyChainTradeTransaction>
		{$lineItemsXml}
		<ram:ApplicableHeaderTradeAgreement>
			<ram:SellerTradeParty>
				<ram:Name>{$sellerName}</ram:Name>
				<ram:PostalTradeAddress>
					<ram:PostcodeCode>{$sellerZip}</ram:PostcodeCode>
					<ram:LineOne>{$sellerAddress}</ram:LineOne>
					<ram:CityName>{$sellerCity}</ram:CityName>
					<ram:CountryID>{$sellerCountry}</ram:CountryID>
				</ram:PostalTradeAddress>
				<ram:SpecifiedTaxRegistration>
					<ram:ID schemeID="VA">{$sellerVatId}</ram:ID>
				</ram:SpecifiedTaxRegistration>
			</ram:SellerTradeParty>
			<ram:BuyerTradeParty>
				<ram:Name>{$buyerName}</ram:Name>
			</ram:BuyerTradeParty>
		</ram:ApplicableHeaderTradeAgreement>
		<ram:ApplicableHeaderTradeDelivery/>
		<ram:ApplicableHeaderTradeSettlement>
			<ram:PaymentReference>{$invoiceNum}</ram:PaymentReference>
			<ram:TaxCurrencyCode>EUR</ram:TaxCurrencyCode>
			<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>
			<ram:SpecifiedTradePaymentTerms>
				<ram:Description>{$paymentMeans}</ram:Description>
				<ram:DueDateDateTime>
					<udt:DateTimeString format="102">{$dueDateFormatted}</udt:DateTimeString>
				</ram:DueDateDateTime>
			</ram:SpecifiedTradePaymentTerms>
			{$taxXml}
			<ram:SpecifiedTradeSettlementHeaderMonetarySummation>
				<ram:LineTotalAmount>{$totalHTFmt}</ram:LineTotalAmount>
				<ram:TaxBasisTotalAmount>{$totalHTFmt}</ram:TaxBasisTotalAmount>
				<ram:TaxTotalAmount currencyID="EUR">{$totalVATFmt}</ram:TaxTotalAmount>
				<ram:GrandTotalAmount>{$totalTTCFmt}</ram:GrandTotalAmount>
				<ram:DuePayableAmount>{$totalTTCFmt}</ram:DuePayableAmount>
			</ram:SpecifiedTradeSettlementHeaderMonetarySummation>
		</ram:ApplicableHeaderTradeSettlement>
	</rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;

			// 4. Sauvegarde du XML dans Nextcloud
			$cleanFolder = html_entity_decode($folder);
			$xmlFileName = html_entity_decode($name);

			try { $this->storage->newFolder($cleanFolder); } catch (\OCP\Files\NotPermittedException $e) {}
			try {
				$filePath = $cleanFolder . $xmlFileName;
				$this->storage->newFile($filePath);
				$file = $this->storage->get($filePath);
				$file->putContent($facturxXml);
			} catch (\OCP\Files\NotFoundException $e) {}

			// 5. Retour du fichier XML au navigateur
			return new DataDownloadResponse(
				$facturxXml,
				$xmlFileName,
				'application/xml'
			);

		} catch (\Exception $e) {
			return new DataResponse([
				'status'  => 'error',
				'message' => $e->getMessage(),
			], 500);
		}
	}

	private function getLogo($name){
		try {
			if(isset($this->storage)){
				$file = $this->storage->get('/.gestion/'.$name);
			}else{
				return "nothing";
			}
		} catch(\OCP\Files\NotFoundException $e) {
			return "nothing";
		}

		return base64_encode($file->getContent());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getStats(){
		$res = array();
		$res['client'] = json_decode($this->myDb->numberClient($this->session->get('CurrentCompany')))[0]->c;
		$res['devis'] = json_decode($this->myDb->numberDevis($this->session->get('CurrentCompany')))[0]->c;
		$res['facture'] = json_decode($this->myDb->numberFacture($this->session->get('CurrentCompany')))[0]->c;
		$res['produit'] = json_decode($this->myDb->numberProduit($this->session->get('CurrentCompany')))[0]->c;
		return json_encode($res);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	*/
	#[UseSession]
	public function getAnnualTurnoverPerMonthNoVat(){
		return $this->myDb->getAnnualTurnoverPerMonthNoVat($this->session->get('CurrentCompany'));
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 * @param string $html
	 * @param string $name
	 * @param string $folder
	 * @return void
	*/
	#[UseSession]
	public function generatePDF($html, $name, $folder) {

			try {

					$mpdf = new Mpdf([
							'mode' => 'utf-8',
							'format' => 'A4',
							'margin_top' => 10,
							'margin_bottom' => 10,
							'margin_left' => 10,
							'margin_right' => 10,
							'tempDir' => '/tmp'
					]);

					$css = file_get_contents(
							__DIR__ . '/../../css/style.css'
					);

					$mpdf->WriteHTML(
							$css,
							\Mpdf\HTMLParserMode::HEADER_CSS
					);

					$mpdf->WriteHTML(
							$html,
							\Mpdf\HTMLParserMode::HTML_BODY
					);

					$pdfContent = $mpdf->Output(
							'',
							\Mpdf\Output\Destination::STRING_RETURN
					);

					$encoded = base64_encode($pdfContent);

					$this->savePDF(
							$encoded,
							$folder,
							$name
					);

					return new DataDownloadResponse(
							$pdfContent,
							$name . '.pdf',
							'application/pdf'
					);

			} catch (\Mpdf\MpdfException $e) {

					return new DataResponse([
							'status' => 'error',
							'message' => $e->getMessage()
					], 500);
			}
	}
}