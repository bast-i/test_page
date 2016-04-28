<?php

// Data functions (insert, update, delete, form) for table categories

// This script and data application were generated by AppGini 5.50
// Download AppGini for free from http://bigprof.com/appgini/download/

function categories_insert(){
	global $Translation;

	// mm: can member insert record?
	$arrPerm=getTablePermissions('categories');
	if(!$arrPerm[1]){
		return false;
	}

	$data['CategoryName'] = makeSafe($_REQUEST['CategoryName']);
		if($data['CategoryName'] == empty_lookup_value){ $data['CategoryName'] = ''; }
	$data['Description'] = makeSafe($_REQUEST['Description']);
		if($data['Description'] == empty_lookup_value){ $data['Description'] = ''; }
	$data['Picture'] = PrepareUploadedFile('Picture', 204800,'jpg|jpeg|gif|png', false, '');
	if($data['Picture']) createThumbnail($data['Picture'], getThumbnailSpecs('categories', 'Picture', 'tv'));
	if($data['Picture']) createThumbnail($data['Picture'], getThumbnailSpecs('categories', 'Picture', 'dv'));

	/* for empty upload fields, when saving a copy of an existing record, copy the original upload field */
	if($_REQUEST['SelectedID']){
		$res = sql("select * from categories where CategoryID='" . makeSafe($_REQUEST['SelectedID']) . "'");
		if($row = db_fetch_assoc($res)){
			if(!$data['Picture']) $data['Picture'] = makeSafe($row['Picture']);
		}
	}

	// hook: categories_before_insert
	if(function_exists('categories_before_insert')){
		$args=array();
		if(!categories_before_insert($data, getMemberInfo(), $args)){ return false; }
	}

	$o=array('silentErrors' => true);
	sql('insert into `categories` set       ' . ($data['Picture'] != '' ? "`Picture`='{$data['Picture']}'" : '`Picture`=NULL') . ', `CategoryName`=' . (($data['CategoryName'] !== '' && $data['CategoryName'] !== NULL) ? "'{$data['CategoryName']}'" : 'NULL') . ', `Description`=' . (($data['Description'] !== '' && $data['Description'] !== NULL) ? "'{$data['Description']}'" : 'NULL'), $o);
	if($o['error']!=''){
		echo $o['error'];
		echo "<a href=\"categories_view.php?addNew_x=1\">{$Translation['< back']}</a>";
		exit;
	}

	$recID = db_insert_id(db_link());

	// hook: categories_after_insert
	if(function_exists('categories_after_insert')){
		$res = sql("select * from `categories` where `CategoryID`='" . makeSafe($recID, false) . "' limit 1", $eo);
		if($row = db_fetch_assoc($res)){
			$data = array_map('makeSafe', $row);
		}
		$data['selectedID'] = makeSafe($recID, false);
		$args=array();
		if(!categories_after_insert($data, getMemberInfo(), $args)){ return (get_magic_quotes_gpc() ? stripslashes($recID) : $recID); }
	}

	// mm: save ownership data
	sql("insert ignore into membership_userrecords set tableName='categories', pkValue='$recID', memberID='".getLoggedMemberID()."', dateAdded='".time()."', dateUpdated='".time()."', groupID='".getLoggedGroupID()."'", $eo);

	return (get_magic_quotes_gpc() ? stripslashes($recID) : $recID);
}

