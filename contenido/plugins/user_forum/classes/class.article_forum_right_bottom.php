<?php
global $area;
class ArticleForumRightBottom extends cGuiPage {

    private $_indentFactor = 20;

    protected $_collection;

    function __construct() {
        $this->_collection = new ArticleForumCollection();
        parent::__construct('right_bottom', 'forumlist');
    }

    /**
     * form foreach
     *
     * @param $key
     * @param $cont
     *
     *
     * @param $cfg
     * @return array with buttons
     */
    function buildOnlineButtonBackendListMode(&$key, &$cont, &$cfg) {
        global $area;

        $buttons = array();

        $id = $cont['id_user_forum'];
        // shows onlineState
        $online = new cHTMLLink();
        if ($cont['online'] == 1) {
            $online->setImage($cfg['path']['images'] . 'online.gif');
            $online->setCustom('action', 'online_toggle');
            $online->setAlt(UserForum::i18n('SETOFFLINE'));
        } else {
            $online->setImage($cfg['path']['images'] . 'offline.gif');
            $online->setCustom('action', 'offline_toggle');
            $online->setAlt(UserForum::i18n('SETONLINE'));
        }

        $online->setCLink($area, 4, 'show_form');
        $online->setStyle('margin-top:0px; ');
        $online->setTargetFrame('right_bottom');
        $online->setCustom('action', 'online_toggle');
        $online->setCustom('idart', $cont['idart']);
        $online->setCustom('id_user_forum', $cont['id_user_forum']);
        $online->setCustom('idcat', $cont['idcat']);
        $online->setCustom('online', $cont['online']);
        $online->setAttribute('method', 'get');

        // link to edit mode
        $edit = new cHTMLButton("edit");
        $edit->setImageSource($cfg['path']['images'] . 'but_todo.gif');
        $edit->setEvent('click', "$('form[name=$id]').submit()");
        $edit->setMode('image');
        $edit->setAlt(UserForum::i18n('EDIT'));

        // link for delete action
        $delete = new cHTMLLink();
        $delete->setImage($cfg['path']['images'] . 'delete.gif');
        $delete->setAlt(UserForum::i18n('DELETE'));
        $delete->setCLink($area, 4, 'show_form');
        $delete->setTargetFrame('right_bottom');
        $delete->setCustom('action', 'deleteComment');
        $delete->setCustom('level', $cont['level']);
        $delete->setCustom('key', $key);
        $delete->setCustom('id_user_forum', $cont['id_user_forum']);
        $delete->setCustom('idcat', $cont['idcat']);
        $delete->setCustom('idart', $cont['idart']);

        $buttons['online'] = $online;
        $buttons['edit'] = $edit;
        $buttons['delete'] = $delete;

        return $buttons;
    }

