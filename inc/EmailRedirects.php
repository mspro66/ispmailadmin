<?php
/**
 **
 **
 ** @package    ISPmail_Admin
 ** @author     Ole Jungclaussen
 ** @version    0.9.0
 **/

/**
 ** @public
 **/
class EmailRedirects
{
   /**
    * @type false|IspMailAdminApp Reference to Main app obj
    */
   public $App = false;

   /**
    * @type array
    */
   public $aStat = null;

   /**
    * @var string ID of mailbox that own domain-redirects
    */
   public $idCurrentMboxOwner;

   /**
    * @var string Name of current mailbox owner of domain redirects
    */
   public $nameCurrentMboxOwner;

   /**
    * @var false|EmailDomains Reference to Domain obj
    */
   protected $EDom = false;

   private $sLastSrc = '';
   private $sLastTar = '';
   private $iIdLastDom = 0;

// ########## CONST/DEST

   function __construct (IspMailAdminApp &$App, EmailDomains &$EDom)
   {
      $this->App   = &$App;
      $this->aStat = &$App->aRedStat;
      $this->EDom  = &$EDom;
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
      $this->App->Page->setTitle('Domain Redirects');
      $this->App->Page->setHelp(<<<HTM
        <div class="Heading">
            Manage redirects from a local virtual mail-box to any other email-address 
            (forward emails for <i>somelocalvirtualaddress@<b>{$this->App->sDomSel}</b></b></i> to <i>somebody.else@over.there.com</i>)
         </div>
         <ul>
             <li>Create a redirect: entering a virtual mail-box name, an email destination address and click "Create"</li>
             <li>Add another destination: make click on <img class="icon" style="height: 20px" src="./img/envelope-plus.png" alt="delete icon" />
                 and insert an email destination address and click "Create"</li>
             <li>Delete a redirect: make click on <img class="icon" style="height: 20px" src="./img/trash.png" alt="adddelete icon" /></li>
             <li><b>Note</b>: E-mails addressed to a deleted redirects will be rejected by the mailserver &ndash; unless you've a "catchall" account.</li>'
         </ul>
      HTM );

      /*
       <li><b>Note</b>: You can redirect an existing account.</li>
      */
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

            if (!isset($this->App->aReqParam['ssrc'])) ;
            else if (0 == strlen($this->sLastSrc = trim($this->App->aReqParam['ssrc']))) ;
            /*else if (!isset($this->App->aReqParam['iiddomain'])) ;
            else if (0 >= ($this->iIdLastDom = intval($this->App->aReqParam['iiddomain']))) ;*/
            else if (!isset($this->App->aReqParam['star'])) ;
            else if (0 == strlen($this->sLastTar = trim($this->App->aReqParam['star']))) ;
            else if (0 != ($iErr = $this->create($sMsg, $bSuccess, $this->sLastSrc, $this->sLastTar))) ;
            else $this->App->Page->drawMsg(!$bSuccess, $sMsg);

            // clear fields on success
            if ($bSuccess)
            {
               $this->sLastSrc = '';
               $this->sLastTar = '';
            }
            break;

         case 'cmd_delete':
            $bSuccess = false;

            if (!isset($this->App->aReqParam['idredirect'])) ;
            else if (0 >= ($iIdRedirect = intval($this->App->aReqParam['idredirect']))) ;
            else if (0 != ($iErr = $this->delete($sMsg, $bSuccess, $iIdRedirect))) ;
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
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   protected function create (&$sMsg, &$bSuccess, $newRedirect, $sTar)
   {
      $iErr     = 0;
      $bSuccess = false;

      $newRedirectAddr = "$newRedirect@{$this->App->sDomSel}";
      $aliasesFound    = [];
      $targetsFound    = [];

      //verify if proposed redirect is already present as mailbox
      if ($this->App->existsMailboxName($newRedirectAddr))
      {
         $sMsg .= "The redirect '$newRedirectAddr' is invalid, already exists a mailbox with some name!";
      }
      else if (! filter_var($newRedirectAddr, FILTER_VALIDATE_EMAIL))
      {
         $sMsg = 'The new redirect address is invalid!';
      }
      else if (! filter_var($sTar, FILTER_VALIDATE_EMAIL))
      {
         $sMsg = 'The new destination address is invalid!';
      }//verify if this alias exist, owned by user mailboxes or domain redirect
      else if (0 != ($iErr = $this->App->getExistingAliases($aliasesFound, $newRedirectAddr, $this->idCurrentMboxOwner))) ;
      else if (!empty($aliasesFound))
      {
         $sMsg       .= "The redirect '$newRedirectAddr' is already used in:  ";
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
      else if (0 != ($iErr = $this->App->getExistingTargets($targetsFound, $newRedirectAddr, $this->idCurrentMboxOwner))) ;
      else if (!empty($targetsFound) && in_array($sTar, $targetsFound))
      {
         $sMsg .= "Redirect '$newRedirectAddr' exists and target '$sTar', already is in!'";
      }
      else if (!$this->App->verifyEmailIsBlacklisted($sMsg, $newRedirectAddr)) ;
      else if (0 != ($iErr = $this->App->DB->state(<<<SQL
            INSERT INTO virtual_aliases 
                (domain_id, mailbox_id, source, destination) 
            VALUES (
                {$this->App->iIdDomSel},
                {$this->idCurrentMboxOwner},
                '{$this->App->DB->realEscapeString($newRedirectAddr)}',
                '{$this->App->DB->realEscapeString($sTar)}'
            )
         SQL
         )))
      {
         lib\ErrLog::getInstance()->push("Could not create redirect '$newRedirectAddr' to '$sTar', something [$iErr] went wrong!");
      }
      else
      {
         $sMsg     = "Redirect '$newRedirectAddr' to {$sTar} has been created.";
         $bSuccess = true;
      }

      return ($iErr);
   }

   /**
    *
    * @retval integer
    * @returns !=0 on error
    */
   protected function delete (&$sMsg, &$bSuccess, $iId)
   {
      $iErr     = 0;
      $bSuccess = false;

      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, <<<SQL
            SELECT 
                source, 
                destination,
                (select count(*) from virtual_aliases where mailbox_id=m.mailbox_id and source=m.source) as numdest
            FROM virtual_aliases m WHERE id=$iId
        SQL))) ;
      else if (NULL == $aRow) $sMsg = 'No such redirect!';
      else if (0 != ($iErr = $this->App->DB->state("DELETE FROM virtual_aliases WHERE id=" . strval($iId))))
      {
         lib\ErrLog::getInstance()->push('Could not delete redirect "' . $aRow['source'] . '", something[' . $iErr . '] went wrong!');
      }
      else
      {
         if (intval($aRow['numdest']) > 1)
            $sMsg = "The destination '{$aRow['destination']}' of redirect '{$aRow['source']}', has been deleted.";
         else
            $sMsg = "The redirect '{$aRow['source']}' of domain {$this->App->sDomSel}, has been deleted.";

         $bSuccess = true;
      }

