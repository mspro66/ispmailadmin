<?php
/**
 **
 **
 ** @package    ISPmail_Admin
 ** @author     Ole Jungclaussen
 ** @version    0.9.11
 **/
require_once('inc/defs.inc.php');
require_once('cfg/config.inc.php');
require_once('inc/lib.inc.php');
require_once('inc/HtmlPage.inc.php');
// Version
define('IMA_VERSION_STR', "0.9.11");
define('IMA_REVISION_STR', "1.1");

/**
 ** @public
 **/
class IspMailAdminApp
{
   /**
    **
    ** @var object $Instance
    **/
   protected static $Instance = false;
   /**
    **
    ** @type HtmlPage
    **/
   public $Page = false;
   /**
    **
    ** @type array
    **/
   public $aReqParam = array();
   /**
    **
    ** @type Database
    **/
   public $DB = false;
   /**
    * Logged-in user
    *      < 0: the admin
    *      > 0: virtual_users.id
    * @type integer
    */
   public $iIdUser = 0;
   /**
    * @var int ID of selected domain for managing
    */
   public int $iIdDomSel = 0;
   /**
    ** @var string Name of selected domain reference to {@see $iIdDomSel}
    */
   public string $sDomSel = '';
   /**
    **
    ** @type integer
    **/
   public $iIDuserAccountSelected = 0;

   /**
    * @var string User account selected in webpage
    */
   public $sUserAccountSelected = '';

   /**
    * @var string
    **/
   public $sIdPage = '';

   /**
    * @var array State of the overview page.
    */
   public $aOvrStat = array(
      // Sorting of the overview.
      'sSort'    => 'sSrcUser ASC, sSrcDomain ASC, sTarUser ASC, sTarDomain ASC'
      // pagination
      ,
      'iIdxPage' => null,
      'iShowMax' => 65535
   );

   /**
    * @var array State of the domain page
    */
   public $aDomStat = array(
      // pagination
      'iIdxPage' => null,
      'iShowMax' => 65535
   );

   /**
    * @var array State of the accounts page.
    */
   public $aAccStat = array(
      // pagination
      'iIdxPage' => null,
      'iShowMax' => 65535
   );

   /**
    * State of the alias page.
    * @type array
    */
   public $aAlsStat = array(
      // pagination
      'iIdxPage' => null,
      'iShowMax' => 65535
   );

   /**
    * State of the redirect page.
    * @type array
    */
   public $aRedStat = array(
      // pagination
      'iIdxPage' => null,
      'iShowMax' => 65535
   );

   // ########## PROPS PROTECTED

   /**
    * @type array
    */
   protected $aPages;

   function __construct ()
   {
      $this->aPages = array(
         'page_welcome'   => array(
            'aAccess' => array('a'),
            'sMenu'   => 'Home'
         ),
         'page_overview'  => array(
            'aAccess' => array('a'),
            'sMenu'   => 'Overview'
         ),
         'page_domains'   => array(
            'aAccess' => array('a'),
            'sMenu'   => 'Domains'
         ),
         'page_accounts'  => array(
            'aAccess' => array('a'),
            'sMenu'   => 'Accounts'
         ),
         'page_aliases'   => array(
            'aAccess' => array(
               'a',
               'u'
            ),
            'sMenu'   => 'Aliases'
         ),
         'page_redirects' => array(
            'aAccess' => array('a'),
            'sMenu'   => 'Redirects'
         )
      );

      if (defined('IMA_SUPPORT_BLACKLIST'))
      {
         $this->aPages['page_blacklist'] = array(
            'aAccess' => array('a'),
            'sMenu'   => 'Blacklist'
         );
      }

      if (defined('IMA_LIST_MAX_ENTRIES'))
      {
         $this->aOvrStat['iShowMax'] = IMA_LIST_MAX_ENTRIES;
         $this->aDomStat['iShowMax'] = IMA_LIST_MAX_ENTRIES;
         $this->aAccStat['iShowMax'] = IMA_LIST_MAX_ENTRIES;
         $this->aAlsStat['iShowMax'] = IMA_LIST_MAX_ENTRIES;
         $this->aRedStat['iShowMax'] = IMA_LIST_MAX_ENTRIES;
      }
   }

// ########## METHOD PUBLIC

