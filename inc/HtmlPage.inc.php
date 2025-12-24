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
class HtmlPage
{
// ########## PROPS PUBLIC
   /**
    **
    ** @type IspMailAdminApp
    **/
   public $App = false;
// ########## PROPS PROTECTED
// ########## PROPS PRIVATE
   /**
    * @var string html code to show combobox of managed domain
    */
   public $sDomainSelector = "";
   /**
    **
    ** @type string
    **/
   private $sMenu = '';
   /**
    **
    ** @type string
    **/
   private $sTitle = '';
   /**
    **
    ** @type string
    **/
   private $sHelp = '';
   /**
    **
    ** @type string
    **/
   private $sBody = '';
   /**
    **
    ** @type string
    **/
   private $sMsgBox = '';
   /**
    **
    ** @type string
    **/
   private $sDebug = '';
   /**
    **
    ** @type array
    **/
   private $cssLink = "";

// ########## CONST/DEST
   function __construct (IspMailAdminApp &$App)
   {
      $this->App = &$App;
   }

// ########## METHOD PUBLIC

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function setTitle ($sTxt)
   {
      $this->sTitle .= $sTxt;
      return (0);
   }

   /**
    ** Add css file(s) links.
    ** @param string $sName (string) Path/Name of the desired css file<br>
    ** @returns int
    ** @return 0 on success !0 on error
    **/
   public function addCss ($sName)
   {
      $this->cssLink .= '<link rel="stylesheet" type="text/css" media="all" href="' . $sName . '">' . PHP_EOL;
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function setHelp ($sHtml)
   {
      $this->sHelp .= $sHtml;
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function setMenu ($sHtml)
   {
      $iErr        = 0;
      $this->sMenu .= $sHtml;
      return ($iErr);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function addBody ($sHtml)
   {
      $this->sBody .= $sHtml;
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawMsg ($bError, $sTxt)
   {
      return (!$bError ? $this->drawMsgSuccess($sTxt) : $this->drawMsgError($sTxt));
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawMsgSuccess ($sTxt)
   {
      $this->sMsgBox .= '<div class="MsgSuccess">' . $sTxt . '</div>';
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function drawMsgError ($sTxt)
   {
      $this->sMsgBox .= '<div class="MsgError">' . $sTxt . '</div>';
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function addDebug ($s)
   {
      $this->sDebug .= $s;
      return (0);
   }

   /**
    **
    **
    ** @retval integer
    ** @returns !=0 on error
    **/
   public function send ()
   {
      print <<<TOP
         <!DOCTYPE html>
         <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                <title>{$this->App->getName()}</title>
                <meta name="description" content="Mailserver administration: domains, accounts, and aliases" />

                <link rel="shortcut icon" type="image/x-icon" href="img/favicon.ico" >
                <link rel="icon" type="image/x-icon" href="img/favicon.ico" >
                {$this->cssLink}
                <script src="js/ispmailadm.js" type="text/javascript"></script>
            </head>
            <body>
                <div class="page">
                    <div class="content">
                       <div class="top">
                            <div style="display: flex">
                              <div><img src="img/global-mail.png" height="128" alt="ispmailadm logo" /></div>
                              <div><h1 class="title">{$this->App->getName()}</h1></div>
                            </div>
                            <br>  
                            <div class="menu">
                                {$this->sMenu}
                            </div>
                       </div>
                       
                       <!-- -->
                       <div class="domainSelector">
                           {$this->drawDomainSelector()}
                       </div>
                      
                       <!-- DINAMIC CONTENT BY SELECTED ITEM MENU -->
                       <div class="middle">
                            <h2>{$this->sTitle}</h2>
                            <table>
                            <colgroup><col class="left"><col class="right"></colgroup>
                            <tr>
                                 <td style="padding-right: 10px;">
                                    <div>
                                        {$this->sMsgBox}
                                        {$this->sBody}
                                    </div>
                                 </td>
                                 <td class="help">
                                      {$this->sHelp}
                                 </td>
                            </tr>
                            </table>
                       </div>
                    </div>
                    {$this->sDebug}
                </div>

                <!-- -->
                <div id="page_footer">
                   <table class="footer" style="width: 100%; margin-left: 0px">
                   <tr>
                      <td style="text-align: left">
                          <i>{$this->App->getName()}</i> by <a href="http://ima.jungclaussen.com">Ole Jungclaussen</a>, 
                          <b>version {$this->App->getVersion()},&nbsp;
                          revision {$this->App->getRevision()}</b> by <a href="https://github.com/mspro66" target="_blank">&lt;/mspro66&gt;</a>
                      </td>
                      <td>&nbsp;</td>
                      <td style="text-align: right">
                          Icons by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="http://www.flaticon.com" title="Flaticon">www.flaticon.com</a>  
                      </td>
                   </tr>
                   </table>
                </div>
            </html>
      TOP;

/*
      . '<h2>' . $this->sTitle . '</h2>'
      . '<table class="content">'
      . '<colgroup><col class="left"><col class="right"></colgroup>'
      . '<tr>'
      . '<td id="content">'
      . $this->sMsgBox
      . (!$bCfgErr ? $this->sBody :
         '<div style="border:2px solid red;border-radius:5px;padding:2em;text-align:left;color:red;font-weight:normal;">'
         . implode('<br><br>', $this->App->aCfgErr)
         . '</div>'
      )
      . '</td>'
      . '<td id="page_help">'
      . $this->sHelp
      . '</td>'
      . '</tr>'
      . '</table>'
      . '</div>'
      . '</div>'
      . '<div id="page_footer">' . <<<HTML
                <table class="footer" style="width: 100%; margin-left: 0px"><tr>
                <td style="text-align: left">
                   <i>{$this->App->getName()}</i> by <a href="http://ima.jungclaussen.com">Ole Jungclaussen</a>, 
                   <b>version {$this->App->getVersion()}&nbsp;|&nbsp;
                   revision {$this->App->getRevision()}</b> by <a href="https://github.com/mspro66" target="_blank">&lt;/mspro66&gt;</a>
                </td>
                <td>&nbsp;</td>
                <td style="text-align: right">
                    Icons by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="http://www.flaticon.com" title="Flaticon">www.flaticon.com</a>  
                </td>
                </tr></table>
               HTML
      . '</div>'
      . '</div>'
      . $this->sDebug
      . '</body>'
      . '</html>';*/
      return (0);
   }

   function drawDomainSelector ()
   {
      $htAct = $_SERVER['PHP_SELF'];
      $html  = <<<HTML
            <form id="domain_selector" name="domain_selector" action="$htAct" method="POST">
               <input type="hidden" name="cmd" value="cmd_setIdDomain"/>
                 {$this->sDomainSelector}
            </form>   
         HTML;

      switch ($this->App->sIdPage)
      {
         case 'page_welcome':
         case 'page_overview':
         case 'page_domains':
            return "&nbsp;\n";
         default:
            return $html;
      }
   }
}