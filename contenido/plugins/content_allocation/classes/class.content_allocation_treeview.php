<?php
/**
 * Class pApiContentAllocation
 * 
 * Stores and handles content allocation management
 *
 * @author Marco Jahn
 * @version 0.2.2
 * @copyright four for business AG
 * @modified 27.10.2005 by Willi Man
 * @modified 24.01.2008 by Andreas Lindner  
 * @modified 26.06.2008 by Timo Trautmann changed post var from   treeItem to treeItemPost (security issue)
 */
 
/**
 * Todo
 * - generalize this and papitree !!!!
 * - Comments!
 */

class pApiContentAllocationTreeView extends pApiTree {

	/**
	 * 
	 */
	var $tpl = null;
	
	/**
	 * 
	 */
	var $template = '';

	/**
	 * 
	 */
	function pApiContentAllocationTreeView ($uuid) {
		global $cfg;
		
		parent::pApiTree($uuid);
		$this->tpl = new Template;
		$this->template = $cfg['pica']['treetemplate'];
	}
	
	/**
	 * 
	 */
	function _buildRenderTree ($tree) {
		global $action, $frame, $area, $sess;
		$result = array();
		foreach ($tree as $item_tmp) {
			$item = array();
			
			// update item
			if ($_GET['step'] == 'rename' && $item_tmp['idpica_alloc'] == $_GET['idpica_alloc']) {
				$item = array();
				
				$item['ITEMNAME'] = '
					<table cellspacing="0" cellpaddin="0" border="0">
					<form name="rename" action="main.php" method="POST" onsubmit="return fieldCheck();">
					<input type="hidden" name="action" value="'.$action.'" />
					<input type="hidden" name="frame" value="'.$frame.'" />
					<input type="hidden" name="contenido" value="'.$sess->id.'" />
					<input type="hidden" name="area" value="'.$area.'" />
					<input type="hidden" name="step" value="storeRename" />
					<input type="hidden" name="treeItemPost[idpica_alloc]" value="'.$item_tmp['idpica_alloc'].'" />
					<tr>
					<td class="text_medium"><input id="itemname" class="text_medium" type="text" name="treeItemPost[name]" value="'.$item_tmp['name'].'"></td>
					<td>&nbsp;
					<a href="main.php?action='.$action.'&frame='.$frame.'&area='.$area.'&contenido='.$sess->id.'"><img src="images/but_cancel.gif" border="0" /></a>
					<input type="image" src="images/but_ok.gif" />
					</td></tr>
					</form>
					</table>
					<script language="JavaScript">
						controller = document.getElementById("itemname");
						controller.focus();
						function fieldCheck() {
							if (controller.value == "") {
								alert("'.i18n("Please enter a category name").'");
								controller.focus();
								return false;
							}
						return true;
						}
					</script>';
			} else {
				if ($item_tmp['children'] || $item_tmp['status'] == 'collapsed') {
					$expandCollapseImg = 'images/close_all.gif';
					if ($item_tmp['status'] == 'collapsed' ) {
						$expandCollapseImg = 'images/open_all.gif';
					}
                    
					$expandCollapse = '<a href="main.php?contenido='.$sess->id.'&idart='.$idart.'&action='.$action.'&frame='.$frame.'&area='.$area.'&step=collapse&idpica_alloc='.$item_tmp['idpica_alloc'].'"><img src="'.$expandCollapseImg.'" border="0" style="vertical-align: middle; padding:4px;" width="7" height="7"></a>';
                } else {
					$expandCollapseImg = 'images/spacer.gif';
					$expandCollapse = '<img src="'.$expandCollapseImg.'" border="0" style="vertical-align: middle;" width="11" height="11">';
				}
				
				$item['ITEMNAME'] = $expandCollapse . ' ' . $item_tmp['name'];
			}
			
			$item['ITEMINDENT'] = $item_tmp['level'] * 15 + 3;
			$item['ACTION_CREATE'] = '<a href="main.php?contenido='.$sess->id.'&action='.$action.'&frame='.$frame.'&area='.$area.'&step=add&parentid='.$item_tmp['idpica_alloc'].'"><img src="images/folder_new.gif" border="0" title="'.i18n("New category").'" alt="'.i18n("New category").'" /></a>';
			
			$item['ACTION_RENAME'] = '<a href="main.php?contenido='.$sess->id.'&action='.$action.'&frame='.$frame.'&area='.$area.'&step=rename&idpica_alloc='.$item_tmp['idpica_alloc'].'"><img src="images/but_todo.gif" width="16" height="16" border="0" alt="'.i18n("Rename category").'" title="'.i18n("Rename category").'" /></a>';
			$item['ACTION_MOVE_UP'] = (count($result) >= 1) ? '<a href="main.php?contenido='.$sess->id.'&action='.$action.'&frame='.$frame.'&area='.$area.'&step=moveup&idpica_alloc='.$item_tmp['idpica_alloc'].'"><img src="images/folder_moveup.gif" border="0" alt="'.i18n("Move category up").'" title="'.i18n("Move category up").'" /></a>' : '<img src="images/spacer.gif" width="16" height="16" /></a>';
			$item['ACTION_MOVE_DOWN'] = (count($result) >= 1) ? '<img src="images/folder_movedown.gif" border="0" alt="'.i18n("Move category down").'" title="'.i18n("Move category down").'" />' : '<img src="images/spacer.gif" width="16" height="16" />';
			$item['ACTION_MOVE_DOWN'] = '';
			
			if ($item_tmp['online'] == 1) { // set offline
				$item['ACTION_ONOFFLINE'] = '<a href="main.php?contenido='.$sess->id.'&action='.$action.'&frame='.$frame.'&area='.$area.'&step=offline&idpica_alloc='.$item_tmp['idpica_alloc'].'""><img src="images/online.gif" alt="'.i18n("Set category offline").'" title="'.i18n("Set category offline").'"></a>';
			} else {
				$item['ACTION_ONOFFLINE'] = '<a href="main.php?contenido='.$sess->id.'&action='.$action.'&frame='.$frame.'&area='.$area.'&step=online&idpica_alloc='.$item_tmp['idpica_alloc'].'""><img src="images/offline.gif" alt="'.i18n("Set category online").'" title="'.i18n("Set category online").'"></a>';
			}
			
			if ($item_tmp['children']) {
				$item['ACTION_DELETE'] = '<img src="images/delete_inact.gif" border="0" alt="'.i18n("One or more subcategories exist, unable to delete").'" title="'.i18n("One or more subcategories exist, unable to delete").'">';
			} else {
				$item['ACTION_DELETE'] = '<a href="javascript://" onclick="box.confirm(&quot;'.i18n("Delete category").'&quot;, &quot;'.i18n("Are you sure to delete the following category").':<br><br><b>'.str_replace("'", "\'", $item_tmp['name']).'</b>&quot;,&quot;deleteCategory('.$item_tmp['idpica_alloc'].')&quot;);"><img src="images/delete.gif" border="0" alt="'.i18n("Delete category").'" title="'.i18n("Delete category").'"></a>';
			}
			
			array_push($result, $item);
			
			if ($item_tmp['children']) {
				$children = $this->_buildRenderTree($item_tmp['children']);
				$result = array_merge($result, $children);	
			}
			
			// add new item -> show formular
			if ($_GET['step'] == 'add' && $item_tmp['idpica_alloc'] == $_GET['parentid']) {
				$item = array();
				
				$item['ITEMNAME'] = '
					<table cellspacing="0" cellpaddin="0" border="0">
					<form name="create" action="main.php" method="POST" onsubmit="return fieldCheck();">
					<input type="hidden" name="action" value="'.$action.'" />
					<input type="hidden" name="frame" value="'.$frame.'" />
					<input type="hidden" name="contenido" value="'.$sess->id.'" />
					<input type="hidden" name="area" value="'.$area.'" />
					<input type="hidden" name="step" value="store" />
					<input type="hidden" name="treeItemPost[parentid]" value="'.$_GET['parentid'].'" />
					<tr>
					<td class="text_medium"><input id="itemname" class="text_medium" type="text" name="treeItemPost[name]" value=""></td>
					<td>&nbsp;
					<a href="main.php?action='.$action.'&frame='.$frame.'&area='.$area.'&contenido='.$sess->id.'"><img src="images/but_cancel.gif" border="0" /></a>
					<input type="image" src="images/but_ok.gif" />
					</td></tr>
					</form>
					</table>
					<script language="JavaScript">
						controller = document.getElementById("itemname");
						controller.focus();
						function fieldCheck() {
							if (controller.value == "") {
								alert("'.i18n("Please enter a category name").'");
								controller.focus();
								return false;
							}
						return true;
						}
					</script>';
				$item['ITEMINDENT'] = ($item_tmp['level'] + 1) * 15;
				$item['ACTION_CREATE'] = '<img src="images/spacer.gif" width="15" height="13" />';
				$item['ACTION_RENAME'] = '<img src="images/spacer.gif" width="23" height="14" />';
				$item['ACTION_MOVE_UP'] = '<img src="images/spacer.gif" width="15" height="13" />';
				$item['ACTION_MOVE_DOWN'] = '<img src="images/spacer.gif" width="15" height="13" />';
				$item['ACTION_MOVE_DOWN'] = '';
				$item['ACTION_DELETE'] = '<img src="images/spacer.gif" width="14" height="13" />';
				$item['ACTION_ONOFFLINE'] = '<img src="images/spacer.gif" width="11" height="12" />';
				
				array_push($result, $item);
			}
		}
		return $result;
	}
	
	/**
	 * 
	 */
	function renderTree ($return = true) {
		$this->tpl->reset();
		
		$tree = $this->fetchTree(false, 0, true); # modified 27.10.2005
		
		if ($tree === false) {
			return false;	
		}
		
		#echo "<pre>";
		#print_r($tree);
		#echo "</pre>";

		$tree = $this->_buildRenderTree($tree);
        
		$even = true;
		foreach ($tree as $item) {
			$even = !$even;
			$bgcolor = ($even) ? '#FFFFFF' : '#F1F1F1';
			$this->tpl->set('d', 'BACKGROUND_COLOR', $bgcolor);
			foreach ($item as $key => $value) {
				$this->tpl->set('d', $key, $value);
			}
			$this->tpl->next();
		}
		
		$this->tpl->set('s', 'CATEGORY', i18n("Category"));
		$this->tpl->set('s', 'ACTIONS', i18n("Actions"));
		
		if ($return === true) {
			return $this->tpl->generate($this->template, true);
		} else {
			$this->tpl->generate($this->template);
		}
	}
}

?>