   /**
    ** @return string Name of App
    **/
   public static function getVersion (): string
   {
      return (IMA_VERSION_STR);
   }

   public static function getRevision (): string
   {
      return (IMA_REVISION_STR);
   }

   /**
    * @retval integer
    * @returns !=0 on error
    */
   public function startScript ()
   {
      $iErr = 0;

      if (false === ($this->Page = new HtmlPage($this))) ;
      else if (0 != ($iErr = $this->initDatabase())) ;
      else if (0 != ($iErr = $this->handleParams())) ;
      else if (0 != $this->iIdUser && false === $this->regenerateSessionId()) ;
      // (AUTO)LOGIN USER
      else if (!$this->isLoggedIn() && 0 != ($iErr = $this->login())) ;

      return ($iErr);
   }

   /**
    * @retval integer
    * @returns !=0 on error
    */
   protected function initDatabase ()
   {
      require_once('inc/Database_mysqli.inc.php');
      $iErr = 0;
      if (false === ($this->DB = new Database(IMA_CFG_DB_HOST, IMA_CFG_DB_PORT, IMA_CFG_DB_SOCKET, IMA_CFG_DB_DATABASE, IMA_CFG_DB_USER, IMA_CFG_DB_PASSWORD))) ; // rhetoric
      else if (0 != ($iErr = $this->DB->connect())) ;
      return ($iErr);
   }

   /**
    * @retval integer
    * @returns !=0 on error
    */
   protected function handleParams ()
   {
      $iErr = 0;
      foreach (array_keys($_GET) as $sKey) $this->handleParam($_GET, $sKey);
      foreach (array_keys($_POST) as $sKey) $this->handleParam($_POST, $sKey);
      foreach (array_keys($_FILES) as $sKey)
      {
         $this->aReqParam[$sKey] = $_FILES[$sKey];
         unset($_FILES[$sKey]);
      }
      return ($iErr);
   }

   /**
    * @retval integer
    * @returns !=0 on error
    */
   protected function handleParam (&$aGetPost, &$sKey)
   {
      // treat a password parameter special
      if (preg_match('/^pwd_/', $sKey))
      {
         if (0 == strlen($this->aReqParam[$sKey] = trim($aGetPost[$sKey]))) ;
         else $this->aReqParam[$sKey] = $this->makePwd_DbHash($aGetPost[$sKey]);
      }
      // sanitize all others
      else $this->aReqParam[$sKey] = lib\sanitizeParam($aGetPost[$sKey]);
      unset($aGetPost[$sKey]);
      return (0);
   }

   /**
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function makePwd_DbHash ($sPwdPlain)
   {
      $sPwdHash = '';
      if (defined('IMA_CFG_USE_MD5_HASHES'))
      {
         $sPwdHash = '{PLAIN-MD5}' . md5($sPwdPlain);
      }
      else if (defined('IMA_CFG_USE_SHA256_HASHES'))
      {
         $sRand    = strval(rand());
         $sRandSh1 = sha1($sRand);
         $sSalt    = substr($sRandSh1, -16);
         $sPwdHash = '{SHA256-CRYPT}' . crypt($sPwdPlain, '$5$' . $sSalt . '$');
      }
      else
      {
         $sPwdHash = '{BLF-CRYPT}' . password_hash($sPwdPlain, PASSWORD_BCRYPT);
      }
      return ($sPwdHash);
   }

   /**
    * When you have properties that fail to serialize (e.g. SQlite3), you can use
    *   $tmpProp = $this->tmpProp;
    *   $this->tmpProp = null;
    *   $bReturn = session_regenerate_id();
    *   $this->tmpProp = $tmpProp;
    *   return $bReturn;
    */
   protected function regenerateSessionId ()
   {
      return session_regenerate_id();
   }

   /**
    ** @retval boolean
    ** @returns true if user is logged in
    **/
   public function isLoggedIn ()
   {
      return (0 != $this->iIdUser);
   }