function categories_delete($selected_id, $AllowDeleteOfParents=false, $skipChecks=false){
	// insure referential integrity ...
	global $Translation;
	$selected_id=makeSafe($selected_id);

	// mm: can member delete record?
	$arrPerm=getTablePermissions('categories');
	$ownerGroupID=sqlValue("select groupID from membership_userrecords where tableName='categories' and pkValue='$selected_id'");
	$ownerMemberID=sqlValue("select lcase(memberID) from membership_userrecords where tableName='categories' and pkValue='$selected_id'");
	if(($arrPerm[4]==1 && $ownerMemberID==getLoggedMemberID()) || ($arrPerm[4]==2 && $ownerGroupID==getLoggedGroupID()) || $arrPerm[4]==3){ // allow delete?
		// delete allowed, so continue ...
	}else{
		return $Translation['You don\'t have enough permissions to delete this record'];
	}

	// hook: categories_before_delete
	if(function_exists('categories_before_delete')){
		$args=array();
		if(!categories_before_delete($selected_id, $skipChecks, getMemberInfo(), $args))
			return $Translation['Couldn\'t delete this record'];
	}

	// child table: products
	$res = sql("select `CategoryID` from `categories` where `CategoryID`='$selected_id'", $eo);
	$CategoryID = db_fetch_row($res);
	$rires = sql("select count(1) from `products` where `CategoryID`='".addslashes($CategoryID[0])."'", $eo);
	$rirow = db_fetch_row($rires);
	if($rirow[0] && !$AllowDeleteOfParents && !$skipChecks){
		$RetMsg = $Translation["couldn't delete"];
		$RetMsg = str_replace("<RelatedRecords>", $rirow[0], $RetMsg);
		$RetMsg = str_replace("<TableName>", "products", $RetMsg);
		return $RetMsg;
	}elseif($rirow[0] && $AllowDeleteOfParents && !$skipChecks){
		$RetMsg = $Translation["confirm delete"];
		$RetMsg = str_replace("<RelatedRecords>", $rirow[0], $RetMsg);
		$RetMsg = str_replace("<TableName>", "products", $RetMsg);
		$RetMsg = str_replace("<Delete>", "<input type=\"button\" class=\"button\" value=\"".$Translation['yes']."\" onClick=\"window.location='categories_view.php?SelectedID=".urlencode($selected_id)."&delete_x=1&confirmed=1';\">", $RetMsg);
		$RetMsg = str_replace("<Cancel>", "<input type=\"button\" class=\"button\" value=\"".$Translation['no']."\" onClick=\"window.location='categories_view.php?SelectedID=".urlencode($selected_id)."';\">", $RetMsg);
		return $RetMsg;
	}

	// delete file stored in the 'Picture' field
	$res = sql("select `Picture` from `categories` where `CategoryID`='$selected_id'", $eo);
	if($row=@db_fetch_row($res)){
		if($row[0]!=''){
			@unlink(getUploadDir('').$row[0]);
			preg_match('/^[a-z0-9_]+\.(gif|png|jpg|jpeg|jpe)$/i', $row[0], $m);
			$thumbDV=str_replace(".$m[1]ffffgggg", "_dv.$m[1]", $row[0].'ffffgggg');
			$thumbTV=str_replace(".$m[1]ffffgggg", "_tv.$m[1]", $row[0].'ffffgggg');
			@unlink(getUploadDir('').$thumbTV);
			@unlink(getUploadDir('').$thumbDV);
		}
	}

	sql("delete from `categories` where `CategoryID`='$selected_id'", $eo);

	// hook: categories_after_delete
	if(function_exists('categories_after_delete')){
		$args=array();
		categories_after_delete($selected_id, getMemberInfo(), $args);
	}

	// mm: delete ownership data
	sql("delete from membership_userrecords where tableName='categories' and pkValue='$selected_id'", $eo);
}

