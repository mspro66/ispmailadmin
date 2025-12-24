<?php
/**
 **
 **
 ** @package    ISPmail_Admin
 ** @author     Ole Jungclaussen
 ** @version    0.9.5
 **/

/**
 ** @public
 **/
class EmailAliases
{
   // ########## PROPS PUBLIC

   /**
    *
    * @type IspMailAdminApp
    */
   public $App = false;

   /**
    *
    * @type array
    */
   public $aStat = null;

   // ########## PROPS PROTECTED

   /**
    *
    * @type EmailDomains
    */
   protected $EDom = false;

   /**
    * @type EmailAccounts
    */
   protected $EAcc = false;

   // ########## PROPS PRIVATE

   /**
    * @var string The last alias part inserted in webpage
    */
   private $sLastSrcAlias = '';

   /**
    * @var string The last destination of alias inserted in webpage
    */
   private $sLastDest = '';

// ########## CONST/DEST
   function __construct (IspMailAdminApp &$App, EmailDomains &$EDom, EmailAccounts &$EAcc)
   {
      $this->App   = &$App;
      $this->aStat = &$App->aAlsStat;
      $this->EDom  = &$EDom;
      $this->EAcc  = &$EAcc;
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
      $this->App->Page->setTitle('Aliases');
      $this->App->Page->setHelp(<<<HTML
            <div class="Heading">
                Manage aliases to existing email-accounts (e.g. myavatar@<b>{$this->App->sDomSel}</b> as alias of {$this->App->sUserAccountSelected}). 
                All emails send to the alias will end up in the account.
            </div>
            <ul>
                <li>Choose the mailbox you want to modify/view aliases from the dropdown list</li>
                <li>Create an alias: Enter the 'Alias' name and click "Create"</li>
                <li>Add another destination to existing Alias by clicking on <img class="icon" style="height: 20px" src="./img/envelope-plus.png" alt="delete icon" />,
                    insert an email destination address and click "Create"</li>
                <li>Delete an alias: Click on <img class="icon" src="./img/trash.png" alt="delete icon" /></li>
                <li><b>Note</b>: E-mails addressed to a deleted alias will be rejected by the mailserver &ndash; unless you've a "catchall" account.</li>
            </ul>
         HTML


      );
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

            if (!isset($this->App->aReqParam['ssource'])) ;
            else if (0 == strlen($this->sLastSrcAlias = trim($this->App->aReqParam['ssource']))) ;
            else if (!isset($this->App->aReqParam['sdest'])) ;
            else if (0 == strlen($this->sLastDest = trim($this->App->aReqParam['sdest']))) ;
            /*r1 ms - the domain for aliases can no longer be chosen
            else if (!isset($this->App->aReqParam['iiddomain'])) ;
            else if (0 >= ($this->iIdLastDom = intval($this->App->aReqParam['iiddomain']))) ;*/
            //r1 else if (0 != ($iErr = $this->create($sMsg, $bSuccess, $this->sLastSrc, -1 /*$this->iIdLastDom*/, $this->App->iIdAccSel))) ;
            else if (0 != ($iErr = $this->create($sMsg, $bSuccess, $this->sLastSrcAlias, $this->sLastDest, $this->App->iIDuserAccountSelected))) ;
            //else $this->App->Page->drawMsg(!$bSuccess, $sMsg);
            $this->App->Page->drawMsg(!$bSuccess, $sMsg);

            // clear fields on success
            if ($bSuccess)
            {
               $this->sLastSrcAlias = '';
               $this->sLastDest     = $this->App->sUserAccountSelected;
            }
            break;

         case 'cmd_delete':
            $bSuccess = false;

            if (!isset($this->App->aReqParam['idalias'])) ;
            else if (0 >= ($iIdAlias = intval($this->App->aReqParam['idalias']))) ;
            else if (0 != ($iErr = $this->delete($sMsg, $bSuccess, $iIdAlias))) ;
            else $this->App->Page->drawMsg(!$bSuccess, $sMsg);
            break;

         case 'cmd_listpage':
            $this->aStat['iIdxPage'] = $this->App->aReqParam['idxpage'];
            break;

         default:
            break;
      }
      return ($iErr);
   }

   /**
    * Verify parameter and create new alias inserting record in database
    * @param string $sMsg Reference to insert user message to return caller
    * @param bool $bSuccess Reference to set TRUE if record is inserted
    * @param string $alias2Create Alias of virtual mailbox without @server-destination
    * @param string $destination Mailbox address destination of redirect alias
    * @param string $iIdMailBox ID user mailbox which own the alias
    * @return int 0=any error, otherwise is occurred an error
    */
   protected function create (&$sMsg, &$bSuccess, $alias2Create, $destination, $iIdMailBox): int
   {
      ///TODO: modificare chiamata con nuovo parametro destination!!!
      $iErr     = 0;
      $bSuccess = false;
      $aliasesFound = [];
      $targetsFound = [];
      $strIDuser = strval($iIdMailBox);

      //get domain name
      if (0 != ($iErr = $this->EDom->getDomainName($sDomain, $this->App->iIdDomSel)))
      {
         $sMsg .= "Cannot find domain_name from id ($this->App->iIdDomSel)";
      }
      else if (! $this->EAcc->isValidIdAccount($iIdMailBox))
      {
         $sMsg .= "Cannot find the parent mail-box from id ($iIdMailBox)";
      }
      //compose full alias address
      else if (false === ($newAliasFull = $alias2Create . '@' . $sDomain)) ;
      //verify address validity
      else if (! filter_var($newAliasFull, FILTER_VALIDATE_EMAIL))
      {
         $sMsg = 'The new alias address is invalid!';
      }
      else if (! filter_var($destination, FILTER_VALIDATE_EMAIL))
      {
         $sMsg = 'The new destination address is invalid!';
      }
      //verify if proposed alias is already present as mailbox
      else if ($this->App->existsMailboxName($newAliasFull))
      {
         $sMsg .= "The alias '$newAliasFull' is invalid, already exists a mailbox with some name!";
      }
      //verify if this alias exist, owned by other mailboxes or domain redirect
      else if (0 != ($iErr = $this->App->getExistingAliases($aliasesFound, $newAliasFull, $iIdMailBox))) ;
      else if (! empty($aliasesFound))
      {
         $sMsg .= "The alias '$newAliasFull' is already used in:  ";
         $firstCicle = true;
         foreach ($aliasesFound as $mboxOwner)
         {
            if (!$firstCicle)
               $sMsg .= ", ";

            if (substr($mboxOwner['mailbox'], 0, 1) == '@')
               $sMsg .= "Domain redirect";
            else
               $sMsg .= "mailbox {$mboxOwner['mailbox']}";

            $firstCicle = false;
         }
      }
      //verify existing target for this alias
      else if (0 != ($iErr = $this->App->getExistingTargets($targetsFound, $newAliasFull, $iIdMailBox))) ;
      else if ((empty($targetsFound) && $destination != $this->App->sUserAccountSelected)
               || (!empty($targetsFound) && !in_array($this->App->sUserAccountSelected, $targetsFound)))
      {
         //first destination must be a user mailbox!
         $sMsg .= "The first destination of alias must be some user mailbox!";
      }
      else if (!empty($targetsFound) && in_array($destination, $targetsFound))
      {
         $sMsg .= "Alias '$newAliasFull' exists and target '$destination', already is in!'";
      }
      //test balcklisting email
      else if (!$this->App->verifyEmailIsBlacklisted($sMsg, $newAliasFull)) ;
      //insert record in database
      else if (0 != ($iErr = $this->App->DB->state(<<<SQL
            INSERT INTO virtual_aliases 
                (domain_id, mailbox_id, source, destination) 
            VALUES (
                (select domain_id from virtual_users where id = '$iIdMailBox'),
                $iIdMailBox,
                '{$this->App->DB->realEscapeString($newAliasFull)}',
                '{$this->App->DB->realEscapeString($destination)}'
            )
            SQL
         )))
      {
         lib\ErrLog::getInstance()->push("Could not create alias '$newAliasFull' with target '$destination'; something[$iErr] went wrong!");
      }
      else
      {
         $sMsg = "Alias <b>$newAliasFull</b> with target of '$destination' has been created.";
         //if (0 != count($targetsFound)) $sMsg .= '<br /><br /><b>Note</b>: This alias is also an alias of <ul class="Msg"><li>' . implode('</li><li>', $targetsFound) . '</li></ul>';
         $bSuccess = true;
      }

      return ($iErr);
   }

   /**
    *
    *
    * @return int !=0 on error
    */
   protected function delete (&$sMsg, &$bSuccess, $iId)
   {
      $iErr     = 0;
      $bSuccess = false;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, <<<SQL
            SELECT source, 
                   destination, 
                   (select count(*) from virtual_aliases where mailbox_id=m.mailbox_id and source=m.source) as numdest 
            FROM virtual_aliases m WHERE id=$iId
         SQL ))) ;
      else if (NULL == $aRow)
         $sMsg = 'No such alias!';
      else if (intval($aRow['numdest']) > 1 && $aRow['destination'] === $this->App->sUserAccountSelected)
         $sMsg = 'Cannot be delete the main destination corrisponding the parent mailbox!';
      else if (0 != ($iErr = $this->App->DB->state("DELETE FROM virtual_aliases WHERE id=" . strval($iId))))
      {
         lib\ErrLog::getInstance()->push('Could not delete alias "' . $aRow['source'] . '", something[' . $iErr . '] went wrong!');
      }
      else
      {
         if (intval($aRow['numdest']) > 1)
            $sMsg = "The destination '{$aRow['destination']}' of alias '{$aRow['source']}', has been deleted.'";
         else
            $sMsg = "The alias '{$aRow['source']}' of '{$this->App->sUserAccountSelected}' mailbox, has been deleted.";
         
         $bSuccess = true;
      }

      return ($iErr);
   }
   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawSelectMailbox (HtmlPage &$Page)
   {
      $iErr  = 0;
      $sOpts = '';
      $aRow  = null;
      $rRslt = null;
      $nRows = 0;
      $sHtml = '';

      // user is a normal account: restrict to own account
      if (0 < $this->App->iIdUser) $this->App->iIDuserAccountSelected = $this->App->iIdUser;
      // else verify currently selected account, if any
      else if (0 != $this->App->iIDuserAccountSelected && !$this->EAcc->isValidIdAccount($this->App->iIDuserAccountSelected))
         $this->App->iIDuserAccountSelected = 0;

      if (0 != ($iErr = $this->App->DB->query($rRslt, "SELECT" . " user.email AS sAccount" . ",user.id AS iId" . " FROM virtual_users AS user" . " WHERE" . " SUBSTRING(user.email,1,1) <> '@'" //r1 ms
            . " AND user.domain_id=" . strval($this->App->iIdDomSel) //r1 ms
            . (0 > $this->App->iIdUser ? "" : " AND user.id=" . strval($this->App->iIdUser)) . " ORDER BY user.email ASC"))) ;
      else if (0 != ($iErr = $this->App->DB->getNumRows($nRows, $rRslt))) ;
      else if (0 == $nRows) $sHtml .= '<option value="0">No (email)accounts available</option>';
      else while (0 == ($iErr = $this->App->DB->fetchArray($aRow, $rRslt, MYSQLI_ASSOC)) && NULL !== $aRow)
      {
         if (0 == $this->App->iIDuserAccountSelected) $this->App->iIDuserAccountSelected = $aRow['iId'];
         if ($this->App->iIDuserAccountSelected == $aRow['iId']) $this->App->sUserAccountSelected = $aRow['sAccount'];

         $sOpts .= '<option value="' . strval($aRow['iId']) . '"' . ($aRow['iId'] != $this->App->iIDuserAccountSelected ? '' : ' selected="selected"') . '>' . $aRow['sAccount'] . '</option>';
      }

      if (0 != $iErr) ;
      else if (0 != ($iErr = $Page->addBody('<div class="inputform">' . '<form id="accountid_selector" name="accountid_selector" action="' . $_SERVER['PHP_SELF'] . '" method="POST">' . '<input type="hidden" name="cmd" value="cmd_setIdAccount" />' . '<table>' . '<tr>' . '<td class="label">Selected mailbox:</td>' . '<td class="value">' . '<select name="idaccount" onChange="document.accountid_selector.submit();">' . $sOpts . '</select>' . '</td>' . '</tr>' . '</table>' . '</form>' . '</div>'))) ;

      return ($iErr);
   }

   /**
    *
    *
    * @return int !=0 on error
    */
   public function drawCreate (HtmlPage &$Page)
   {
//      if (0 != ($iErr = $this->EDom->getSelectOpts($sDomOpts, $this->iIdLastDom, $sDomSel))) ;

      //$domainAlias = substr($this->App->sAccSel,strpos($this->App->sAccSel, '@')); //r1 ms

      return ($Page->addBody(
         '<h3>Create new</h3>'
         . '<div class="inputform">'
         . '<form id="create_alias" name="create_alias" action="' . $_SERVER['PHP_SELF'] . '" method="POST">'
         . '<input type="hidden" name="cmd" value="cmd_create" />'
         . '<table class="InputForm">'
         . '<tr>'
         . '<td class="label">Alias:</td>'
         . '<td class="value">'
         . '<input type="text" name="ssource" id="alias_src" placeholder="name" value="' . $this->sLastSrcAlias . '" style="text-align: right">'
         . '@' . $this->App->sDomSel
//         . '@<select name="iiddomain">'
//         . $sDomOpts
//         . '</select>'
         . '</td>'
         . '</tr>'
         . '<tr>'
         . '<td class="label">Destination:</td>'
         . '<td class="value">'
         . '<input class="readonly" name="sdest" type="text" readonly="readonly" value="' . $this->App->sUserAccountSelected . '">'
         . '</td>'
         . '</tr>'
         . '<tr>'
         . '<td class="label">&nbsp;</td>'
         . '<td class="submit">'
         . '<button onClick=" verifyCreateAlias(document.create_alias);return false;">Create</button>'
         . '&nbsp;<button id="breset" style="visibility:hidden; background-color: #cccccc" onClick="resetAliasForm(); return false;">Cancel</button>'
         . '</td>'
         . '</tr>'
         . '</table>'
         . '</form>'
         . '</div>'
      ));
   }

   /**
    * Create html grid of aliases
    * @return int !=0 on error
    */
   public function drawList (HtmlPage &$Page)
   {
      $iErr     = 0;
      $sHtml    = '';
      $nEntries = 0;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, <<<SQL
            select count(*) as nCnt
            from virtual_aliases
            where mailbox_id = {$this->App->iIDuserAccountSelected};
         SQL
         ))) ;
      else if (null === $aRow) ;
      else if (0 != ($iErr = lib\checkListPages($this->aStat, ($nEntries = $aRow['nCnt'])))) ;

      $lastSource = "";
      $sqlLimit   = lib\makeListPagesSqlLimit($this->aStat);
      if (0 != ($iErr = $this->App->DB->query($rRslt, <<<SQL
            select *
            from virtual_aliases
            where mailbox_id = {$this->App->iIDuserAccountSelected}
            order by source, destination
            $sqlLimit
         SQL
         ))) ;
      else if (0 != ($iErr = $this->App->DB->getNumRows($nRows, $rRslt))) ;
      else if (0 == $nRows) $sHtml .= '<tr><td colspan="3">No aliases created yet for this account</td></tr>';
      else
      {
         $htmAliasOpen = function ($Source) {
            return <<<HTM
               <tr>
                 <td class="icon">
                     <img class="icon" src="./img/envelope-plus.png" onClick="newAddedAlias('$Source');" alt="icon new" title="Add new destination to this alias"/>
                 </td>    
                 <td class="enfasi">
                   $Source
                 </td>
                 <td>
                    <table class="inside">
               HTM;
         };

         $htmAliasClose = <<<HTM
                </table>
              </td>
          </tr>
         HTM;
         $htmDest = function ($ID, $Source, $Target)
            {
               return <<<HTM
                    <tr>
                        <td>
                          $Target
                        </td>
                        <td class="icon">
                           <form name="delete_alias_$ID" action="{$_SERVER['PHP_SELF']}" method="POST">
                               <input type="hidden" name="cmd" value="cmd_delete"/>
                               <input type="hidden" name="idalias" value="$ID"/>
                               <img src="./img/trash.png" onClick="confirmDeleteAlias(document.delete_alias_$ID, '$Source', '$Target'); return false;" alt="icon delete" title = "Delete destination"/>
                           </form>
                       </td>
                    </tr>
               HTM;
            };

         while (0 == ($iErr = $this->App->DB->fetchArray($aRow, $rRslt, MYSQLI_ASSOC)) && NULL !== $aRow)
         {
            if ($lastSource == $aRow['source'])
            {
               $sHtml .= $htmDest($aRow['id'], $aRow['source'], $aRow['destination']);
            }
            else
            {
               if (!empty($lastSource))
                  $sHtml .= $htmAliasClose;

               $sHtml .= $htmAliasOpen ($aRow['source']) . $htmDest($aRow['id'], $aRow['source'], $aRow['destination']);
            }

            $lastSource = $aRow['source'];
         }

         if (!empty($lastSource))
            $sHtml .= $htmAliasClose;
      }

      if (0 == $iErr)
      {
         $scrollNumber = lib\makeListPages($this->aStat, $nEntries, 'Alias_ListPage');
         $Page->addBody(<<<HTML
            <h3>Existing Aliases for {$this->App->sUserAccountSelected}</h3>
            <div class="listgrid">
                $scrollNumber
                <table>
                    <tr>
                        <th colspan="2">Alias</th>
                        <th>Destinations</th>
                    </tr>
                    $sHtml
                 </table>
            </div>
         HTML
         );
      }

      return ($iErr);
   }

   /*public function drawList (HtmlPage &$Page)
   {
      $iErr     = 0;
      $sHtml    = '';
      $nEntries = 0;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow,
            "SELECT"
            . " COUNT(alias.id) AS nCnt"
            . " FROM `virtual_aliases` AS alias"
            . " LEFT JOIN virtual_users AS user ON(user.email=alias.destination AND user.id=" . strval($this->App->iIdAccSel) . ")"
            . " LEFT JOIN virtual_aliases AS alias2 ON(alias2.source=alias.source AND alias2.destination!=alias.destination)"
            . " WHERE NOT " . $this->App->DB->sqlISNULL('user.id')
         ))) ;
      else if (null === $aRow) ;
      else if (0 != ($iErr = lib\checkListPages($this->aStat, ($nEntries = $aRow['nCnt'])))) ;

      if (0 != ($iErr = $this->App->DB->query($rRslt,
            "SELECT"
            . " alias.id AS iId"
            . ",alias.source AS sSrc"
            . ",alias.destination AS sTar"
            . ",COUNT(alias2.id) AS nAddTars"
            . " FROM virtual_aliases AS alias"
            . " LEFT JOIN virtual_users AS user ON(user.email=alias.destination AND user.id=" . strval($this->App->iIdAccSel) . ")"
            . " LEFT JOIN virtual_aliases AS alias2 ON(alias2.source=alias.source AND alias2.destination!=alias.destination)"
            . " WHERE NOT " . $this->App->DB->sqlISNULL('user.id')
            . " GROUP BY iId, sSrc, sTar"
            . " ORDER BY sSrc ASC"
            . lib\makeListPagesSqlLimit($this->aStat)
         ))) ;
      else if (0 != ($iErr = $this->App->DB->getNumRows($nRows, $rRslt))) ;
      else if (0 == $nRows) $sHtml .= '<tr class=""><td class="" colspan="6">No aliases created yet for this account</td></tr>';
      else
         while (0 == ($iErr = $this->App->DB->fetchArray($aRow, $rRslt, MYSQLI_ASSOC)) && NULL !== $aRow)
         {
            $aAddTars = array();

            if (0 != $aRow['nAddTars'] && 0 != ($iErr = $this->getExistingTargets($aAddTars, $aRow['sSrc'], $aRow['sTar']))) ;
            else $sHtml .=
               '<tr>'
               . '<td class="icon">'
               . '<form name="delete_alias_' . strval($aRow['iId']) . '" action="' . $_SERVER['PHP_SELF'] . '" method="POST">'
               . '<input type="hidden" name="cmd" value="cmd_delete" />'
               . '<input type="hidden" name="idalias" value="' . strval($aRow['iId']) . '" />'
               . '<img class="icon" src="./img/trash.png" onClick="confirmDeleteAlias(document.delete_alias_' . strval($aRow['iId']) . ', \'' . $aRow['sSrc'] . '\');" alt="icon delete"/>'
               . '</form>'
               . '</td>'
               . '<td class="">' . $aRow['sSrc'] . '</td>'
               . '<td class="list">' . implode('<br />', $aAddTars) . '</td>'
               . '</tr>';
         }

      if (0 != $iErr) ;
      else if (0 != ($iErr = $Page->addBody(
            '<h3>Existing Aliases for ' . $this->App->sAccSel . '</h3>'
            . '<div class="DatabaseList">'
            . lib\makeListPages($this->aStat, $nEntries, 'Alias_ListPage')
            . '<table class="DatabaseList">'
            . '<colgroup><col width="16"><col width="*"><col width="30%"></colgroup>'
            . '<tr>'
            . '<th></th>'
            . '<th>Alias</th>'
            . '<th>Also&nbsp;alias&nbsp;of&nbsp;/&nbsp;redirects&nbsp;to</th>'
            . '</tr>'
            . $sHtml
            . '</table>'
            . '</div>'
         ))) ;

      return ($iErr);
   }*/
}

?>