      return ($iErr);
   }

// ########## METHOD PROTECTED

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawCreate (HtmlPage &$Page)
   {
      $sDomOpts = '';
      $sDomSel  = '';

      if (0 != ($iErr = $this->EDom->getSelectOpts($sDomOpts, $this->iIdLastDom, $sDomSel))) ;
      else return ($Page->addBody(
         '<h3>Create new</h3>'
         . '<div class="inputform">'
         . '<form id="create_redirect" name="create_redirect" action="' . $_SERVER['PHP_SELF'] . '" method="POST">'
         . '<input type="hidden" name="cmd" value="cmd_create" />'
         . '<table>'
         . '<tr>'
         . '<td class="label">Virtual mailbox:</td>'
         . '<td class="value">'
         . '<input type="text" name="ssrc" id="redirect_src" placeholder="Place a mailbox address" value="' . $this->sLastSrc . '" style="text-align: right">'
         . "@" . $this->App->sDomSel
         /*.'@<select name="iiddomain">'
         .$sDomOpts
       .'</select>'*/
         . '</td>'
         . '</tr>'
         . '<tr>'
         . '<td class="label">Destination email:</td>'
         . '<td class="value">'
         . '<input type="text" name="star" placeholder="place an email address" value="' . $this->sLastTar . '">'
         . '</td>'
         . '</tr>'
         . '<tr>'
         . '<td class="label">&nbsp;</td>'
         . '<td class="submit">'
         . '<button onClick="return verifyCreateRedirect(document.create_redirect);">Create</button>'
         . '&nbsp;<button id="breset" style="visibility:hidden; background-color: #cccccc" onClick="resetRedirectForm();">Cancel</button>'
         . '</td>'
         . '</tr>'
         . '</table>'
         . '</form>'
         . '</div>'
      ));
   }

   /**
    *
    * @param string $redirUserId
    * @param string $redirUserName
    * @param int $idCurrentDomain
    * @return int
    */
   public function getUserIdOwnerRedirects (&$redirUserId, &$redirUserName, $idCurrentDomain): int
   {
      if ($this->App->DB->queryOneRow($aRow, <<<SQL
            select id, email
            from virtual_users
            where 
                domain_id = {$idCurrentDomain}
                and substring(email,1,1) = "@"
         SQL
         ) != 0)
      {
         lib\ErrLog::getInstance()->push(__METHOD__ . ": query error.");
         return 1;
      }

      if ($aRow !== null)
      {
         $redirUserId   = $aRow['id'];
         $redirUserName = $aRow['email'];

         return 0;
      }
      elseif ($idCurrentDomain > 0)
      {
         //create mbox owner of domain redirect
         if ($this->createMboxRedirectOwner($idCurrentDomain) == 0)
            return $this->getUserIdOwnerRedirects ($redirUserId, $redirUserName, $idCurrentDomain);
      }

      return 1;
   }

   function createMboxRedirectOwner ($domainID)
   {
      $domName = "";
      if ($this->EDom->getDomainName($domName, $domainID) != 0)
         return 1;

      if ($this->App->DB->state(<<<SQL
            insert into virtual_users (domain_id, email, password)   
            values ($domainID, "@$domName", "~")
         SQL) != 0)
      {
         return 1;
      }

      return 0;
   }

   public function drawList (HtmlPage &$Page)
   {
      $iErr  = 0;
      $sHtml = '';
      //$aSources = array();
      $nEntries = 0;

      //count record for paging
      if (0 != ($iErr = $this->App->DB->queryOneRow($aRow, <<<SQL
            select count(*) as nCnt
            from virtual_aliases
            where mailbox_id = {$this->idCurrentMboxOwner};
         SQL
         ))) ;
      else if (null === $aRow) ;
      else if (0 != ($iErr = lib\checkListPages($this->aStat, ($nEntries = $aRow['nCnt'])))) ;

      //read redirects rows
      $sqlLimit = lib\makeListPagesSqlLimit($this->aStat);
      if (0 != ($iErr = $this->App->DB->query($dbResultSet, <<<SQL
                select * 
                from virtual_aliases
                where
                    mailbox_id = '{$this->idCurrentMboxOwner}'
                    $sqlLimit
                order by source
            SQL
         ))) ;
      else if (0 != ($iErr = $this->App->DB->getNumRows($nRows, $dbResultSet))) ;
      else if (0 == $nRows) $sHtml .= '<tr class=""><td class="" colspan="6">No redirects created yet</td></tr>';
      else
      {
         $htmRedirOpen = function ($redirect) {
            return <<<HTM
               <tr>
                 <td class="icon">
                     <img src="./img/envelope-plus.png" onClick="addNewDestinationOnRedirect('$redirect');" alt="icon new" title="Add new destination to this redirect"/>
                 </td>    
                 <td class="enfasi">
                   $redirect
                 </td>
                 <td>
                    <table class="inside">
               HTM;
         };

         $htmRedirClose = <<<HTM
                   </table>
                 </td>
             </tr>
            HTM;

         $htmDest = function ($ID, $redirect, $Target) {
            return <<<HTM
                    <tr>
                       <td>
                         $Target
                       </td>
                       <td class="icon">
                         <form name="delete_redirect_$ID" action="{$_SERVER['PHP_SELF']}" method="POST">
                            <input type="hidden" name="cmd" value="cmd_delete"/>
                            <input type="hidden" name="idredirect" value="$ID"/>
                            <img src="./img/trash.png" onClick="confirmDeleteRedirect(document.delete_redirect_$ID, '$redirect', '$Target');" alt="icon delete" title = "Delete destination of redirect"/>
                         </form>
                       </td>
                    </tr>
               HTM;
         };

         $aRow       = [];
         $lastSource = null;
         while (0 == ($iErr = $this->App->DB->fetchArray($aRow, $dbResultSet, MYSQLI_ASSOC)) && NULL !== $aRow)
         {
            if ($lastSource == $aRow['source'])
            {
               $sHtml .= $htmDest($aRow['id'], $aRow['source'], $aRow['destination']);
            }
            else
            {
               if (!empty($lastSource))
                  $sHtml .= $htmRedirClose;

               $sHtml .= $htmRedirOpen ($aRow['source']) . $htmDest($aRow['id'], $aRow['source'], $aRow['destination']);
            }

            $lastSource = $aRow['source'];
         }

         if (!empty($lastSource))
            $sHtml .= $htmRedirClose;
      }


      if ($iErr == 0)
      {
         $scrollNumber = lib\makeListPages($this->aStat, $nEntries, 'Alias_ListPage');
         $Page->addBody(<<<HTML
            <h3>Existing Redirects</h3>
            <div class="listgrid">
                $scrollNumber
                <table class="DatabaseList">
                   <tr>
                       <th colspan="2">Virtual mailbox</th>
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
}