<?php
/**
 **
 **
 ** @package    ISPmail_Admin
 ** @author     Ole Jungclaussen
 ** @version    0.9.4
 **/

/**
 ** @public
 **/
class EmailAccounts
{
// ########## PROPS PUBLIC
   /**
    ** @type IspMailAdminApp
    **/
   public $App;
   /**
    **
    ** @type array
    **/
   public $aStat = null;
// ########## PROPS PROTECTED
   /**
    **
    ** @type EmailDomains
    **/
   //protected $EDom = false;
// ########## PROPS PRIVATE
// ########## CONST/DEST
   //public function __construct(IspMailAdminApp &$App, EmailDomains &$Domains)
   public function __construct (IspMailAdminApp &$App)
   {
      $this->App   = &$App;
      $this->aStat = &$App->aAccStat;
      //$this->EDom  = &$Domains;
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public static function loginAccount (&$App, &$iId, $sAccount, $sPwdPlain)
   {
      $iErr = 0;
      $iId  = 0;
      $aRow = null;
      if (0 != ($iErr = $App->DB->queryOneRow($aRow, "SELECT id, password FROM virtual_users WHERE" . " email='" . $App->DB->realEscapeString($sAccount) . "'"))) ;
      else if (NULL == $aRow) ;
      else if (!$App->verifyPwd_DbHash($sPwdPlain, $aRow['password'])) ;
      else $iId = $aRow['id'];

      return ($iErr);
   }
// ########## METHOD PUBLIC

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function setTitleAndHelp (HtmlPage &$Page)
   {
      $this->App->Page->setTitle('Email accounts');
      $this->App->Page->setHelp('<div class="Heading">Manage real email-accounts (<i>user1@example.com</i>) that users can access with POP3(s), IMAP(s), and SMTP</div>' . '<ul>' . '<li>Choose the domain you want to modify/view from the dropdown list.</li>' . '<li>Create an account: Enter user name, password, and click "Create".</li>' . '<li>Delete an account: Click on <img class="icon" src="./img/trash.png" alt="delete icon" />.</li>' . '<li>Set a new password: Click on <img class="icon" src="./img/key.png" alt="change password icon" />, enter the new password and click "Set".</li>' . (!IMA_CFG_USE_QUOTAS ? '' : '<li>Change the quota: Click on <img style="width:1em;" class="icon" src="./img/edit_pen.svg" alt="change quota icon" />, enter the new quota and click "Set".</li>') . '<li>Aliases to an account: Click on <img class="icon" src="./img/edit.png" alt="edit icon"/>.</li>' . (!IMA_CFG_USE_QUOTAS ? '' : '<li>Quota: Value is in <i>bytes</i>. Use "0" (zero) for unlimited. One GB is 1073741824 bytes (2<sup>30</sup>). Do not use dots or commas.') . '<li><b>Note</b>: If you delete an account, all aliases associated with it <i>will</i> be deleted, too.</li>' . '</ul>');
      return (0);
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

      if (!isset($this->App->aReqParam['cmd'])) ;
      else switch ($this->App->aReqParam['cmd'])
      {
         case 'cmd_create':
            $bSuccess = false;
            $iQuota   = 0;

            if (!isset($this->App->aReqParam['saccount']) || 0 == strlen($sAccount = trim($this->App->aReqParam['saccount']))) ;
            else if (!isset($this->App->aReqParam['pwd_spassword'])) ;
            else if (IMA_CFG_USE_QUOTAS && (!isset($this->App->aReqParam['quota']) || 0 > intval($iQuota = $this->App->aReqParam['quota']))) ;
            else if (0 != ($iErr = $this->createAccount($sMsg, $bSuccess, $sAccount, $this->App->aReqParam['pwd_spassword'], $this->App->iIdDomSel, $iQuota))) ;
            else  $this->App->Page->drawMsg(!$bSuccess, $sMsg);
            break;

         case 'cmd_delete':
            $bSuccess = false;

            if (!isset($this->App->aReqParam['idaccount'])) ;
            else if (0 >= ($iIdAccount = intval($this->App->aReqParam['idaccount']))) ;
            else if (0 != ($iErr = $this->delete($sMsg, $bSuccess, $iIdAccount))) ;
            else  $this->App->Page->drawMsg(!$bSuccess, $sMsg);
            break;

         case 'cmd_resetPassword':
            $bSuccess = false;

            if (!isset($this->App->aReqParam['idaccount'])) ;
            else if (0 >= ($iIdAccount = intval($this->App->aReqParam['idaccount']))) ;
            else if (!isset($this->App->aReqParam['pwd_spassword'])) ;
            else if (0 != ($iErr = $this->setPassword($sMsg, $bSuccess, $iIdAccount, $this->App->aReqParam['pwd_spassword']))) ;
            else $this->App->Page->drawMsg(!$bSuccess, $sMsg);
            break;

         case 'cmd_changeQuota':
            if (!isset($this->App->aReqParam['idaccount'])) ;
            else if (0 >= ($iIdAccount = intval($this->App->aReqParam['idaccount']))) ;
            else if (!isset($this->App->aReqParam['quota'])) ;
            else if (0 != ($iErr = $this->setQuota($sMsg, $bSuccess, $iIdAccount, $this->App->aReqParam['quota']))) ;
            else $this->App->Page->drawMsg(!$bSuccess, $sMsg);
            break;

         case 'cmd_listpage':
            $this->aStat['iIdxPage'] = $this->App->aReqParam['idxpage'];
            break;
      }
      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function createAccount (&$sMsg, &$bSuccess, $sAccount, $sPwdHash, $iIdDomain, $iQuota = 0)
   {
      $iErr     = 0;
      $bSuccess = false;

      //if(0!=($iErr = $this->EDom->getDomainName($sDomain, $iIdDomain)));
      if (0 == strlen($this->App->sDomSel))
      {
         $sMsg .= 'Invalid Domain [' . $iIdDomain . ']';
      }
      else if ($this->doesAccountExist($sAccount . '@' . $this->App->sDomSel))
      {
         $sMsg .= 'Account "' . $sAccount . '@' . $this->App->sDomSel . '" already exists!';
      }
      else if (!$this->App->verifyEmailIsBlacklisted($sMsg, $sAccount . '@' . $this->App->sDomSel)) ;
      else if (!filter_var($sAccount, FILTER_VALIDATE_EMAIL))
      {
         $sMsg = 'The email address inserted is invalid!';
      }
      else if (0 == strlen($sPwdHash))
      {
         $sMsg = 'Password is empty!';
      }
      else if (0 != ($iErr = $this->App->DB->state(// reminder: this has to work with SQLite (IMA-Demo), too
         // - SQLite3 doesn't know the "INSERT ... SET" Syntax
            "INSERT INTO virtual_users (domain_id, password, email" . (IMA_CFG_USE_QUOTAS ? ", quota" : "") . ") VALUES (" . strval($iIdDomain) . ",'" . $this->App->DB->realEscapeString($sPwdHash) . "'" . ",'" . $this->App->DB->realEscapeString($sAccount . '@' . $this->App->sDomSel) . "'" . (IMA_CFG_USE_QUOTAS ? ",'" . intval($iQuota) . "'" : "") . ")"))) lib\ErrLog::getInstance()->push('Could not create account "' . $sAccount . '@' . $this->App->sDomSel . '", something[' . $iErr . '] went wrong!');
      else
      {
         $bSuccess = true;
         $sMsg     = 'Account "' . $sAccount . '@' . $this->App->sDomSel . '" has been created and should show in the list below.';
      }

      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function doesAccountExist ($sEmail)
   {
      $bRetVal = false;
      $aRow    = null;
      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, "SELECT id FROM virtual_users WHERE email='" . $this->App->DB->realEscapeString($sEmail) . "'"))) ;
      else if (NULL === $aRow) ;
      else $bRetVal = true;
      return ($bRetVal);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function delete (&$sMsg, &$bSuccess, $iId)
   {
      $iErr     = 0;
      $bSuccess = false;
      $aRow     = null;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, "SELECT email FROM virtual_users WHERE id=" . strval($iId)))) ;
      else if (NULL === $aRow)
      {
         $sMsg .= 'No such Account!<br />';
      }
      else if (0 == ($iErr = $this->App->DB->startTransaction()))
      {
         if (0 != ($iErr = $this->App->DB->state(//"DELETE FROM virtual_aliases WHERE destination='" . $this->App->DB->realEscapeString($aRow['email']) . "'"
               "DELETE FROM virtual_aliases WHERE mailbox_id=$iId"))) ;
         else if (0 != ($iErr = $this->App->DB->state("DELETE FROM virtual_users WHERE id=$iId"))) ;

         if (0 != $iErr) $this->App->DB->cancelTransaction();
         else if (0 != ($iErr = $this->App->DB->commitTransaction())) ;
      }

      if (0 != $iErr) lib\ErrLog::getInstance()->push('Could not delete account "' . $aRow['email'] . '", something[' . $iErr . '] went wrong!');
      else
      {
         $bSuccess = true;
         $sMsg     = 'Account "' . $aRow['email'] . '" and all aliases associated with it have been deleted.';
      }

      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function setPassword (&$sMsg, &$bSuccess, $iIdAccount, $sPwdHash)
   {
      $iErr     = 0;
      $bSuccess = false;
      $aRow     = null;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, "SELECT email FROM virtual_users WHERE id=" . strval($iIdAccount)))) ;
      else if (NULL === $aRow)
      {
         $sMsg = 'No such Account!';
      }
      else if (0 == strlen($sPwdHash))
      {
         $sMsg = 'Password is empty!';
      }
      else if (0 != ($iErr = $this->App->DB->state(// reminder: this has to work with SQLite (IMA-Demo), too
            "UPDATE virtual_users SET password='" . $this->App->DB->realEscapeString($sPwdHash) . "' WHERE id=" . strval($iIdAccount))))
      {
         lib\ErrLog::getInstance()->push('Could not change password for "' . $aRow['email'] . '", something[' . $iErr . '] went wrong!');
      }
      else
      {
         $bSuccess = true;
         $sMsg     .= 'Password for "' . $aRow['email'] . '" changed.';
      }

      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function setQuota (&$sMsg, &$bSuccess, $iIdAccount, $iQuota)
   {
      $iErr     = 0;
      $bSuccess = false;
      $aRow     = null;

      $iQuota = intval($iQuota);

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, "SELECT email FROM virtual_users WHERE id=" . strval($iIdAccount)))) ;
      else if (NULL === $aRow)
      {
         $sMsg = 'No such Account!';
      }
      else if (0 != ($iErr = $this->App->DB->state(// reminder: this has to work with SQLite (IMA-Demo), too
            "UPDATE virtual_users SET quota='" . strval($iQuota) . "' WHERE id=" . strval($iIdAccount))))
      {
         lib\ErrLog::getInstance()->push('Could not change quota for "' . $aRow['email'] . '", something[' . $iErr . '] went wrong!');
      }
      else
      {
         $bSuccess = true;
         $sMsg     .= 'Quota for "' . $aRow['email'] . '" changed to ' . self::cnvQuotaToHuman($iQuota);
      }

      return ($iErr);
   }