    /**
     * generate main menu
     *
     * @param $result array with comments
     * @return ArticleForumRightBottom
     */
    function getMenu(&$result) {
        $table = new cHTMLTable();
        $table->setCellPadding('100px');
        global $area;
        $table->updateAttributes(array(
            "class" => "generic",
            "cellspacing" => "0",
            "cellpadding" => "2"
        ));

        $tr = new cHTMLTableRow();
        $th = new cHTMLTableHead();
        $th->setContent(i18n("FORUM_POST", "user_forum"));
        $tr->appendContent($th);

        $th = new cHTMLTableHead();
        $th->setContent(i18n("ACTIONS", "user_forum"));
        $th->setStyle('widht:20px');
        $th->setAttribute('valign', 'top');
        $tr->appendContent($th);

        $table->appendContent($tr);

        $menu = new cGuiMenu();
        $cfg = cRegistry::getConfig();

        foreach ($result as $key => $cont) {

            $set = false;
            $like = $cont['like'];
            $dislike = $cont['dislike'];
            $date = $cont['timestamp'];

            // build Buttons
            $id = $cont['id_user_forum'];
            $buttons = array();
            $buttons = $this->buildOnlineButtonBackendListMode($key, $cont, $cfg);

            $online = $buttons['online'];
            $edit = $buttons['edit'];
            $delete = $buttons['delete'];

            // row
            $tr = new cHTMLTableRow();
            $trLike = new cHTMLTableRow();

            $likeButton = new cHTMLImage($cfg['path']['images'] . 'like.png');
            // $likeButton->setAttribute('valign','bottom');
            $dislikeButton = new cHTMLImage($cfg['path']['images'] . 'dislike.png');

            $tdEmpty = new cHTMLTableData();
            $tdEmpty->appendContent("<br>");
            $tdLike = new cHTMLTableData();
            $tdEmpty->setAttribute('valign', 'top');
            $tdLike->setAttribute('valign', 'top');

            $tdLike->appendContent($likeButton);
            $tdLike->appendContent(" $like ");
            $tdLike->appendContent($dislikeButton);
            $tdLike->appendContent(" $dislike");

            // $tdLike->appendContent($likeTag . ": " . $like . "<br>");
            // $tdLike->appendContent($dislikeTag . ": " . $dislike);

            // in new row
            $trLike->appendContent($tdEmpty);
            $trLike->appendContent($tdLike);

            $form = new cHTMLForm($cont['id_user_forum']);
            $form->setAttribute('action', 'main.php?' . "area=" . $area . '&frame=4');

            $tdForm = new cHTMLTableData();
            $tdForm->setStyle('padding-left:' . $cont['level'] * $this->_indentFactor . 'px');

            $tdButtons = new cHTMLTableData();
            $tdButtons->setAttribute('valign', 'top');
            $tdButtons->setStyle(' text-align: center;'); // horitontal-align:
                                                          // middle;');
            $tdButtons->appendContent($online);
            $tdButtons->appendContent($edit);
            $tdButtons->appendContent($delete);
            $tdButtons->appendContent('<br>');
            $tdButtons->appendContent('<br>');

            $maili = new cHTMLLink();
            $maili->setLink("mailto:" . $cont['email']);
            $maili->setContent($cont['realname']);

            $text = $cont['forum']; // n2bl

            // create hidden-fields
            $hiddenIdart = new cHTMLHiddenField('idart');
            $hiddenIdcat = new cHTMLHiddenField('idcat');
            $hiddenId_user_forum = new cHTMLHiddenField('id_user_forum');
            $hiddenLike = new cHTMLHiddenField('like');
            $hiddenDislike = new cHTMLHiddenField('dislike');
            $hiddenName = new cHTMLHiddenField('realname');
            $hiddenEmail = new cHTMLHiddenField('email');
            $hiddenLevel = new cHTMLHiddenField('level');
            $hiddenEditdat = new cHTMLHiddenField('editedat');
            $hiddenEditedby = new cHTMLHiddenField('editedby');
            $hiddenTimestamp = new cHTMLHiddenField('timestamp');
            $hiddenForum = new cHTMLHiddenField('forum');
            $hiddenOnline = new cHTMLHiddenField('online');
            $hiddenMode = new cHTMLHiddenField('mode');
            $hiddenKey = new cHTMLHiddenField('key');

            // set values
            $hiddenIdart->setValue($cont['idart']);
            $hiddenIdcat->setValue($cont['idcat']);
            $hiddenId_user_forum->setValue($cont['id_user_forum']);
            $hiddenLike->setValue($cont['like']);
            $hiddenDislike->setValue($cont['dislike']);
            $hiddenName->setValue($cont['realname']);
            $hiddenEmail->setValue($cont['email']);
            $hiddenLevel->setValue($cont['level']);
            $hiddenEditdat->setValue($cont['editedat']);
            $hiddenEditedby->setValue($cont['editedby']);
            $hiddenTimestamp->setValue($cont['timestamp']);
            $hiddenForum->setValue($cont['forum']);
            $hiddenOnline->setValue($cont['online']);
            $hiddenMode->setValue('edit');
            $hiddenKey->setValue($key);

            // append to hidden-fields to form
            $form->appendContent($hiddenIdart);
            $form->appendContent($hiddenIdcat);
            $form->appendContent($hiddenId_user_forum);
            $form->appendContent($hiddenLike);
            $form->appendContent($hiddenDislike);
            $form->appendContent($hiddenName);
            $form->appendContent($hiddenEmail);
            $form->appendContent($hiddenLevel);
            $form->appendContent($hiddenForum);
            $form->appendContent($hiddenEditdat);
            $form->appendContent($hiddenEditedby);
            $form->appendContent($hiddenTimestamp);
            $form->appendContent($hiddenMode);
            $form->appendContent($hiddenOnline);
            $form->appendContent($hiddenKey);

            // generate output text
            $form->appendContent($maili . " schrieb am : " . $date . "<br><br>");
            $form->appendContent($text . "<br>");
            $tdForm->setContent($form);
            $tdForm->setAttribute('valign', 'top');
            $tr->setContent($tdForm);
            $tr->appendContent($tdButtons);
            $tr->appendContent($trLike);
            $table->appendContent($tr);
        }
        $this->appendContent($table);

        return $this;
    }

