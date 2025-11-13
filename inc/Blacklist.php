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
class Blacklist {
// ########## PROPS PUBLIC
    /**
    **
    ** @type IspMailAdminApp
    **/
    public $App = false;
    /**
    **
    ** @type array
    **/
    public $aStat = null;
    /**
    **
    ** @type string
    **/
    public $sLastAddr = '';
    /**
    **
    ** @type string
    **/
    public $sLastReason = '';
// ########## PROPS PROTECTED
// ########## PROPS PRIVATE
// ########## CONST/DEST
    function __construct(IspMailAdminApp &$App)
    {
        $this->App   = &$App;
        $this->aStat = &$App->aAlsStat;
    }
    function __destruct()
    {
        
    }
// ########## METHOD PUBLIC
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    public function setTitleAndHelp(HtmlPage &$Page)
    {
        $this->App->Page->setTitle('Blacklist');
        $this->App->Page->setHelp(
            '<div class="Heading">Manage blacklisted e-mail addresses (e.g. "wellknown@spammer.biz") that are not allowed to be used (anymore).</div>'
            .'<div class="Heading">Account, Alias, and Redirect will verify against this.</div>'
            .'<ul>'
            .'<li>Create an entry: Enter the email-address and click "Add"</li>'
            .'<li>Delete an entry: Click on <img class="icon" src="./img/trash.png" alt="delete icon" /></li>'
            .'</ul>'
        );
        return(0);
    }
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    public function processCmd()
    {
        $iErr = 0;
        if(!isset($this->App->aReqParam['cmd']));
        else switch($this->App->aReqParam['cmd']){
            case 'cmd_add':
                $bSuccess = false;

                if(!isset($this->App->aReqParam['address']));
                else if(0==strlen($this->sLastAddr = trim($this->App->aReqParam['address'])));
                else if(!isset($this->App->aReqParam['reason']));
                else if(null===($this->sLastReason = trim($this->App->aReqParam['reason'])));
                else if(0!=($iErr = $this->add($sMsg, $bSuccess, $this->sLastAddr, $this->sLastReason)));
                else $this->App->Page->drawMsg(!$bSuccess, $sMsg);
                
                // clear fields on success
                if($bSuccess) $this->sLastAddr = '';
                break;

            case 'cmd_delete':
                $bSuccess = false;
                if(!isset($this->App->aReqParam['idaddress']));
                else if(0>=($iIdAddress = intval($this->App->aReqParam['idaddress'])));
                else if(0!=($iErr = $this->delete($sMsg, $bSuccess, $iIdAddress)));
                else $this->App->Page->drawMsg(!$bSuccess, $sMsg);
                break;

            case 'cmd_listpage':
                $this->aStat['iIdxPage'] = $this->App->aReqParam['idxpage'];
                break;

            default:
                break;
        }
        return($iErr);
    }
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    public function drawCreate(HtmlPage &$Page)
    {
        return($Page->addBody(
            '<h3>Add new</h3>'
            .'<div class="InputForm">'
              .'<form id="add_blacklist" name="add_blacklist" action="'.$_SERVER['PHP_SELF'].'" method="POST">'
                .'<input type="hidden" name="cmd" value="cmd_add" />'
                .'<table class="InputForm">'
                  .'<tr>'
                    .'<td class="label">Address:</td>'
                    .'<td class="value">'
                      .'<input type="text" name="address" id="address" placeholder="" value="'.$this->sLastAddr.'">'
                    .'</td>'
                  .'</tr>'
                  .'<tr>'
                    .'<td class="label">Reason:</td>'
                    .'<td class="value">'
                      .'<input type="text" name="reason" id="reason" value="'.$this->sLastReason.'">'
                    .'</td>'
                  .'</tr>'
                  .'<tr>'
                    .'<td class="label">&nbsp;</td>'
                    .'<td class="submit">'
                      .'<a class="button" onClick="document.add_blacklist.submit();">Add</a>'
                    .'</td>'
                  .'</tr>'
                .'</table>'
              .'</form>'
            .'</div>'
        ));
    }
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    public function drawList(HtmlPage &$Page)
    {
        $iErr = 0;
        $sHtml = '';
        $nEntries=0;
        
        if(0!=($iErr = $this->App->DB->queryOneRow($aRow,
            "SELECT"
            ." COUNT(address) AS nCnt"
            ." FROM `blacklist_email` AS blacklist"
        )));
        else if(null===$aRow);
        else if(0!=($iErr = lib\checkListPages($this->aStat, ($nEntries = $aRow['nCnt']))));
        
        if(0!=($iErr = $this->App->DB->query($rRslt,
            "SELECT"
            ." id AS iId"
            .",address AS sAddress"
            .",reason AS sReason"
            ." FROM blacklist_email"
            ." ORDER BY sAddress ASC"
            .lib\makeListPagesSqlLimit($this->aStat)
        ))); 
        else if(0!=($iErr = $this->App->DB->getNumRows($nRows, $rRslt)));
        else if(0==$nRows) $sHtml .= '<tr class=""><td class="" colspan="6">No blacklist entries</td></tr>';
        else while(0==($iErr = $this->App->DB->fetchArray($aRow, $rRslt, MYSQLI_ASSOC)) && NULL!==$aRow){
            $sHtml .= 
                '<tr>'
                .'<td class="icon">'
                  .'<form name="delete_blacklist_'.strval($aRow['iId']).'" action="'.$_SERVER['PHP_SELF'].'" method="POST">'
                    .'<input type="hidden" name="cmd" value="cmd_delete" />'
                    .'<input type="hidden" name="idaddress" value="'.strval($aRow['iId']).'" />'
                    .'<img class="icon" src="./img/trash.png" onClick="confirmDeleteFromBlacklist(document.delete_blacklist_'.strval($aRow['iId']).', \''.$aRow['sAddress'].'\');" alt="icon delete"/>'
                  .'</form>'
                .'</td>'
                .'<td class="">'.$aRow['sAddress'].'</td>'
                .'<td class="">'.$aRow['sReason'].'</td>'
                .'</tr>'
            ;
        }

        if(0!=$iErr);
        else if(0!=($iErr = $Page->addBody(
            '<h3>Current Blacklistes E-Mail Addresses</h3>'
            .'<div class="DatabaseList">'
              .lib\makeListPages($this->aStat, $nEntries, 'Blacklist_ListPage')
              .'<table class="DatabaseList">'
              .'<colgroup><col width="16"><col width="*"><col width="30%"></colgroup>'
                .'<tr>'
                  .'<th></th>'
                  .'<th>Address</th>'
                  .'<th>Reason</th>'
                .'</tr>'
                .$sHtml
              .'</table>'
            .'</div>'
        )));
        
        return($iErr);
    }
// ########## METHOD PROTECTED
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    protected function add(&$sMsg, &$bSuccess, $sAddress, $sReason)
    {
        $iErr = 0;
        $bSuccess = false;
        
        if(0!=($iErr = $this->getExistingAddress($aTar, $sAddress)));
        else if(count($aTar)){
            $sMsg .= '"'.$sAddress.'" is already blacklisted.';
        }
        else if(0!=($iErr = $this->App->DB->state(
            // reminder: this has to work with SQLite (IMA-Demo), too
            // - SQLite3 doesn't know the "INSERT ... SET" Syntax
            "INSERT INTO blacklist_email (address, reason) VALUES ("
              ."'".$this->App->DB->realEscapeString($sAddress)."'"
              .",'".$this->App->DB->realEscapeString($sReason)."'"
            .")"
        ))){
            lib\ErrLog::getInstance()->push('Could not add "'.$sAddress.', something['.$iErr.'] went wrong!');
        }
        else{
            $sMsg = '"'.$sAddress.'" blacklisted.';
            $bSuccess = true;
        }
        return($iErr);
    }
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    protected function delete(&$sMsg, &$bSuccess, $iId)
    {
        $iErr = 0;
        $bSuccess = false;
        if(0!=($iErr = $this->App->DB->queryOneRow($aRow, 
            "SELECT address FROM blacklist_email WHERE id=".strval($iId)
        )));
        else if(NULL==$aRow) $sMsg = 'No such address!';
        else if(0!=($iErr = $this->App->DB->state(
            "DELETE FROM blacklist_email WHERE id=".strval($iId)
        ))){
            lib\ErrLog::getInstance()->push('Could not delete address "'.$aRow['address'].'", something['.$iErr.'] went wrong!');
        }
        else{
            $sMsg = '"'.$aRow['address'].'" removed from blacklist.';
            $bSuccess = true;
        }
        return($iErr);
    }
    /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
    protected function getExistingAddress(&$aTar, $sAddress)
    {
        $iErr = 0;
        $aTar = array();
        if(0!=($iErr = $this->App->DB->query($rRslt, 
            "SELECT address as sAddr"
            ." FROM blacklist_email"
            ." WHERE address='".$this->App->DB->realEscapeString($sAddress)."'"
        )));
        else if(0!=($iErr = $this->App->DB->getNumRows($nRows, $rRslt)));
        else if(0==$nRows);
        else while(0==($iErr = $this->App->DB->fetchArray($aRow, $rRslt, MYSQLI_ASSOC)) && NULL!==$aRow){
            $aTar[] = $aRow['sTar'];
        }
        return($iErr);
    }
// ########## METHOD PRIVATE
};
?>