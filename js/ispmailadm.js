/**
 **
 **
 ** @package    ISPmail_Admin
 ** @author     Ole Jungclaussen
 ** @version    0.9.9
 **/
/**
 ** #####################################
 ** GOODIES
 **/
if (!String.prototype.trim) {
    String.prototype.trim = function () {
        return this.replace(/^\s+|\s+$/g, '');
    };
}

/**
 **
 **
 ** @retval boolean
 ** @returns true if email-address is acceptable
 **/
function verifyEmailAdress(sEmail) {
    return (sEmail.match(/^[-a-z0-9~!$%^&*_=+}{\'?]+(\.[-a-z0-9~!$%^&*_=+}{\'?]+)*@((?!-))(xn--)?[a-z0-9][a-z0-9-_]{0,61}[a-z0-9]{0,1}\.(xn--)?([a-z0-9]{1,61}|[a-z0-9-]{1,30}\.[a-z]{2,})$/i));
}

function menuChoice(page) {
    f = document.createElement("form");
    f.setAttribute("method", "post");
    f.setAttribute("action", document.URL);

    c = document.createElement("input");
    c.setAttribute("type", "hidden");
    c.setAttribute("name", "cmd");
    c.setAttribute("value", "cmd_openPage");
    f.appendChild(c);

    p = document.createElement("input");
    p.setAttribute("type", "hidden");
    p.setAttribute("name", "spage");
    p.setAttribute("value", page);
    f.appendChild(p);

    document.body.appendChild(f);
    f.submit();
}

function menuLogout() {
    f = document.createElement("form");
    f.setAttribute("method", "post");
    f.setAttribute("action", document.URL);

    c = document.createElement("input");
    c.setAttribute("type", "hidden");
    c.setAttribute("name", "cmd");
    c.setAttribute("value", "cmd_logout");
    f.appendChild(c);

    document.body.appendChild(f);
    f.submit();
}

/**
 ** #####################################
 ** DOMAINS
 **/
/**
 **
 **
 ** @retval boolean
 ** @returns true if domain name is acceptable
 **/
function verifyCreateDomain(Form, bSubmit) {
    var bOk = false;

    Form.sdomain.value = Form.sdomain.value.trim();

    if (0 == Form.sdomain.value.length) alert('Please enter a domain name.');
    else if (!Form.sdomain.value.match(/^((?!-))(xn--)?[a-z0-9][a-z0-9-_]{0,61}[a-z0-9]{0,1}\.(xn--)?([a-z0-9]{1,61}|[a-z0-9-]{1,30}\.[a-z]{2,})$/)) {
        bOk = confirm('\t' + Form.sdomain.value + '\n\nseems not to be a valid Domain name, proceed anyway?');
    } else bOk = true;

    if (bOk && bSubmit) Form.submit();

    return (false);
}

/**
 **
 **
 ** @retval boolean
 ** @returns true user confirmed deletion
 **/
function confirmDeleteDomain(Form, sName) {
    if (confirm('Really delete the domain\n\n\t' + sName + '\n\nand all accounts, aliases and redirects associated with it?')) {
        if (confirm('Do you really want delete the domain\n\n\t' + sName + '\n\n and all things associated with it???'))
            Form.submit();
    }

    return (false);
}

/**
 ** #####################################
 ** ACCOUNTS
 **/
/**
 **
 **
 ** @retval boolean
 ** @returns true account and pass are ok
 **/
function verifyCreateAccount(Form, sDomain) {
    var bOk = false;

    Form.saccount.value = Form.saccount.value.trim();
    Form.pwd_spassword.value = Form.pwd_spassword.value.trim();

    if (0 == Form.saccount.value.length) {
        alert('Please enter a user');
    } else if (0 == Form.pwd_spassword.value.length) {
        alert('Please enter a password');
    } else if (Form.quota && 0 > parseInt(Form.quota.value)) {
        alert('Quota cannot be negative. Use 0 for unlimited.');
    }// else if (!verifyEmailAdress(Form.saccount.value + '@' + sDomain) && !confirm('\t"' + Form.saccount.value + '@' + sDomain + '"\n\nseems not to be a valid email-address, proceed anyway?')) {    }
    else bOk = true;

    if (bOk) Form.submit();

    return (false);
}

/**
 **
 **
 ** @retval boolean
 ** @returns true user confirmed deletion
 **/
function confirmDeleteAccount(Form, sName) {
    if (confirm('Really delete the account\n\n\t' + sName)) {
        Form.submit();
    }
    return (false);
}

/**
 **
 **
 ** @retval boolean
 ** @returns false
 **/
function toggleEditQuota(Form) {
    Form.style.display = (Form.style.display == 'block' ? 'none' : 'block');
    return (false);
}

function cancelEditQuota(Form) {
    Form.style.display = (Form.style.display == 'block' ? 'none' : 'block');
    return (false);
}

/**
 ** @retval boolean
 ** @returns true password not empty
 **/
function confirmChangeQuota(Form) {
    var iQuota = parseInt(Form.quota.value);
    if (0 > iQuota) {
        alert('Quota cannot be negative. Use 0 for unlimited.');
    } else Form.submit();

    return (false);
}

/**
 **
 **
 ** @retval boolean
 ** @returns false
 **/
function toggleNewPassword(Form) {
    Form.style.display = (Form.style.display == 'block' ? 'none' : 'block');
    return (false);
}

/**
 **
 **
 ** @retval boolean
 ** @returns true password not empty
 **/
function confirmChangePassword(Form) {
    Form.pwd_spassword.value = Form.pwd_spassword.value.trim();
    if (0 == Form.pwd_spassword.value.length) alert('Please enter a password');
    else Form.submit();
    return (false);
}

/**
 ** #####################################
 ** ALIASES
 **/
/**
 **
 **
 ** @retval boolean
 ** @returns true if alias email-address name is acceptable
 **/
function verifyCreateAlias(Form) {
    var bOk = false;

    Form.ssource.value = Form.ssource.value.trim();

    if (0 == Form.ssource.value.length)
        alert('Please enter a valid email-address as alias');
    else
        Form.submit();
    /*else{
        var sSrc = Form.ssource.value+'@'+Form.iiddomain.options[Form.iiddomain.selectedIndex].innerHTML;
        bOk = true;
        if(!verifyEmailAdress(sSrc)){
            bOk = confirm('Source\n\n\t"'+sSrc+'"\n\nseems not to be a valid email-address, proceed anyway?');
        }
    }
    
    if(bOk) Form.submit();
    */

    return (false);
}

/**
 **
 **
 ** @retval boolean
 ** @returns true user confirmed deletion
 **/
function confirmDeleteAlias(Form, sAlias) {
    if (confirm('Really delete the alias\n\n\t' + sAlias)) {
        Form.submit();
    }
    return (false);
}

/**
 * Set fields for add destination to existing alias
 * @param alias
 */
function newAddedAlias(alias) {
    fs = document.getElementsByName("ssource")[0];
    fs.readOnly = true;
    fs.className = "readonly";
    fs.value = alias.match(/[0-9a-z-_.]*/i);

    fd = document.getElementsByName('sdest')[0];
    fd.readOnly = false;
    fd.className = "";
    fd.value = "";

    document.getElementById('breset').style.visibility = "visible";
    fd.focus();
    return (false);
}

function resetAliasForm() {
    fs = document.getElementsByName("ssource")[0];
    fd = document.getElementsByName('sdest')[0];

    fs.readOnly = false;
    fs.className = "";
    fs.value = "";

    fd.readOnly = true;
    fd.className = "readonly";
    fd.value = "";

    document.getElementById('breset').style.visibility = "hidden";
    fs.focus();
    return (false);
}

/**
 ** #####################################
 ** Redirects
 **/
/**
 **
 **
 ** @retval boolean
 ** @returns true if alias email-address name is acceptable
 **/
function verifyCreateRedirect(Form) {
    var bOk = false;

    Form.ssrc.value = Form.ssrc.value.toLowerCase().trim();
    Form.star.value = Form.star.value.toLowerCase().trim();

    if (0 == Form.ssrc.value.length) alert('Please enter a valid email-address as redirect (virtual mailbox)');
    else if (0 == Form.star.value.length) alert('Please enter a valid email-address as destination');
    else {
        bOk = true;
        /*var sSrc = Form.ssrc.value + '@' + Form.iiddomain.options[Form.iiddomain.selectedIndex].innerHTML;
        bOk = true;

        if (!verifyEmailAdress(sSrc)) {
            bOk = confirm('Source\n\n\t"' + sSrc + '"\n\nseems not to be a valid email-address, proceed anyway?');
        }*/

/*
        if (bOk && !verifyEmailAdress(Form.star.value)) {
            bOk = confirm('Destination\n\n\t"' + Form.star.value + '"\n\nseems not to be a valid email-address, proceed anyway?');
        }
*/
    }
    if (bOk) Form.submit();

    return (false);
}

/**
 * Set fields for add destination to existing alias
 * @param alias
 */
function addNewDestinationOnRedirect(redirect) {
    fs = document.getElementsByName("ssrc")[0];
    fs.readOnly = true;
    fs.className = "readonly";
    fs.value = redirect.match(/[0-9a-z-_\.]*/i);

    fd = document.getElementsByName('star')[0];
    fd.className = "";
    fd.value = "";

    document.getElementById('breset').style.visibility = "visible";
    fd.focus();
    return (false);
}

function resetRedirectForm() {
    fs = document.getElementsByName("ssrc")[0];
    fd = document.getElementsByName('star')[0];

    fs.readOnly = false;
    fs.className = "";
    fs.value = "";

    fd.value = "";

    document.getElementById('breset').style.visibility = "hidden";
    fs.focus();
}

/**
 **
 **
 ** @retval boolean
 ** @returns true user confirmed deletion
 **/
function confirmDeleteRedirect(Form, sSrc, sTar) {
    if (confirm('Really delete the redirect\n\n\t' + sSrc + '\n\nto\n\n\t' + sTar)) {
        Form.submit();
    }
    return (false);
}

/**
 ** #####################################
 ** Redirects
 **/
/**
 **
 **
 ** @retval boolean
 ** @returns true user confirmed deletion
 **/
function confirmDeleteFromBlacklist(Form, sAddress) {
    if (confirm('Really delete the address\n\n\t' + sAddress)) {
        Form.submit();
    }
    return (false);
}