function categories_update($selected_id){
	global $Translation;

	// mm: can member edit record?
	$arrPerm=getTablePermissions('categories');
	$ownerGroupID=sqlValue("select groupID from membership_userrecords where tableName='categories' and pkValue='".makeSafe($selected_id)."'");
	$ownerMemberID=sqlValue("select lcase(memberID) from membership_userrecords where tableName='categories' and pkValue='".makeSafe($selected_id)."'");
	if(($arrPerm[3]==1 && $ownerMemberID==getLoggedMemberID()) || ($arrPerm[3]==2 && $ownerGroupID==getLoggedGroupID()) || $arrPerm[3]==3){ // allow update?
		// update allowed, so continue ...
	}else{
		return false;
	}

	$data['CategoryName'] = makeSafe($_REQUEST['CategoryName']);
		if($data['CategoryName'] == empty_lookup_value){ $data['CategoryName'] = ''; }
	$data['Description'] = makeSafe($_REQUEST['Description']);
		if($data['Description'] == empty_lookup_value){ $data['Description'] = ''; }
	$data['selectedID']=makeSafe($selected_id);
	if($_REQUEST['Picture_remove'] == 1){
		$data['Picture'] = '';
		// delete file from server
		$res = sql("select `Picture` from `categories` where `CategoryID`='".makeSafe($selected_id)."'", $eo);
		if($row=@db_fetch_row($res)){
			if($row[0]!=''){
				@unlink(getUploadDir('').$row[0]);
				preg_match('/^[a-z0-9_]+\.(gif|png|jpg|jpeg|jpe)$/i', $row[0], $m);
				$thumbDV=str_replace(".$m[1]ffffgggg", "_dv.$m[1]", $row[0].'ffffgggg');
				$thumbTV=str_replace(".$m[1]ffffgggg", "_tv.$m[1]", $row[0].'ffffgggg');
				@unlink(getUploadDir('').$thumbTV);
				@unlink(getUploadDir('').$thumbDV);
			}
		}
	}else{
		$data['Picture'] = PrepareUploadedFile('Picture', 204800, 'jpg|jpeg|gif|png', false, "");
		if($data['Picture']) createThumbnail($data['Picture'], getThumbnailSpecs('categories', 'Picture', 'tv'));
		if($data['Picture']) createThumbnail($data['Picture'], getThumbnailSpecs('categories', 'Picture', 'dv'));
		// delete file from server
		if($data['Picture'] != ''){
			$res = sql("select `Picture` from `categories` where `CategoryID`='".makeSafe($selected_id)."'", $eo);
			if($row=@db_fetch_row($res)){
				if($row[0]!=''){
					@unlink(getUploadDir('').$row[0]);
					preg_match('/^[a-z0-9_]+\.(gif|png|jpg|jpeg|jpe)$/i', $row[0], $m);
					$thumbDV=str_replace(".$m[1]ffffgggg", "_dv.$m[1]", $row[0].'ffffgggg');
					$thumbTV=str_replace(".$m[1]ffffgggg", "_tv.$m[1]", $row[0].'ffffgggg');
					@unlink(getUploadDir('').$thumbTV);
					@unlink(getUploadDir('').$thumbDV);
				}
			}
		}
	}

	// hook: categories_before_update
	if(function_exists('categories_before_update')){
		$args=array();
		if(!categories_before_update($data, getMemberInfo(), $args)){ return false; }
	}

	$o=array('silentErrors' => true);
	sql('update `categories` set       ' . ($data['Picture']!='' ? "`Picture`='{$data['Picture']}'" : ($_REQUEST['Picture_remove'] != 1 ? '`Picture`=`Picture`' : '`Picture`=NULL')) . ', `CategoryName`=' . (($data['CategoryName'] !== '' && $data['CategoryName'] !== NULL) ? "'{$data['CategoryName']}'" : 'NULL') . ', `Description`=' . (($data['Description'] !== '' && $data['Description'] !== NULL) ? "'{$data['Description']}'" : 'NULL') . " where `CategoryID`='".makeSafe($selected_id)."'", $o);
	if($o['error']!=''){
		echo $o['error'];
		echo '<a href="categories_view.php?SelectedID='.urlencode($selected_id)."\">{$Translation['< back']}</a>";
		exit;
	}


	// hook: categories_after_update
	if(function_exists('categories_after_update')){
		$res = sql("SELECT * FROM `categories` WHERE `CategoryID`='{$data['selectedID']}' LIMIT 1", $eo);
		if($row = db_fetch_assoc($res)){
			$data = array_map('makeSafe', $row);
		}
		$data['selectedID'] = $data['CategoryID'];
		$args = array();
		if(!categories_after_update($data, getMemberInfo(), $args)){ return; }
	}

	// mm: update ownership data
	sql("update membership_userrecords set dateUpdated='".time()."' where tableName='categories' and pkValue='".makeSafe($selected_id)."'", $eo);

}