   /**
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function login ()
   {
      $iErr = 0;

      if (!defined('IMA_CFG_LOGIN')) ;
      // single user and login disabled (e.g. when protected by .htaccess/.htpwd)
      else if (IMA_CFG_LOGIN == IMA_LOGINTYPE_ADMAUTO)
      {
         $this->iIdUser = -1;
         $this->sIdPage = 'page_welcome';
         $this->regenerateSessionId();
         $bRetVal = true;
      }
      else if (isset($this->aReqParam['loginform']))
      {
         if (0 != ($iErr = lib\verifyParam($bOk, 'sloginuser', 'string', 'preg_match("/^[^\r\n\t]+$/", $$$)')) || !$bOk) ;
         // is config.php user
         else if (($this->aReqParam['sloginuser'] == IMA_CFG_ADM_USER) && ($this->aReqParam['sloginpass'] === IMA_CFG_ADM_PASS))
         {
            $this->iIdUser = -1;
            $this->sIdPage = 'page_welcome';
            $this->regenerateSessionId();
         }
         // multiuser allowed: check virtual_users
         else if (IMA_CFG_LOGIN == IMA_LOGINTYPE_ACCOUNT)
         {
            require_once('inc/EmailAccounts.inc.php');
            $iIdAcc = 0;
            if (0 != ($iErr = EmailAccounts::loginAccount($this, $iIdAcc, $this->aReqParam['sloginuser'], $this->aReqParam['sloginpass']))) ;
            else if (0 == $iIdAcc) ;
            else
            {
               $this->iIdUser = $iIdAcc;
               $this->sIdPage = 'page_aliases';
            }
         }

         if (0 == $this->iIdUser) lib\ErrLog::getInstance()->push("Unknown username/password.");
      }
      return ($iErr);
   }

   /**
    ** @retval integer
    ** @returns !=0 on error
    **/
   public static function getInstance ()
   {
      if (false === self::initSession(self::getName())) ;
      else if (self::$Instance) ;
      else if (isset($_SESSION['Sess']))
      {
         self::$Instance = &$_SESSION['Sess'];
      }
      else
      {
         self::$Instance   = new IspMailAdminApp();
         $_SESSION['Sess'] = &self::$Instance;
      }
      return (self::$Instance);
   }
// ########## METHOD PROTECTED

   /**
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected static function initSession ($sAppName)
   {
      session_cache_limiter('nocache');
      session_name(preg_replace('/[^a-z0-9_]+/i', '_', $sAppName));
      session_set_cookie_params(0, dirname($_SERVER['PHP_SELF']), '', false, true);
      return (session_start());
   }

   /**
    ** @return string Name of App
    **/
   public static function getName ()
   {
      return ('ISPmail Admin');
   }