// ########## METHOD PROTECTED

   /**
    **
    **
    ** @retval string
    ** @returns $iQuota as "X.XX GB"
    **/
   public static function cnvQuotaToHuman ($iQuota)
   {
      if (0 >= $iQuota)
      {
         return "unlimited";
      }
      return strval(bcdiv($iQuota, 1073741824, 2)) . 'GB';
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawCreate (HtmlPage &$Page)
   {
      return ($Page->addBody('<h3>Create new</h3>' . '<div class="inputform">' . '<form id="create_account" name="create_account" action="' . $_SERVER['PHP_SELF'] . '" method="POST" autocomplete="off">' . '<input type="hidden" name="cmd" value="cmd_create" />' . '<input type="hidden" name="iddomain" value="' . strval($this->App->iIdDomSel) . '">' . '<table>' . '<tr>' . '<td class="label">Email:</td>' . '<td class="value"><input type="text" name="saccount" placeholder="account" autocomplete="off" autofocus>@' . $this->App->sDomSel . '</td>' . '</tr>' . '<tr>' . '<td class="label">Password:</td>' . '<td class="value"><input type="password" name="pwd_spassword" autocomplete="off"></td>' . '</tr>' . (!IMA_CFG_USE_QUOTAS ? '' : '<tr>' . '<td class="label">Quota:</td>' . '<td class="value"><input type="number" name="quota" autocomplete="off" min="0" step="' . strval(intval(IMA_CFG_QUOTA_STEP)) . '" value="' . strval(IMA_CFG_DEFAULT_QUOTA) . '"></td>' . '</tr>') . '<tr>' . '<td class="label"></td>' . '<td>' . '<button onClick="verifyCreateAccount(document.create_account, \'' . $this->App->sDomSel . '\');">Create</button>' . '</td>' . '</tr>' . '</table>' . '</form>' . '</div>'));
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawList (HtmlPage &$Page)
   {
      $iErr     = 0;
      $sHtml    = '';
      $nEntries = 0;
      $aRow     = null;
      $rRslt    = null;
      $nRows    = 0;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, "SELECT COUNT(id) AS nCnt FROM `virtual_users`" . " WHERE domain_id=" . strval(intval($this->App->iIdDomSel))))) ;
      else if (null === $aRow) ;
      else if (0 != ($iErr = lib\checkListPages($this->aStat, ($nEntries = $aRow['nCnt'])))) ;

      if (0 != ($iErr = $this->App->DB->query($rRslt, "SELECT" . " user.id AS iId" . ",user.email AS sEmail" . (IMA_CFG_USE_QUOTAS ? ",user.quota AS iQuota" : "") . ",COUNT(alias.id) AS nAliases" . " FROM virtual_users AS user" . " LEFT JOIN virtual_aliases AS alias ON(alias.destination=user.email)" . " WHERE user.domain_id=" . strval(intval($this->App->iIdDomSel)) . " AND SUBSTRING(user.email,1,1) <> '@'" //#r1 ms
            . " GROUP BY user.id" . " ORDER BY email ASC" . lib\makeListPagesSqlLimit($this->aStat)))) ;
      else if (0 != ($iErr = $this->App->DB->getNumRows($nRows, $rRslt))) ;
      else if (0 == $nRows) $sHtml .= '<tr class=""><td class="" colspan="6">No (email)accounts created yet for this domain</td></tr>';
      else while (0 == ($iErr = $this->App->DB->fetchArray($aRow, $rRslt, MYSQLI_ASSOC)) && NULL !== $aRow)
      {
//                . '<img class="icon" src="./img/trash.png" onClick="confirmDeleteAccount(document.delete_account_' . strval($aRow['iId']) . ', \'' . $aRow['sEmail'] . '\');" alt="icon delete"/-->
//            . '<a class="button button_small_right" onClick="confirmChangePassword(document.account_chg_pass_' . $aRow['iId'] . ')">Set</a>'
//               . '<a class="button button_small_right" onClick="confirmChangeQuota(document.account_chg_quota_' . $aRow['iId'] . ')">Set</a>'
//            . '<img class="icon" src="./img/edit.png" onClick="document.account_aliases_' . strval($aRow['iId']) . '.submit();" alt="icon edit"/>'
         $htmlStepQuota = IMA_CFG_QUOTA_STEP;
         $htmlHumanQuota = self::cnvQuotaToHuman($aRow['iQuota']);
         $htmlQuotas    = "";
         if (IMA_CFG_USE_QUOTAS) $htmlQuotas = <<<QUOT
                <td class="num">$htmlHumanQuota</td>
                <!-- icon quotas -->
                <td class="icon">
                    <a onClick="toggleEditQuota(document.account_chg_quota_{$aRow['iId']})"  title="Change quota">
                        <img src="./img/edit_pen.svg" alt="icon change quota"/>
                    </a>
                </td>
                <!-- temporary cell for edit quota -->
                <td>
                  <form name="account_chg_quota_{$aRow['iId']}" style="display:none;" action="{$_SERVER['PHP_SELF']}" method="POST">
                    <input type="hidden" name="cmd" value="cmd_changeQuota" />
                    <input type="hidden" name="idaccount" value="{$aRow['iId']}" />
                    <input name="quota" type="number" autocomplete="off" min="0" step="$htmlStepQuota" value="{$aRow['iQuota']}">
                    <!--a onClick="confirmChangeQuota(document.account_chg_quota_{$aRow['iId']})">Set</a-->
                    <button onClick="confirmChangeQuota(document.account_chg_quota_{$aRow['iId']})">Set</button>
                  </form>
               </td>
            QUOT;

         $sHtml .= <<<HTML
            <tr>
                <!-- email box -->
                <td>{$aRow['sEmail']}</td>
                <!-- password icon -->
                <td class="icon">
                    <a onClick="toggleNewPassword(document.account_chg_pass_{$aRow['iId']})" title="Change password">
                        <img src="./img/key.png" alt="icon change password"/>
                    </a>
                </td>
                <!-- password edit column -->
                <td>
                    <form name="account_chg_pass_{$aRow['iId']}" style="display:none;" action="{$_SERVER['PHP_SELF']}" method="POST">
                        <input type="hidden" name="cmd" value="cmd_resetPassword" />
                        <input type="hidden" name="idaccount" value="{$aRow['iId']}" />
                        <input name="pwd_spassword" type="password" placeholder="New password">
                        <!--a onClick="return confirmChangePassword(document.account_chg_pass_{$aRow['iId']});">Set</a-->
                        <button onClick="return confirmChangePassword(document.account_chg_pass_{$aRow['iId']});">Set</button>
                    </form>
                </td>
                <!-- quotas -->
                $htmlQuotas
                <!-- num aliases -->
                <td class="num">{$aRow['nAliases']}</td>
                <!-- icon goto aliases -->
                <td class="icon">
                    <form name="account_aliases_{$aRow['iId']}" action="{$_SERVER['PHP_SELF']}" method="POST">
                        <input type="hidden" name="cmd" value="cmd_openPage" />
                        <input type="hidden" name="spage" value="page_aliases" />
                        <input type="hidden" name="idaccount" value="{$aRow['iId']}" />
                        <img src="./img/edit.png" onClick="document.account_aliases_{$aRow['iId']}.submit();" alt="icon edit"  title="Go to aliases"/>
                    </form>
                </td>
                <!-- delete icon -->
                <td class="icon">
                    <form name="delete_account_{$aRow['iId']}" action="{$_SERVER['PHP_SELF']}" method="POST">
                        <input type="hidden" name="cmd" value="cmd_delete"/>
                        <input type="hidden" name="idaccount" value="{$aRow['iId']})" />
                        <img src="./img/trash.png" onClick="confirmDeleteAccount(document.delete_account_{$aRow['iId']}, '{$aRow['sEmail']}');" alt="icon delete"/>
                    </form>
                </td>
                            </tr>
         HTML;
      }