function categories_form($selected_id = '', $AllowUpdate = 1, $AllowInsert = 1, $AllowDelete = 1, $ShowCancel = 0){
	// function to return an editable form for a table records
	// and fill it with data of record whose ID is $selected_id. If $selected_id
	// is empty, an empty form is shown, with only an 'Add New'
	// button displayed.

	global $Translation;

	// mm: get table permissions
	$arrPerm=getTablePermissions('categories');
	if(!$arrPerm[1] && $selected_id==''){ return ''; }
	$AllowInsert = ($arrPerm[1] ? true : false);
	// print preview?
	$dvprint = false;
	if($selected_id && $_REQUEST['dvprint_x'] != ''){
		$dvprint = true;
	}


	// populate filterers, starting from children to grand-parents

	// unique random identifier
	$rnd1 = ($dvprint ? rand(1000000, 9999999) : '');

	if($selected_id){
		// mm: check member permissions
		if(!$arrPerm[2]){
			return "";
		}
		// mm: who is the owner?
		$ownerGroupID=sqlValue("select groupID from membership_userrecords where tableName='categories' and pkValue='".makeSafe($selected_id)."'");
		$ownerMemberID=sqlValue("select lcase(memberID) from membership_userrecords where tableName='categories' and pkValue='".makeSafe($selected_id)."'");
		if($arrPerm[2]==1 && getLoggedMemberID()!=$ownerMemberID){
			return "";
		}
		if($arrPerm[2]==2 && getLoggedGroupID()!=$ownerGroupID){
			return "";
		}

		// can edit?
		if(($arrPerm[3]==1 && $ownerMemberID==getLoggedMemberID()) || ($arrPerm[3]==2 && $ownerGroupID==getLoggedGroupID()) || $arrPerm[3]==3){
			$AllowUpdate=1;
		}else{
			$AllowUpdate=0;
		}

		$res = sql("select * from `categories` where `CategoryID`='".makeSafe($selected_id)."'", $eo);
		if(!($row = db_fetch_array($res))){
			return error_message($Translation['No records found']);
		}
		$urow = $row; /* unsanitized data */
		$hc = new CI_Input();
		$row = $hc->xss_clean($row); /* sanitize data */
	}else{
	}

	ob_start();
	?>

	<script>
		// initial lookup values

		jQuery(function() {
		});
	</script>
	<?php

	$lookups = str_replace('__RAND__', $rnd1, ob_get_contents());
	ob_end_clean();


	// code for template based detail view forms

	// open the detail view template
	if($dvprint){
		$templateCode = @file_get_contents('./templates/categories_templateDVP.html');
	}else{
		$templateCode = @file_get_contents('./templates/categories_templateDV.html');
	}

	// process form title
	$templateCode = str_replace('<%%DETAIL_VIEW_TITLE%%>', 'Add/Edit Product Categories', $templateCode);
	$templateCode = str_replace('<%%RND1%%>', $rnd1, $templateCode);
	$templateCode = str_replace('<%%EMBEDDED%%>', ($_REQUEST['Embedded'] ? 'Embedded=1' : ''), $templateCode);
	// process buttons
	if($arrPerm[1] && !$selected_id){ // allow insert and no record selected?
		if(!$selected_id) $templateCode=str_replace('<%%INSERT_BUTTON%%>', '<button type="submit" class="btn btn-success" id="insert" name="insert_x" value="1" onclick="return categories_validateData();"><i class="glyphicon glyphicon-plus-sign"></i> ' . $Translation['Save New'] . '</button>', $templateCode);
		$templateCode=str_replace('<%%INSERT_BUTTON%%>', '<button type="submit" class="btn btn-default" id="insert" name="insert_x" value="1" onclick="return categories_validateData();"><i class="glyphicon glyphicon-plus-sign"></i> ' . $Translation['Save As Copy'] . '</button>', $templateCode);
	}else{
		$templateCode=str_replace('<%%INSERT_BUTTON%%>', '', $templateCode);
	}

	// 'Back' button action
	if($_REQUEST['Embedded']){
		$backAction = 'window.parent.jQuery(\'.modal\').modal(\'hide\'); return false;';
	}else{
		$backAction = '$$(\'form\')[0].writeAttribute(\'novalidate\', \'novalidate\'); document.myform.reset(); return true;';
	}

	if($selected_id){
		if(!$_REQUEST['Embedded']) $templateCode=str_replace('<%%DVPRINT_BUTTON%%>', '<button type="submit" class="btn btn-default" id="dvprint" name="dvprint_x" value="1" onclick="$$(\'form\')[0].writeAttribute(\'novalidate\', \'novalidate\'); document.myform.reset(); return true;"><i class="glyphicon glyphicon-print"></i> ' . $Translation['Print Preview'] . '</button>', $templateCode);
		if($AllowUpdate){
			$templateCode=str_replace('<%%UPDATE_BUTTON%%>', '<button type="submit" class="btn btn-success btn-lg" id="update" name="update_x" value="1" onclick="return categories_validateData();"><i class="glyphicon glyphicon-ok"></i> ' . $Translation['Save Changes'] . '</button>', $templateCode);
		}else{
			$templateCode=str_replace('<%%UPDATE_BUTTON%%>', '', $templateCode);
		}
		if(($arrPerm[4]==1 && $ownerMemberID==getLoggedMemberID()) || ($arrPerm[4]==2 && $ownerGroupID==getLoggedGroupID()) || $arrPerm[4]==3){ // allow delete?
			$templateCode=str_replace('<%%DELETE_BUTTON%%>', '<button type="submit" class="btn btn-danger" id="delete" name="delete_x" value="1" onclick="return confirm(\'' . $Translation['are you sure?'] . '\');"><i class="glyphicon glyphicon-trash"></i> ' . $Translation['Delete'] . '</button>', $templateCode);
		}else{
			$templateCode=str_replace('<%%DELETE_BUTTON%%>', '', $templateCode);
		}
		$templateCode=str_replace('<%%DESELECT_BUTTON%%>', '<button type="submit" class="btn btn-default" id="deselect" name="deselect_x" value="1" onclick="' . $backAction . '"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['Back'] . '</button>', $templateCode);
	}else{
		$templateCode=str_replace('<%%UPDATE_BUTTON%%>', '', $templateCode);
		$templateCode=str_replace('<%%DELETE_BUTTON%%>', '', $templateCode);
		$templateCode=str_replace('<%%DESELECT_BUTTON%%>', ($ShowCancel ? '<button type="submit" class="btn btn-default" id="deselect" name="deselect_x" value="1" onclick="' . $backAction . '"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['Back'] . '</button>' : ''), $templateCode);
	}

	// set records to read only if user can't insert new records and can't edit current record
	if(($selected_id && !$AllowUpdate) || (!$selected_id && !$AllowInsert)){
		$jsReadOnly .= "\tjQuery('#Picture').replaceWith('<div class=\"form-control-static\" id=\"Picture\">' + (jQuery('#Picture').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('#CategoryName').replaceWith('<div class=\"form-control-static\" id=\"CategoryName\">' + (jQuery('#CategoryName').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('.select2-container').hide();\n";

		$noUploads = true;
	}elseif(($AllowInsert && !$selected_id) || ($AllowUpdate && $selected_id)){
		$jsEditable .= "\tjQuery('form').eq(0).data('already_changed', true);"; // temporarily disable form change handler
			$jsEditable .= "\tjQuery('form').eq(0).data('already_changed', false);"; // re-enable form change handler
	}

	// process combos

	/* lookup fields array: 'lookup field name' => array('parent table name', 'lookup field caption') */
	$lookup_fields = array();
	foreach($lookup_fields as $luf => $ptfc){
		$pt_perm = getTablePermissions($ptfc[0]);

		// process foreign key links
		if($pt_perm['view'] || $pt_perm['edit']){
			$templateCode = str_replace("<%%PLINK({$luf})%%>", '<button type="button" class="btn btn-default view_parent hspacer-lg" id="' . $ptfc[0] . '_view_parent" title="' . htmlspecialchars($Translation['View'] . ' ' . $ptfc[1], ENT_QUOTES, 'iso-8859-1') . '"><i class="glyphicon glyphicon-eye-open"></i></button>', $templateCode);
		}

		// if user has insert permission to parent table of a lookup field, put an add new button
		if($pt_perm['insert'] && !$_REQUEST['Embedded']){
			$templateCode = str_replace("<%%ADDNEW({$ptfc[0]})%%>", '<button type="button" class="btn btn-success add_new_parent" id="' . $ptfc[0] . '_add_new" title="' . htmlspecialchars($Translation['Add New'] . ' ' . $ptfc[1], ENT_QUOTES, 'iso-8859-1') . '"><i class="glyphicon glyphicon-plus-sign"></i></button>', $templateCode);
		}
	}

	// process images
	$templateCode=str_replace('<%%UPLOADFILE(CategoryID)%%>', '', $templateCode);
	$templateCode=str_replace('<%%UPLOADFILE(Picture)%%>', ($noUploads ? '' : '<input type=hidden name=MAX_FILE_SIZE value=204800>'.$Translation['upload image'].' <input type="file" name="Picture" id="Picture">'), $templateCode);
	if($AllowUpdate && $row['Picture']!=''){
		$templateCode=str_replace('<%%REMOVEFILE(Picture)%%>', '<br><input type="checkbox" name="Picture_remove" id="Picture_remove" value="1"> <label for="Picture_remove" style="color: red; font-weight: bold;">'.$Translation['remove image'].'</label>', $templateCode);
	}else{
		$templateCode=str_replace('<%%REMOVEFILE(Picture)%%>', '', $templateCode);
	}
	$templateCode=str_replace('<%%UPLOADFILE(CategoryName)%%>', '', $templateCode);
	$templateCode=str_replace('<%%UPLOADFILE(Description)%%>', '', $templateCode);

	// process values
	if($selected_id){
		$templateCode=str_replace('<%%VALUE(CategoryID)%%>', htmlspecialchars($row['CategoryID'], ENT_QUOTES, 'iso-8859-1'), $templateCode);
		$templateCode=str_replace('<%%URLVALUE(CategoryID)%%>', urlencode($urow['CategoryID']), $templateCode);
		$row['Picture']=($row['Picture']!=''?$row['Picture']:'blank.gif');
		$templateCode=str_replace('<%%VALUE(Picture)%%>', htmlspecialchars($row['Picture'], ENT_QUOTES, 'iso-8859-1'), $templateCode);
		$templateCode=str_replace('<%%URLVALUE(Picture)%%>', urlencode($urow['Picture']), $templateCode);
		$templateCode=str_replace('<%%VALUE(CategoryName)%%>', htmlspecialchars($row['CategoryName'], ENT_QUOTES, 'iso-8859-1'), $templateCode);
		$templateCode=str_replace('<%%URLVALUE(CategoryName)%%>', urlencode($urow['CategoryName']), $templateCode);
		if($AllowUpdate || $AllowInsert){
			$templateCode=str_replace('<%%HTMLAREA(Description)%%>', '<textarea name="Description" id="Description" rows="5">'.htmlspecialchars($row['Description'], ENT_QUOTES, 'iso-8859-1').'</textarea>', $templateCode);
		}else{
			$templateCode=str_replace('<%%HTMLAREA(Description)%%>', $row['Description'], $templateCode);
		}
		$templateCode=str_replace('<%%VALUE(Description)%%>', nl2br($row['Description']), $templateCode);
		$templateCode=str_replace('<%%URLVALUE(Description)%%>', urlencode($urow['Description']), $templateCode);
	}else{
		$templateCode=str_replace('<%%VALUE(CategoryID)%%>', '', $templateCode);
		$templateCode=str_replace('<%%URLVALUE(CategoryID)%%>', urlencode(''), $templateCode);
		$templateCode=str_replace('<%%VALUE(Picture)%%>', 'blank.gif', $templateCode);
		$templateCode=str_replace('<%%VALUE(CategoryName)%%>', '', $templateCode);
		$templateCode=str_replace('<%%URLVALUE(CategoryName)%%>', urlencode(''), $templateCode);
		$templateCode=str_replace('<%%HTMLAREA(Description)%%>', '<textarea name="Description" id="Description" rows="5"></textarea>', $templateCode);
	}

	// process translations
	foreach($Translation as $symbol=>$trans){
		$templateCode=str_replace("<%%TRANSLATION($symbol)%%>", $trans, $templateCode);
	}

	// clear scrap
	$templateCode=str_replace('<%%', '<!-- ', $templateCode);
	$templateCode=str_replace('%%>', ' -->', $templateCode);

	// hide links to inaccessible tables
	if($_REQUEST['dvprint_x'] == ''){
		$templateCode .= "\n\n<script>\$j(function(){\n";
		$arrTables = getTableList();
		foreach($arrTables as $name => $caption){
			$templateCode .= "\t\$j('#{$name}_link').removeClass('hidden');\n";
			$templateCode .= "\t\$j('#xs_{$name}_link').removeClass('hidden');\n";
		}

		$templateCode .= $jsReadOnly;
		$templateCode .= $jsEditable;

		if(!$selected_id){
		}

		$templateCode.="\n});</script>\n";
	}

	// ajaxed auto-fill fields
	$templateCode .= '<script>';
	$templateCode .= '$j(function() {';


	$templateCode.="});";
	$templateCode.="</script>";
	$templateCode .= $lookups;

	// handle enforced parent values for read-only lookup fields

	// don't include blank images in lightbox gallery
	$templateCode = preg_replace('/blank.gif" data-lightbox=".*?"/', 'blank.gif"', $templateCode);

	// don't display empty email links
	$templateCode=preg_replace('/<a .*?href="mailto:".*?<\/a>/', '', $templateCode);

	// hook: categories_dv
	if(function_exists('categories_dv')){
		$args=array();
		categories_dv(($selected_id ? $selected_id : FALSE), getMemberInfo(), $templateCode, $args);
	}

	return $templateCode;
}
?>