   /**
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function endScript ()
   {
      $iErr            = 0;
      $this->aReqParam = array();
      $this->Page      = false;
      $this->DB->close();
      $this->DB = false;
      return ($iErr);
   }

   /**
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawPage ()
   {
      $iErr = 0;

      if (isset($this->aReqParam['iddomain']))
      {
         $this->iIdDomSel = intval($this->aReqParam['iddomain']);
         $this->sDomSel   = $this->getDomainName($this->iIdDomSel);
         $this->iIDuserAccountSelected = 0;
      }

      // LOGIN FORM
      if (!$this->isLoggedIn())
      {
         $iErr = $this->drawLoginForm();
      }
      // MENU
      else if (0 != ($iErr = $this->drawMenu())) ;
      // DOMAIN COMBO
      else if (0 != ($iErr = $this->drawCbDomains())) ;
      // CONTENT
      else switch ($this->sIdPage)
      {
         case 'page_welcome':
            if (0 != ($iErr = $this->Page->setTitle('Welcome'))) ;
            else if (0 != ($iErr = $this->Page->setHelp(<<<HTM
                    <ul>
                        <li><b>Domains</b>: Add and remove domains (<i>example.com</i>) handled by this mailserver</li>
                        <li><b>Accounts</b>: Add and remove real mail-boxes accounts (<i>user1@example.com</i>) with user and passwords</li>
                        <li><b>Aliases</b>: Add and remove aliases to existing mail-box (<i>info@example.com</i> as an alias of <i>user1@example.com</i>)</li>
                        <li><b>Redirects</b>: Add and remove domain redirects (forward emails for <i>not.an.account@example.com</i> to <i>somebody.else@over.there.com</i>)</li>
                     </ul>
               HTM ))) ;
            else if (0 != ($iErr = $this->Page->addBody(<<<HTM
                    <noscript>
                        <div class="MsgError">Please enable Javascript!</div></noscript>
                        <h3>{$this->getName()} is an administrative panel for small or private "Mail Service Provider"</h3>
                        <ul class="welcome">
                            <li class="welcome">This is a revised version of the "ISPmail Admin" software originally made by Ole Jungclaussen</li>
                            <li class="welcome">The changes made to this software were developed by <a href="https://github.com/mspro66" target="_blank">&lt;/mspro66&gt;</a></li>
                            <li class="welcome">The database structure used is the same as that proposed by the fantastic 
                                    <a href="https://workaround.org/ispmail-trixie/"  target="_blank"><i>ISPmail guide</i></a> with a small integration</li>
                            <li class="welcome">If you want to know the changes and integrations made in this version of the software <a href="https://github.com/mspro66" target="_blank">click here</a>.</li>' . '<li class="welcome">Any suggestions or reports about this version of the software, you can do it on the 
                                    <a href="https://github.com/mspro66" target="_blank">GitHub project page</a></li>
                        </ul>
               HTM ))) ;
            break;

         case 'page_overview':
            require_once('inc/EmailOverview.inc.php');
            $EOvr = new EmailOverview($this);

            if (0 != ($iErr = $EOvr->setTitleAndHelp($this->Page))) ;
            else if (0 != ($iErr = $EOvr->processCmd())) ;
            else if (0 != ($iErr = $EOvr->drawCreate($this->Page))) ;
            else if (0 != ($iErr = $EOvr->drawList($this->Page))) ;
            break;

         case 'page_domains':
            require_once('inc/EmailDomains.inc.php');
            $EDom = new EmailDomains($this);

            if (0 != ($iErr = $EDom->setTitleAndHelp($this->Page))) ;
            else if (0 != ($iErr = $EDom->processCmd())) ;
            else if (0 != ($iErr = $EDom->drawCreate($this->Page))) ;
            else if (0 != ($iErr = $EDom->drawList($this->Page))) ;
            break;

         case 'page_accounts':
            require_once('inc/EmailDomains.inc.php');
            require_once('inc/EmailAccounts.inc.php');

            $EAcc = new EmailAccounts($this);

            if (0 != ($iErr = $EAcc->setTitleAndHelp($this->Page))) ;
            else if (0 == $this->iIdDomSel) ;
            else if (0 != ($iErr = $EAcc->processCmd())) ;
            // also verifies (and possibly changes) $this->iIdDomSel
            //else if (0 != ($iErr = $EDom->drawSelect($this->Page, $this->iIdDomSel, $this->sDomSel))) ;
            else if (0 != ($iErr = $EAcc->drawCreate($this->Page))) ;
            else if (0 != ($iErr = $EAcc->drawList($this->Page))) ;
            break;

         case 'page_aliases':
            require_once('inc/EmailDomains.inc.php');
            require_once('inc/EmailAliases.php');
            require_once('inc/EmailAccounts.inc.php');
            $EDom = new EmailDomains($this);
            //$EAcc   = new EmailAccounts($this, $EDom);
            $EAcc   = new EmailAccounts($this);
            $EAlias = new EmailAliases($this, $EDom, $EAcc);

            //$this->iIdAccSel = 0;
            if (isset($this->aReqParam['idaccount']))
               $this->iIDuserAccountSelected = intval($this->aReqParam['idaccount']);

            //read email of account selected
            if ($this->iIDuserAccountSelected > 0)
            {
               $an = $EAcc->getAccountEmail($this->iIDuserAccountSelected);
               if (is_string($an))
                  $this->sUserAccountSelected = $an;
            }

            if (0 != ($iErr = $EAlias->setTitleAndHelp($this->Page))) ;
            //verifiy if domain is selected
            else if (0 == $this->iIdDomSel) ;
            else if (0 != ($iErr = $EAlias->processCmd())) ;
            // also verifies (and possibly changes) $this->iIdAccSel
            else if (0 != ($iErr = $EAlias->drawSelectMailbox($this->Page))) ;
            else if (0 == $this->iIDuserAccountSelected) ;
            else if (0 != ($iErr = $EAlias->drawCreate($this->Page))) ;
            else if (0 != ($iErr = $EAlias->drawList($this->Page))) ;
            break;

         case 'page_redirects':
            require_once('inc/EmailDomains.inc.php');
            require_once('inc/EmailRedirects.php');
            $EDom   = new EmailDomains($this);
            $ERedir = new EmailRedirects($this, $EDom);

            if (0 != ($iErr = $ERedir->setTitleAndHelp($this->Page))) ;
            //verifiy if domain is selected
            else if (0 == $this->iIdDomSel) ;
            //set/get user-id owner of all domain redirects
            else if (0 != ($iErr = $ERedir->getUserIdOwnerRedirects($ERedir->idCurrentMboxOwner, $ERedir->nameCurrentMboxOwner, $ERedir->App->iIdDomSel))) ;
            else if (0 != ($iErr = $ERedir->processCmd())) ;
            else if (0 != ($iErr = $ERedir->drawCreate($this->Page))) ;
            else if (0 != ($iErr = $ERedir->drawList($this->Page))) ;
            break;

         case 'page_blacklist':
            require_once('inc/Blacklist.php');
            $EBl = new Blacklist($this);
            if (0 != ($iErr = $EBl->setTitleAndHelp($this->Page))) ;
            else if (0 != ($iErr = $EBl->processCmd())) ;
            else if (0 != ($iErr = $EBl->drawCreate($this->Page))) ;
            else if (0 != ($iErr = $EBl->drawList($this->Page))) ;
            break;
      }
      return ($iErr);
   }

   /**
    * @param int $domainID
    * @return string | false
    */
   protected function getDomainName (int $domainID): string
   {
      $row = null;

      if (!is_integer($domainID)) return "";

      if (0 != $this->DB->queryOneRow($row, <<<SQL
            SELECT
               name
            FROM 
               virtual_domains
            where
               id = $domainID
         SQL
         ))
      {
         lib\ErrLog::getInstance()->push(__METHOD__ . ": Error in query domain name!");
         return "";
      }
      else if (NULL === $row)
      {
         return "";
      }

      return $row['name'];
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function drawLoginForm ()
   {
      $iErr = 0;
      $helpAdmin =(IMA_CFG_LOGIN != IMA_LOGINTYPE_ACCOUNT ? '' : '<li>Or enter email-account (you@example.com) and password</li>');

      if (0 != ($iErr = $this->Page->setTitle('Login'))) ;

      $row = [];
      if (0 != ($iErr = $this->DB->queryOneRow($row, "SHOW COLUMNS FROM virtual_aliases LIKE 'mailbox_id'")))
         $this->Page->addBody('Database access issues were encountered. Check the configuration.');
      else if ($row === null)
      {
         $iErr = 1;
         $this->Page->addBody('The app extension is not active! See the readme.md file in the "installation" section and follow the configuration instructions.');
      }
      else if (!defined('IMA_CFG_LOGIN'))
      {
         $this->Page->setHelp(<<<HTM
            <ul>
                <li>For install and setup see the readme.md file</li>
            </ul>
         HTM );

         $this->Page->addBody('Sorry, but <i>' . $this->getName() . '</i> has not been configured correctly yet.');
      }
      else if (0 != ($iErr = $this->Page->setHelp(<<<HTM
            <ul>
                <li>Enter administrator's username and password</li>
                $helpAdmin
            </ul>
         HTM))) ;
      else if (0 != ($iErr = $this->Page->addBody(<<<HTM
            <div class="login_form">
                <form name=login_form" action="{$_SERVER['PHP_SELF']}" method="POST">
                  <input type="hidden" name="loginform" value="1">
                  <table>
                  <tr>
                     <td>
                        <input name="sloginuser" id="sloginuser" type="text" placeholder="Username" autofocus>
                     </td>
                  </tr>
                  <tr>
                    <td>
                        <input name="sloginpass" id="sloginpass" type="password" placeholder="Password" value="">
                    </td>
                  </tr>
                  <tr>
                     <td>
                        <input type="submit" value="Login">
                     </td>
                  </tr>
                  </table>
                </form>
            </div>
        HTM ))) ;

      return ($iErr);
   }

   /**
    * @retval integer
    * @returns !=0 on error
    */
   protected function drawMenu ()
   {
      $iErr = 0;
      /*$aEntries = array();
      foreach ($this->aPages as $sId => $aPage)
      {
         if ($this->iIdUser > 0 && !in_array('u', $aPage['aAccess'])) ;
         else if ($this->iIdUser < 0 && !in_array('a', $aPage['aAccess'])) ;
         else $aEntries[] =
            '<div class="menu_entry">'
            . '<form name="menu_page_' . $sId . '" action="' . $_SERVER['PHP_SELF'] . '" method="POST">'
            . '<input type="hidden" name="cmd" value="cmd_openPage" />'
            . '<input type="hidden" name="spage" value="' . $sId . '" />'
            . '<a class="menu_entry" onClick="menu_page_' . $sId . '.submit();">[' . $aPage['sMenu'] . ']</a>'
            . '</form>'
            . '</div>';
      }*/

      $buttons = "";
      foreach ($this->aPages as $sId => $aPage)
      {
         if ($this->iIdUser > 0 && in_array('u', $aPage['aAccess']) || $this->iIdUser < 0 && in_array('a', $aPage['aAccess']))
         {
            $buttons .= "<button onclick='menuChoice(\"$sId\")'>{$aPage['sMenu']}</button>" . PHP_EOL;
         }
      }

      if (IMA_CFG_LOGIN !== IMA_LOGINTYPE_ADMAUTO || $this->App->isLoggedIn()) $buttons .= '<button onClick="menuLogout()" title="Click here to logout"><img alt="logout icon" src="img/logout.png" /></button>';

      $this->Page->setMenu($buttons);
      //$this->Page->setMenu('<div id="page_menu">' . implode('<div class="menu_sep">&#9830;</div>', $aEntries) . '</div>');

      return ($iErr);
   }
// #####################################
// LOGIN / LOGOUT    

   /**
    * Generate html code for combobox of domains
    * @return int
    */
   protected function drawCbDomains (): int
   {
      $iErr       = 0;
      $selectedID = 0;

      if ($this->iIdUser > 0)
      {
         // if logged user is a mailbox
         $this->iIdDomSel             = $this->getDomainIdFromUserID($this->iIdUser);
         $this->sDomSel               = $this->getDomainName($this->iIdDomSel);
         $this->Page->sDomainSelector = <<<HTM
             <div class="domain_selector">
                <span id="dom-desc">Mailbox of domain:</span>&nbsp;
                <span id="don-name">{$this->sDomSel}</span>
             </div>
          HTM;
         return 0;
      }

      $opt = "";
      foreach ($this->getDomainList() as $dom)
      {
         $selectedID = (intval($dom['id']) === $this->iIdDomSel ? intval($dom['id']) : $selectedID);
         $opt        .= "<option value=\"{$dom['id']}\"" . (intval($dom['id']) === $this->iIdDomSel ? " selected=\"yes\"" : "") . ">{$dom['name']}</option>\n";
      }
      if (empty ($opt)) $opt = "<option value=\"0\">*No domains found*</option>\n" . $opt;
      else
         $opt = "<option value=\"0\">*Please select a domain*</option>\n" . $opt;

      $this->Page->sDomainSelector = <<<HTM
             <div class="domain_selector">
                <span id="dom-desc">Current domain selected:</span>&nbsp;
                <select name="iddomain" onChange="document.domain_selector.submit();">'
                    $opt
                </select>
             </div>
             HTM;

      //carica in caso di pagina di inizio
      $this->iIdDomSel = $selectedID;
      if ($selectedID > 0) $this->sDomSel = $this->getDomainName($selectedID);
      else
         $this->sDomSel = "";

      return $iErr;
   }

   protected function getDomainIdFromUserID ($userID)
   {
      $ResultRecordset = null;
      $result          = [];

      if (0 != ($err = $this->DB->query($ResultRecordset, <<<SQL
         SELECT
            domain_id AS id
         FROM 
            virtual_users
         where
            id = $userID
      SQL
         )))
      {
         lib\ErrLog::getInstance()->push('Error in query domains! errno: $err');
         return 0;
      }

      $row = null;
      if ($this->DB->fetchArray($row, $ResultRecordset, MYSQLI_ASSOC) !== null) $result = $row['id'];

      return $result;
   }
// #####################################
// DRAW ELEMENTS

   protected function getDomainList ()
   {
      $ResultRecordset = null;
      $result          = false;

      if (0 != ($err = $this->DB->query($ResultRecordset, <<<SQL
         SELECT
            domain.id AS id,
            domain.name AS name
         FROM virtual_domains AS domain
         ORDER BY domain.name ASC
      SQL
         )))
      {
         lib\ErrLog::getInstance()->push('Error in query domains!');
         return $result;
      }

      $nrow = 0;
      $this->DB->getNumRows($nrow, $ResultRecordset);
      if ($nrow == 0) return $result;

      $result = [];
      $row    = false;
      do
      {
         $this->DB->fetchArray($row, $ResultRecordset, MYSQLI_ASSOC);
         if ($row !== null) $result[] = $row;

      } while ($row !== null);

      return $result;
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function processCmd ()
   {
      $iErr = 0;
      if (!isset($this->aReqParam['cmd'])) ;
      else switch ($this->aReqParam['cmd'])
      {
         case 'cmd_logout':
            $iErr = $this->logout();
            break;
         case 'cmd_openPage':
            if (0 != ($iErr = lib\verifyParam($bOk, 'spage', 'string')) || !$bOk) ;
            else if (0 != ($iErr = $this->verifyPageAccess($bOk, $this->aReqParam['spage'])) || !$bOk) ;
            else $this->sIdPage = $this->aReqParam['spage'];
            break;
      }

      return ($iErr);
   }

   /**
    **
    **
    ** @retval boolean
    ** @returns true if user is logged in
    **/
   protected function logout ()
   {
      if (0 != $this->iIdUser)
      {
         session_unset();
         session_destroy();
         $this->reset();
      }
      return (0);
   }

   private function reset ()
   {
      $this->iIdUser              = 0;
      $this->iIdDomSel            = 0;
      $this->sDomSel              = '';
      $this->iIDuserAccountSelected            = 0;
      $this->sUserAccountSelected = '';
      $this->sIdPage              = '';
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function verifyPageAccess (&$bOk, $sIdPage)
   {
      $iErr = 0;

      $bOk = false;

      if (!in_array($sIdPage, array_keys($this->aPages))) ;
      else if ($this->iIdUser > 0 && !in_array('u', $this->aPages[$sIdPage]['aAccess'])) ;
      else if ($this->iIdUser < 0 && !in_array('a', $this->aPages[$sIdPage]['aAccess'])) ;
      else $bOk = true;

      return ($iErr);
   }

   /**
    * Verify if the user mailbox exists
    * @param string $mailboxAddress The mailbox address to verify
    * @return bool True=mailbox exists; False mailbox do't exists
    */
   public function existsMailboxName ($mailboxAddress): bool
   {
      $mbSql = $this->DB->realEscapeString($mailboxAddress);
      if (0 != $this->DB->query($recordSet, <<<SQL
           select '1'
           from virtual_users
           where email = '$mbSql' 
         SQL
         )) return false;

      $this->DB->getNumRows($nRows, $recordSet);
      if ($nRows < 1) return false;

      return true;
   }

   /**
    * @param array $resultTargets Reference in which to return a list of destination target already associated to alias. If null any destinations are present!
    * @param string $alias2ctrl Alias to search
    * @param int $idMailBoxUser ID of mailbox user
    * @return int 0=no error; !=0 error occurred
    */
   public function getExistingTargets (&$resultTargets, $alias2ctrl, $idMailBoxUser)
   {
      $resultTargets = array();
      $alias2ctrl    = trim($alias2ctrl);

      $aliasSql = $this->DB->realEscapeString($alias2ctrl);
      if ($this->DB->query($recordSet, <<<SQL
            SELECT destination
            FROM virtual_aliases
            WHERE source = '$aliasSql' and mailbox_id = $idMailBoxUser
         SQL
      ))
      {
         //errore query
         return 1;
      }

      $iErr = 0;
      $aRow = [];
      $this->DB->getNumRows($nRows, $recordSet);
      if ($nRows > 0) while (0 == ($iErr = $this->DB->fetchArray($aRow, $recordSet, MYSQLI_ASSOC)) && NULL !== $aRow)
      {
         $resultTargets[] = $aRow['destination'];
      }

      return ($iErr);
   }

   // ########## METHOD PRIVATE

   /**
    * Search other aliases with some name on some domain
    * @param array $resultAliases Reference in which to return a list of alias already existing not {@see $idMailBox2exclude}
    * @param string $alias2ctrl Alias to search
    * @param int $idMailBox2exclude ID o mailbox to exclude from search
    * @return int
    */
   public function getExistingAliases (array &$resultAliases, string $alias2ctrl, int $idMailBox2exclude): int
   {
      $resultAliases = array();
      $alias2ctrl    = trim($alias2ctrl);

      $aliasSql = $this->DB->realEscapeString($alias2ctrl);
      if ($this->DB->query($recordSet, <<<SQL
         SELECT distinct a.mailbox_id as mbid, u.email as mailbox
            FROM virtual_aliases as a
                right join virtual_users as u on u.id = a.mailbox_id
         WHERE 
             a.domain_id = (select domain_id from virtual_users where id = $idMailBox2exclude)
             and a.source = '$aliasSql' 
             and a.mailbox_id <> $idMailBox2exclude
         SQL
      ))
      {
         //errore query
         return 1;
      }

      $iErr = 0;
      $this->DB->getNumRows($nRows, $recordSet);
      if ($nRows > 0) while (0 == ($iErr = $this->DB->fetchArray($aRow, $recordSet, MYSQLI_ASSOC)) && NULL !== $aRow)
      {
         $resultAliases[] = [
            "mbox_id" => $aRow['mbid'],
            "mailbox" => $aRow['mailbox']
         ];
      }

      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function sendPage ($iErr)
   {
      // SHOW ERRORS
      if (!lib\ErrLog::getInstance()->hasError()) ;
      else if (0 != lib\ErrLog::getInstance()->getLog($sErrMsg)) ;
      else if (0 != $this->Page->drawMsgError($sErrMsg)) ;

      $this->Page->addCss('css/isp.css');
      return ($this->Page->send());
   }

   /**
    **
    **
    ** @retval boolean
    ** @returns true on pwd and hash matching
    **/
   public function verifyPwd_DbHash ($sPwdPlain, $sPwdHash)
   {
      $bRetVal = false;
      if (!preg_match("/^\{(BLF-CRYPT|SHA256-CRYPT|PLAIN-MD5)\}(.*)$/i", $sPwdHash, $aRes)) ;
      else if ('PLAIN-MD5' == $aRes[1]) $bRetVal = ($aRes[2] === md5($sPwdPlain));
      else $bRetVal = password_verify($sPwdPlain, $aRes[2]);
      return ($bRetVal);
   }

   /**
    * Verifies - if enabled - against blacklist_email
    * @param string $sMsg
    * @param string $sAddress
    * @return bool
    */
   public function verifyEmailIsBlacklisted (&$sMsg, $sAddress)
   {
      if (defined('IMA_SUPPORT_BLACKLIST'))
      {
         $aRow = NULL;
         if (0 != $this->DB->queryOneRow($aRow, "SELECT address as sAddr, reason as sReason" . " FROM blacklist_email" . " WHERE address='" . $this->DB->realEscapeString($sAddress) . "'")) ;
         else if (NULL === $aRow)
         {
            return true;
         }
         else
         {
            $sMsg = '"' . $sAddress . '" is blacklisted.' . (strlen($aRow['sReason']) > 0 ? ' Reason is "' . $aRow['sReason'] . '"' : '');
         }
         return false;
      }
      return true;
   }
}