//. '<div class="DatabaseList">'
//            . '<table class="DatabaseList">'
//            . (!IMA_CFG_USE_QUOTAS ? '' : '<th class="num">Quota</th><th></th><th></th>')
      if ($iErr == 0)
      {
         $htmlPaging = lib\makeListPages($this->aStat, $nEntries, 'Accounts_ListPage');
         $htmlHeadQuotas = (!IMA_CFG_USE_QUOTAS ? '' : '<th colspan="3">Quota</th>');

         $iErr = $Page->addBody(<<<HTM
                <h3>Existing Accounts for {$this->App->sDomSel}</h3>
                <div class="listgrid">
                    $htmlPaging 
                    <table>
                        <tr>
                            <th colspan="3">Account</th>
                            <!-- password -->
                            $htmlHeadQuotas               
                            <th colspan="2" class="num">Aliases</th>
                            <!-- delete -->
                            <th></th>
                         </tr>
                         $sHtml
                    </table>
                </div>
             HTM
         );
      }
      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function isValidIdAccount ($iId)
   {
      $bRetVal = false;
      $aRow    = null;
      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, "SELECT id FROM virtual_users WHERE id='" . strval(intval($iId)) . "'"))) ;
      else if (NULL === $aRow) ;
      else $bRetVal = true;
      return ($bRetVal);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function getAccountEmail ($iId)
   {
      $aRow = null;
      if ($this->App->DB->queryOneRow($aRow, "SELECT email FROM virtual_users WHERE id='" . strval(intval($iId)) . "'") != 0) return false;

      if ($aRow == NULL) return false;

      return $aRow['email'];
   }
}