    /**
     * generate dialog for editmode
     *
     * @param unknown $post
     * @return ArticleForumRightBottom
     */
    function getEditModeMenu($post) {
        global $area;
        $cfg = cRegistry::getConfig();
        $menu = new cGuiMenu();
        $tr = new cHTMLTableRow();

        $th = new cHTMLTableHead();
        $th->setContent(UserForum::i18n("PARAMETER", "user_forum"));

        $th2 = new cHTMLTableHead();
        $th2->setContent(UserForum::i18n("CONTENT", "user_forum"));
        $th2->setStyle('widht:50px');
        $th2->setAttribute('valign', 'top');
        $tr->appendContent($th);
        $tr->appendContent($th2);

        $form1 = new cGuiTableForm("comment", "main.php?area=user_forum&frame=4", "post");
        $form1->addHeader($tr);

        // get User information
        $user = new cApiUser();
        $user->loadByPrimaryKey($post['editedby']);
        $username = $user->getField('username');

        // Dialog EDITMODE :
        $id = $post['id_user_forum'];

        $likeButton = new cHTMLImage($cfg['path']['images'] . 'like.png');
        $dislikeButton = new cHTMLImage($cfg['path']['images'] . 'dislike.png');

        $name = new cHTMLTextBox("realname", conHtmlSpecialChars($post['realname']), 40, 255);
        $email = new cHTMLTextBox("email", conHtmlSpecialChars($post['email']), 40, 255);
        $like = new cHTMLTextBox("like", conHtmlSpecialChars($post['like']), 40, 255);
        $dislike = new cHTMLTextBox("dislike", conHtmlSpecialChars($post['dislike']), 40, 255);
        $forum = new cHTMLTextArea("forum", conHtmlSpecialChars($post['forum']), 30, 10);
        $timestamp = new cHTMLTextBox("timestamp", conHtmlSpecialChars($post['timestamp']), 40, 255);
        $editedat = new cHTMLTextBox("editedat", conHtmlSpecialChars($post['editedat']), 40, 255);
        $editedby = new cHTMLTextBox("editedby", conHtmlSpecialChars($username), 40, 255);

        $editedat->setDisabled(true);
        $timestamp->setDisabled(true);
        $editedby->setDisabled(true);

        if ($post['online'] == 1) {
            $onlineBox = new cHTMLCheckbox("onlineState", 'set_offline');
            $onlineBox->setChecked(false);
            $form1->setVar("checked", "1");
        } else {
            $onlineBox = new cHTMLCheckbox("onlineState", 'set_online');
            $onlineBox->setChecked(false);
            $form1->setVar("checked", "0");
        }

        $idart = $post['idart'];
        $idcat = $post['idcat'];

        $form1->addCancel("main.php?area=user_forum&frame=4&action=back&idart=$idart&idcat=$idcat");
        $form1->add(UserForum::i18n("USER"), $name, '');
        $form1->add(UserForum::i18n("EMAIL"), $email, '');
        $form1->add(UserForum::i18n("LIKE"), $like, '');
        $form1->add(UserForum::i18n("DISLIKE"), $dislike, '');
        $form1->add(UserForum::i18n("TIME"), $timestamp, '');
        $form1->add(UserForum::i18n("EDITDAT"), $editedat, '');
        $form1->add(UserForum::i18n("EDITEDBY"), $editedby, '');
        $form1->add(UserForum::i18n("STATUS"), $onlineBox, '');
        $form1->add(UserForum::i18n("COMMENT"), $forum, '');

        // set hidden fields
        $form1->setVar("id_user_forum", $post['id_user_forum']);
        $form1->setVar("idart", $post['idart']);
        $form1->setVar("idcat", $post['idcat']);
        $form1->setVar("action", 'update');
        $form1->setVar("mode", "list");

        $this->appendContent($form1);

        return $this;
    }

    function getForum($id_cat, $id_art, $id_lang) {
        $arrUsers = $this->_collection->getExistingforum($id_cat, $id_art, $id_lang);
        $arrforum = array();

        $this->_collection->getTreeLevel($id_cat, $id_art, $id_lang, $arrUsers, $arrforum);

        $result = array();
        $this->normalizeArray($arrforum, $result);
        $ret = $this->getMenu($result);

        return $ret;
    }

    function normalizeArray($arrforum, &$result, $level = 0) {
        if (is_array($arrforum)) {
            foreach ($arrforum as $key => $value) {
                $value['level'] = $level;
                unset($value['children']);
                $result[$key] = $value;
                $this->normalizeArray($arrforum[$key]['children'], $result, $level + 1);
            }
        }
    }

    function receiveData() {
    }

    function processReceivedData() {
    }

}

?>