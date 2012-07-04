<?php
/**
 * Project:
 * CONTENIDO Content Management System
 *
 * Description:
 * MyContenido
 *
 * Requirements:
 * @con_php_req 5.0
 *
 *
 * @package    CONTENIDO Backend Includes
 * @version    1.1.3
 * @author     unknown
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since CONTENIDO release <= 4.6
 *
 * {@internal
 *   created unknown
 *   $Id$:
 * }}
 */

if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}


$user = new cApiUser($auth->auth["uid"]);

$noti = "";

if ($action == "mycontenido_editself") {

    if (!isset($wysi)) {
        $wysi = false;
    }

    $error = false;

    if ($newpassword != "") {
        if (cApiUser::encodePassword($oldpassword) != $user->get("password")) {
            $error = i18n("Old password incorrect");
        }

        if (strcmp($newpassword, $newpassword2) != 0) {
            $error = i18n("Passwords don't match");
        }


        if ($error !== false) {
            $noti = $notification->returnNotification("error", $error)."<br>";
        } else {
            // New Class User, update password

            $iResult = $user->savePassword($newpassword);

            #$user->set("password", md5($newpassword));

            if ($iResult == cApiUser::PASS_OK) {
                $noti = $notification->returnNotification("info", i18n("Changes saved"))."<br>";
            } else {
                $noti = $notification->returnNotification("error", cApiUser::getErrorString($iResult)."<br>");
            }
        }
    }

    if ($user->get("realname") != $name) {
        $user->set("realname", $name);
    }
    if ($user->get("email") != $email) {
        $user->set("email", $email);
    }
    if ($user->get("telephone") != $phonenumber) {
        $user->set("telephone", $phonenumber);
    }
    if ($user->get("address_street") != $street) {
        $user->set("address_street", $street);
    }
    if ($user->get("address_zip") != $zip) {
        $user->set("address_zip", $zip);
    }
    if ($user->get("address_city") != $city) {
        $user->set("address_city", $city);
    }
    if ($user->get("address_country") != $country) {
        $user->set("address_country", $country);
    }
    if ($user->get("wysi") != $wysi) {
        $user->set("wysi", $wysi);
    }

    $user->setUserProperty("dateformat", "full", $format);
    $user->setUserProperty("dateformat", "date", $formatdate);
    $user->setUserProperty("dateformat", "time", $formattime);

    if ($user->store() && $noti == "") {
        $noti = $notification->returnNotification("info", i18n("Changes saved"))."<br>";
    } else if ($noti == "") {
        $noti = $notification->returnNotification("error", i18n("An error occured while saving user info."))."<br>";
    }
}


$settingsfor = sprintf(i18n("Settings for %s"), $user->get("username") . " (".$user->get("realname").")");

$form = new UI_Table_Form("settings");

$form->setVar("idlang", $lang);
$form->setVar("area", $area);
$form->setVar("action", "mycontenido_editself");
$form->setVar("frame", $frame);

$form->addHeader($settingsfor);

$realname = new cHTMLTextbox("name", $user->get("realname"));
$form->add(i18n("Name"), $realname);

// @since 2006-07-04 Display password fields if not authenticated via LDAP/AD, only
if ($user->get("password") != 'active_directory_auth') {
    $oldpassword = new cHTMLPasswordbox("oldpassword");
    $newpassword = new cHTMLPasswordbox("newpassword");
    $newpassword2 = new cHTMLPasswordbox("newpassword2");

    $form->add(i18n("Old password"), $oldpassword);
    $form->add(i18n("New password"), $newpassword);
    $form->add(i18n("Confirm new password"), $newpassword2);
}

$email = new cHTMLTextbox("email", $user->get("email"));
$form->add(i18n("E-Mail"), $email);

$phone = new cHTMLTextbox("phonenumber", $user->get("telephone"));
$form->add(i18n("Phone number"), $phone);

$street = new cHTMLTextbox("street", $user->get("address_street"));
$form->add(i18n("Street"), $street);

$zipcode = new cHTMLTextbox("zip", $user->get("address_zip"), "10", "10");
$form->add(i18n("ZIP code"), $zipcode);

$city = new cHTMLTextbox("city", $user->get("address_city"));
$form->add(i18n("City"), $city);

$country = new cHTMLTextbox("country", $user->get("address_country"));
$form->add(i18n("Country"), $country);

$wysiwyg = new cHTMLCheckbox("wysi", 1);
$wysiwyg->setChecked($user->get("wysi"));
$wysiwyg->setLabelText(i18n("Use WYSIWYG Editor"));

$form->add(i18n("Options"), array($wysiwyg));

$formathint = "<br>".i18n("The format is equal to PHP's date() function.");
$formathint.= "<br>";
$formathint.= i18n("Common date formattings").":";
$formathint.= "<br>";
$formathint.= "d M Y H:i => 01 Jan 2004 00:00";
$formathint.= "<br>";
$formathint.= "d.m.Y H:i:s => 01.01.2004 00:00:00";

$format = new cHTMLTextbox("format", $user->getUserProperty("dateformat", "full"));
$format2 = new cHTMLTextbox("formatdate", $user->getUserProperty("dateformat", "date"));
$format3 = new cHTMLTextbox("formattime", $user->getUserProperty("dateformat", "time"));

$form->add(i18n("Date/Time format"), array($format, $formathint));
$form->add(i18n("Date format"), array($format2));
$form->add(i18n("Time format"), array($format3));

$page = new cPage();

$page->setContent(array($noti, $form, markSubMenuItem(3, true)));
$page->render();
?>